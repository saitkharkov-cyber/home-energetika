<?php

namespace FrameworkStandardization\Preview;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Review\DbReadOnlyNormalizationReviewChain;

final class DbReadOnlySqlPreview
{
    private $db;
    private $dbPrefix;
    private $reviewChain;

    public function __construct(ReadOnlyDbConnectionInterface $db, $dbPrefix, DbReadOnlyNormalizationReviewChain $reviewChain)
    {
        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
        $this->reviewChain = $reviewChain;
    }

    public function generate($categoryId, array $attributeIds, $canonicalAttributeId, $canonicalUnit)
    {
        $reviewResult = $this->reviewChain->generate($categoryId, $attributeIds, $canonicalAttributeId, $canonicalUnit);
        $schema = $this->inspectStorageSchema($canonicalAttributeId);
        $sourceRows = $this->loadSourceRows($reviewResult['category_scope_ids'], $attributeIds);
        $sourceLanguageByKey = $this->indexSourceLanguages($sourceRows);
        $canonicalRows = $this->loadCanonicalRows($reviewResult['category_scope_ids'], $canonicalAttributeId);
        $canonicalRowsByKey = $this->indexCanonicalRows($canonicalRows);
        $conflictKeys = $this->detectConflicts($reviewResult['review_rows'], $sourceLanguageByKey);
        $actions = array();
        $statements = array();
        $updateCount = 0;
        $insertCount = 0;
        $keepSourceCount = 0;
        $schemaBlockerCount = 0;
        $conflictsCount = 0;

        foreach ($reviewResult['review_rows'] as $row) {
            $sourceKey = $this->buildSourceKey($row['product_id'], $row['attribute_id'], $row['raw_value']);
            $languageId = isset($sourceLanguageByKey[$sourceKey]) ? (int) $sourceLanguageByKey[$sourceKey] : 0;
            $targetKey = $this->buildCanonicalKey($row['product_id'], $languageId);
            $hasCanonicalRow = isset($canonicalRowsByKey[$targetKey]);
            $schemaBlocked = ($schema['schema_status'] !== 'ok' || $languageId <= 0);
            $conflictBlocked = isset($conflictKeys[$targetKey]);
            $statement = '';

            if ((int) $row['attribute_id'] !== (int) $canonicalAttributeId) {
                $keepSourceCount++;
            }

            if ($schemaBlocked) {
                $previewAction = 'schema_blocker';
                $reason = $languageId <= 0 ? 'source_language_id_not_found' : 'storage_schema_not_supported';
                $schemaBlockerCount++;
            } elseif ($conflictBlocked) {
                $previewAction = 'preview_conflict_blocked';
                $reason = 'conflicting_review_approved_values_for_same_product_language';
                $conflictsCount++;
            } elseif ((int) $row['attribute_id'] === (int) $canonicalAttributeId || $hasCanonicalRow) {
                $previewAction = 'preview_update_existing_canonical_row';
                $reason = (int) $row['attribute_id'] === (int) $canonicalAttributeId
                    ? 'review_approved_existing_canonical_row'
                    : 'review_approved_alias_with_existing_canonical_row';
                $statement = $this->buildUpdatePreviewSql($row['product_id'], $canonicalAttributeId, $languageId, $row['proposed_normalized_value']);
                $updateCount++;
            } else {
                $previewAction = 'preview_insert_missing_canonical_row';
                $reason = 'review_approved_alias_missing_canonical_row';
                $statement = $this->buildInsertPreviewSql($row['product_id'], $canonicalAttributeId, $languageId, $row['proposed_normalized_value']);
                $insertCount++;
            }

            $actions[] = array(
                'product_id' => (int) $row['product_id'],
                'source_attribute_id' => (int) $row['attribute_id'],
                'source_attribute_name' => (string) $row['attribute_name'],
                'canonical_attribute_id' => (int) $canonicalAttributeId,
                'language_id' => $languageId,
                'raw_value' => (string) $row['raw_value'],
                'proposed_normalized_value' => (string) $row['proposed_normalized_value'],
                'canonical_unit' => (string) $canonicalUnit,
                'preview_action' => $previewAction,
                'reason' => $reason,
                'keep_existing_source_row' => (int) $row['attribute_id'] === (int) $canonicalAttributeId ? 0 : 1,
            );

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'sql_preview',
            'category_id' => (int) $categoryId,
            'category_scope_ids' => $reviewResult['category_scope_ids'],
            'attribute_ids' => $attributeIds,
            'canonical_attribute_id' => (int) $canonicalAttributeId,
            'canonical_unit' => (string) $canonicalUnit,
            'storage_schema' => $schema,
            'actions' => $actions,
            'statements' => $statements,
            'excluded_unresolved' => $reviewResult['unresolved_rows'],
            'preview_update_existing_canonical_row_count' => $updateCount,
            'preview_insert_missing_canonical_row_count' => $insertCount,
            'keep_existing_source_row_count' => $keepSourceCount,
            'unresolved_excluded_count' => count($reviewResult['unresolved_rows']),
            'schema_blocker_count' => $schemaBlockerCount,
            'conflicts_count' => $conflictsCount,
        );
    }

