<?php

namespace FrameworkStandardization\Preview;

use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use PDO;

final class DbReadOnlyAliasCleanupPreview
{
    private $pdo;
    private $dbPrefix;
    private $contract;
    private $normalizer;
    private $sampleLimit = 20;

    public function __construct(PDO $pdo, $dbPrefix, array $contract, SimpleMetersNormalizer $normalizer)
    {
        $this->pdo = $pdo;
        $this->dbPrefix = $dbPrefix;
        $this->contract = $contract;
        $this->normalizer = $normalizer;
    }

    public function generate($runtimeMode)
    {
        if ((string) $this->contract['normalizer_key'] !== 'simple_meters') {
            throw new \RuntimeException('normalizer_not_supported');
        }

        $categoryScopeIds = $this->loadCategoryScopeIds();
        $scopeProductIds = $this->loadScopeProductIds($categoryScopeIds);
        $canonicalRows = $this->loadCanonicalRows($categoryScopeIds);
        $aliasRows = $this->loadAliasRows($categoryScopeIds);
        $breakdown = $this->createEmptyBreakdown();
        $reasonCounts = $this->createEmptyReasonCounts();
        $sampleSafelyRemovable = array();
        $sampleNotRemovable = array();
        $safelyRemovable = 0;
        $notRemovable = 0;

        foreach ($aliasRows as $row) {
            $attributeId = (int) $row['attribute_id'];
            $breakdown[$attributeId]['total_rows']++;
            $classification = $this->classifyAliasRow($row, $canonicalRows, $scopeProductIds);

            if ($classification['safe']) {
                $safelyRemovable++;
                $breakdown[$attributeId]['safely_removable']++;

                if (count($sampleSafelyRemovable) < $this->sampleLimit) {
                    $sampleSafelyRemovable[] = $this->buildSampleRow($row, $classification['reason'], $classification['canonical_value']);
                }
            } else {
                $notRemovable++;
                $breakdown[$attributeId]['not_removable']++;
                $reasonCounts[$classification['reason']]++;

                if (count($sampleNotRemovable) < $this->sampleLimit) {
                    $sampleNotRemovable[] = $this->buildSampleRow($row, $classification['reason'], $classification['canonical_value']);
                }
            }
        }

        $expectedCountsMatch = $this->expectedCountsMatch(count($aliasRows), $safelyRemovable, $notRemovable, $reasonCounts) ? 1 : 0;

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_readonly_alias_cleanup_preview',
            'target_key' => (string) $this->contract['target_key'],
            'target_meaning' => (string) $this->contract['target_meaning'],
            'category_scope' => (int) $this->contract['category_scope_id'],
            'category_scope_ids_count' => count($categoryScopeIds),
            'canonical_attribute_id' => (int) $this->contract['canonical_attribute_id'],
            'alias_attribute_ids' => $this->contract['alias_attribute_ids'],
            'normalizer_key' => (string) $this->contract['normalizer_key'],
            'target_table' => $this->dbPrefix . 'product_attribute',
            'total_alias_rows_in_scope' => count($aliasRows),
            'safely_removable_alias_rows' => $safelyRemovable,
            'not_removable_alias_rows' => $notRemovable,
            'breakdown_by_alias_attribute_id' => array_values($breakdown),
            'not_removable_reasons' => $reasonCounts,
            'expected_alias_total_rows_after_cleanup' => (int) $this->contract['expected_alias_total_rows_after_cleanup'],
            'expected_alias_safely_removable_after_cleanup' => (int) $this->contract['expected_alias_safely_removable_after_cleanup'],
            'expected_alias_not_removable_after_cleanup' => (int) $this->contract['expected_alias_not_removable_after_cleanup'],
            'expected_alias_unresolved_or_excluded_after_cleanup' => (int) $this->contract['expected_alias_unresolved_or_excluded_after_cleanup'],
            'expected_counts_match' => $expectedCountsMatch,
            'sample_safely_removable' => $sampleSafelyRemovable,
            'sample_not_removable' => $sampleNotRemovable,
            'safety_markers' => array(
                'db_readonly' => 1,
                'delete_executed' => 0,
                'sql_applied' => 0,
                'production_ready' => 0,
                'cache_rebuild_performed' => 0,
                'touches_oc_attribute' => 0,
                'touches_oc_attribute_description' => 0,
            ),
        );
    }

    private function classifyAliasRow(array $row, array $canonicalRows, array $scopeProductIds)
    {
        $productId = (int) $row['product_id'];
        $languageId = (int) $row['language_id'];
        $key = $this->buildCanonicalKey($productId, $languageId);
        $canonicalValue = '';

        if (!isset($scopeProductIds[$productId])) {
            return array('safe' => false, 'reason' => 'product_outside_scope', 'canonical_value' => $canonicalValue);
        }

        $normalized = $this->normalizer->normalize((string) $row['text']);

        if ($normalized['normalized_value'] === null) {
            return array('safe' => false, 'reason' => 'unresolved_or_excluded_value', 'canonical_value' => $canonicalValue);
        }

        if ((int) $row['exact_row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case', 'canonical_value' => $canonicalValue);
        }

        if (!isset($canonicalRows[$key])) {
            return array('safe' => false, 'reason' => 'missing_canonical_row', 'canonical_value' => $canonicalValue);
        }

        if ($canonicalRows[$key]['row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case', 'canonical_value' => $canonicalValue);
        }

        $canonicalValue = $canonicalRows[$key]['text'];

        if ((string) $normalized['normalized_value'] !== (string) $canonicalValue) {
            return array('safe' => false, 'reason' => 'canonical_value_mismatch', 'canonical_value' => $canonicalValue);
        }

        return array('safe' => true, 'reason' => 'covered_by_canonical_row', 'canonical_value' => $canonicalValue);
    }

    private function loadCategoryScopeIds()
    {
        $sql = 'SELECT category_id, parent_id FROM ' . $this->dbPrefix . 'category';
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $childrenByParent = array();

        foreach ($rows as $row) {
            $parentId = (int) $row['parent_id'];

            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }

            $childrenByParent[$parentId][] = (int) $row['category_id'];
        }

        $rootCategoryId = (int) $this->contract['category_scope_id'];
        $scope = array($rootCategoryId => true);
        $queue = array($rootCategoryId);

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (!isset($childrenByParent[$current])) {
                continue;
            }

            foreach ($childrenByParent[$current] as $childId) {
                if (!isset($scope[$childId])) {
                    $scope[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($scope);
    }

    private function loadScopeProductIds(array $categoryScopeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT DISTINCT product_id FROM ' . $this->dbPrefix . 'product_to_category ';
        $sql .= 'WHERE category_id IN (' . implode(', ', $categoryPlaceholders) . ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $productIds = array();

        foreach ($rows as $row) {
            $productIds[(int) $row['product_id']] = true;
        }

        return $productIds;
    }

    private function loadCanonicalRows(array $categoryScopeIds)
    {
        $params = array(':attribute_id' => (int) $this->contract['canonical_attribute_id']);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT pa.product_id, pa.language_id, TRIM(pa.text) AS text, COUNT(*) AS row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id ';
        $sql .= 'GROUP BY pa.product_id, pa.language_id, TRIM(pa.text)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $canonicalRows = array();

        foreach ($rows as $row) {
            $key = $this->buildCanonicalKey($row['product_id'], $row['language_id']);

            if (!isset($canonicalRows[$key])) {
                $canonicalRows[$key] = array(
                    'text' => (string) $row['text'],
                    'row_count' => (int) $row['row_count'],
                );
                continue;
            }

            $canonicalRows[$key]['row_count'] += (int) $row['row_count'];
        }

        return $canonicalRows;
    }

    private function loadAliasRows(array $categoryScopeIds)
    {
        $params = array(':language_id' => 1);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $this->contract['alias_attribute_ids'], $params);
        $sql = 'SELECT pa.product_id, pa.attribute_id, pa.language_id, pa.text, ad.name AS attribute_name, COUNT(*) AS exact_row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute_description ad ';
        $sql .= 'ON ad.attribute_id = pa.attribute_id AND ad.language_id = :language_id ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'GROUP BY pa.product_id, pa.attribute_id, pa.language_id, pa.text, ad.name ';
        $sql .= 'ORDER BY pa.attribute_id, pa.product_id, pa.language_id, pa.text';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createEmptyBreakdown()
    {
        $breakdown = array();

        foreach ($this->contract['alias_attribute_ids'] as $attributeId) {
            $breakdown[(int) $attributeId] = array(
                'alias_attribute_id' => (int) $attributeId,
                'total_rows' => 0,
                'safely_removable' => 0,
                'not_removable' => 0,
            );
        }

        return $breakdown;
    }

    private function createEmptyReasonCounts()
    {
        return array(
            'missing_canonical_row' => 0,
            'canonical_value_mismatch' => 0,
            'unresolved_or_excluded_value' => 0,
            'product_outside_scope' => 0,
            'duplicate_or_conflict_case' => 0,
        );
    }

    private function expectedCountsMatch($total, $safelyRemovable, $notRemovable, array $reasonCounts)
    {
        return $total === (int) $this->contract['expected_alias_total_rows_after_cleanup']
            && $safelyRemovable === (int) $this->contract['expected_alias_safely_removable_after_cleanup']
            && $notRemovable === (int) $this->contract['expected_alias_not_removable_after_cleanup']
            && $reasonCounts['unresolved_or_excluded_value'] === (int) $this->contract['expected_alias_unresolved_or_excluded_after_cleanup'];
    }

    private function buildSampleRow(array $row, $reason, $canonicalValue)
    {
        return array(
            'product_id' => (int) $row['product_id'],
            'attribute_id' => (int) $row['attribute_id'],
            'attribute_name' => (string) $row['attribute_name'],
            'language_id' => (int) $row['language_id'],
            'raw_value' => (string) $row['text'],
            'canonical_value' => (string) $canonicalValue,
            'reason' => (string) $reason,
        );
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

        return $placeholders;
    }
}