    private function inspectStorageSchema($canonicalAttributeId)
    {
        $tableName = $this->dbPrefix . 'product_attribute';
        $rows = $this->db->fetchAll(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name ORDER BY ORDINAL_POSITION',
            array(':table_name' => $tableName)
        );
        $columns = array();

        foreach ($rows as $row) {
            $columns[] = (string) $row['COLUMN_NAME'];
        }

        $required = array('product_id', 'attribute_id', 'language_id', 'text');
        $missing = array();

        foreach ($required as $column) {
            if (!in_array($column, $columns, true)) {
                $missing[] = $column;
            }
        }

        $canonicalRow = $this->db->fetchOne(
            'SELECT a.attribute_id, ad.name FROM ' . $this->dbPrefix . 'attribute a INNER JOIN ' . $this->dbPrefix . 'attribute_description ad ON ad.attribute_id = a.attribute_id WHERE a.attribute_id = :attribute_id AND ad.language_id = :language_id',
            array(':attribute_id' => (int) $canonicalAttributeId, ':language_id' => 1)
        );

        $notes = array();
        $schemaStatus = 'ok';

        if (count($missing) > 0) {
            $schemaStatus = 'blocked';
            $notes[] = 'missing_columns: ' . implode(',', $missing);
        }

        if (!isset($canonicalRow['attribute_id'])) {
            $schemaStatus = 'blocked';
            $notes[] = 'canonical_attribute_row_missing';
        } else {
            $notes[] = 'canonical_attribute_row_exists';
        }

        if (count($notes) === 0) {
            $notes[] = 'schema_supports_product_attribute_text_preview';
        }

        return array(
            'table_name' => $tableName,
            'relevant_columns' => $columns,
            'required_columns' => $required,
            'schema_status' => $schemaStatus,
            'notes' => $notes,
        );
    }

    private function loadSourceRows(array $categoryScopeIds, array $attributeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $attributeIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.attribute_id, pa.language_id, TRIM(pa.text) AS raw_value ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ')';

        return $this->db->fetchAll($sql, $params);
    }

    private function loadCanonicalRows(array $categoryScopeIds, $canonicalAttributeId)
    {
        $params = array(':canonical_attribute_id' => (int) $canonicalAttributeId);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.language_id, TRIM(pa.text) AS current_text ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :canonical_attribute_id';

        return $this->db->fetchAll($sql, $params);
    }

    private function indexSourceLanguages(array $rows)
    {
        $index = array();

        foreach ($rows as $row) {
            $key = $this->buildSourceKey($row['product_id'], $row['attribute_id'], $row['raw_value']);
            $index[$key] = (int) $row['language_id'];
        }

        return $index;
    }

    private function indexCanonicalRows(array $rows)
    {
        $index = array();

        foreach ($rows as $row) {
            $index[$this->buildCanonicalKey($row['product_id'], $row['language_id'])] = true;
        }

        return $index;
    }

    private function detectConflicts(array $reviewRows, array $sourceLanguageByKey)
    {
        $valuesByTarget = array();

        foreach ($reviewRows as $row) {
            $sourceKey = $this->buildSourceKey($row['product_id'], $row['attribute_id'], $row['raw_value']);
            $languageId = isset($sourceLanguageByKey[$sourceKey]) ? (int) $sourceLanguageByKey[$sourceKey] : 0;
            $targetKey = $this->buildCanonicalKey($row['product_id'], $languageId);
            $value = (string) $row['proposed_normalized_value'];

            if (!isset($valuesByTarget[$targetKey])) {
                $valuesByTarget[$targetKey] = array();
            }

            $valuesByTarget[$targetKey][$value] = true;
        }

        $conflicts = array();

        foreach ($valuesByTarget as $targetKey => $values) {
            if (count($values) > 1) {
                $conflicts[$targetKey] = true;
            }
        }

        return $conflicts;
    }

    private function buildUpdatePreviewSql($productId, $canonicalAttributeId, $languageId, $normalizedValue)
    {
        return 'UPDATE ' . $this->dbPrefix . "product_attribute SET text = '" . $this->escapeSqlLiteral($normalizedValue) . "' WHERE product_id = " . (int) $productId . ' AND attribute_id = ' . (int) $canonicalAttributeId . ' AND language_id = ' . (int) $languageId . ';';
    }

    private function buildInsertPreviewSql($productId, $canonicalAttributeId, $languageId, $normalizedValue)
    {
        return 'INSERT INTO ' . $this->dbPrefix . "product_attribute (product_id, attribute_id, language_id, text) VALUES (" . (int) $productId . ', ' . (int) $canonicalAttributeId . ', ' . (int) $languageId . ", '" . $this->escapeSqlLiteral($normalizedValue) . "');";
    }

    private function escapeSqlLiteral($value)
    {
        return str_replace("'", "''", (string) $value);
    }

    private function buildSourceKey($productId, $attributeId, $rawValue)
    {
        return (int) $productId . '|' . (int) $attributeId . '|' . trim((string) $rawValue);
    }

    private function buildCanonicalKey($productId, $languageId)
    {
        return (int) $productId . '|' . (int) $languageId;
    }

    private function buildPlaceholders($prefix, array $values, array &$params)
    {
        $placeholders = array();
        $index = 0;

        foreach ($values as $value) {
            $key = ':' . $prefix . '_' . $index;
            $params[$key] = (int) $value;
            $placeholders[] = $key;
            $index++;
        }

        if (count($placeholders) === 0) {
            $key = ':' . $prefix . '_empty';
            $params[$key] = 0;
            $placeholders[] = $key;
        }

        return $placeholders;
    }
}
