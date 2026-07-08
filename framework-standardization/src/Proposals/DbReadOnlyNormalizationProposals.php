<?php

namespace FrameworkStandardization\Proposals;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;

final class DbReadOnlyNormalizationProposals
{
    private $db;
    private $dbPrefix;
    private $languageId;

    public function __construct(ReadOnlyDbConnectionInterface $db, $dbPrefix, $languageId)
    {
        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
        $this->languageId = (int) $languageId;
    }

    public function generate($categoryId, array $attributeIds, $canonicalAttributeId, $canonicalUnit)
    {
        $categoryId = (int) $categoryId;
        $canonicalAttributeId = (int) $canonicalAttributeId;
        $canonicalUnit = (string) $canonicalUnit;
        $categoryScopeIds = $this->loadCategoryScopeIds($categoryId);
        $rows = $this->loadRows($categoryScopeIds, $attributeIds);
        $proposals = array();
        $unresolved = array();

        foreach ($rows as $row) {
            $rawValue = isset($row['raw_value']) ? (string) $row['raw_value'] : '';
            $normalization = $this->normalizeRawValue($rawValue);

            if ($normalization['accepted']) {
                $proposals[] = array(
                    'product_id' => (int) $row['product_id'],
                    'attribute_id' => (int) $row['attribute_id'],
                    'attribute_name' => (string) $row['attribute_name'],
                    'raw_value' => $rawValue,
                    'normalized_value' => $normalization['normalized_value'],
                    'canonical_unit' => $canonicalUnit,
                    'action' => 'propose_normalized_value',
                    'reason' => $normalization['reason'],
                );
            } else {
                $unresolved[] = array(
                    'product_id' => (int) $row['product_id'],
                    'attribute_id' => (int) $row['attribute_id'],
                    'attribute_name' => (string) $row['attribute_name'],
                    'raw_value' => $rawValue,
                    'reason' => $normalization['reason'],
                );
            }
        }

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'normalization_proposals',
            'category_id' => $categoryId,
            'category_scope_ids' => $categoryScopeIds,
            'attribute_ids' => $attributeIds,
            'canonical_attribute_id' => $canonicalAttributeId,
            'canonical_unit' => $canonicalUnit,
            'proposals' => $proposals,
            'unresolved' => $unresolved,
            'skipped_count' => 0,
            'normalization_proposals_generated' => 1,
            'unresolved_values_reported' => 1,
        );
    }

    private function loadRows(array $categoryScopeIds, array $attributeIds)
    {
        $params = array(':language_id' => $this->languageId);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $attributeIds, $params);

        $sql = 'SELECT DISTINCT pa.product_id, pa.attribute_id, ad.name AS attribute_name, TRIM(pa.text) AS raw_value ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'attribute_description ad ';
        $sql .= 'ON ad.attribute_id = pa.attribute_id AND ad.language_id = pa.language_id ';
        $sql .= 'WHERE pa.language_id = :language_id ';
        $sql .= 'AND pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'ORDER BY pa.product_id ASC, pa.attribute_id ASC, raw_value ASC';

        return $this->db->fetchAll($sql, $params);
    }

    private function normalizeRawValue($rawValue)
    {
        $value = trim((string) $rawValue);

        if ($value === '') {
            return array('accepted' => false, 'reason' => 'empty_value');
        }

        if (preg_match('/^\s*до\b/ui', $value)) {
            return array('accepted' => false, 'reason' => 'textual_upper_bound_unresolved');
        }

        if (preg_match('/[0-9]+(?:[,.][0-9]+)?\s*[-–—]\s*[0-9]+(?:[,.][0-9]+)?/u', $value)) {
            return array('accepted' => false, 'reason' => 'range_value_unresolved');
        }

        preg_match_all('/[0-9]+(?:[,.][0-9]+)?/u', $value, $numberMatches);
        $numberCount = isset($numberMatches[0]) ? count($numberMatches[0]) : 0;

        if ($numberCount === 0) {
            return array('accepted' => false, 'reason' => 'no_numeric_value_unresolved');
        }

        if ($numberCount > 1) {
            return array('accepted' => false, 'reason' => 'ambiguous_multi_number_unresolved');
        }

        if (!preg_match('/^\s*([0-9]+(?:[,.][0-9]+)?)\s*(?:м\.?|m\.?)?\s*$/ui', $value, $matches)) {
            return array('accepted' => false, 'reason' => 'mixed_text_value_unresolved');
        }

        $normalizedValue = $this->normalizeDecimalString($matches[1]);

        return array(
            'accepted' => true,
            'normalized_value' => $normalizedValue,
            'reason' => 'accepted_simple_meter_value',
        );
    }

    private function normalizeDecimalString($value)
    {
        $value = str_replace(',', '.', trim((string) $value));

        if (strpos($value, '.') !== false) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        if ($value === '') {
            return '0';
        }

        return $value;
    }

    private function loadCategoryScopeIds($categoryId)
    {
        $rows = $this->db->fetchAll(
            'SELECT category_id, parent_id FROM ' . $this->dbPrefix . 'category',
            array()
        );
        $childrenByParent = array();
        $knownCategoryIds = array();

        foreach ($rows as $row) {
            $id = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;

            if ($id <= 0) {
                continue;
            }

            $knownCategoryIds[$id] = true;

            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }

            $childrenByParent[$parentId][] = $id;
        }

        if (!isset($knownCategoryIds[(int) $categoryId])) {
            return array((int) $categoryId);
        }

        $scopeIds = array();
        $queue = array((int) $categoryId);
        $seen = array();

        while (count($queue) > 0) {
            $currentId = array_shift($queue);

            if (isset($seen[$currentId])) {
                continue;
            }

            $seen[$currentId] = true;
            $scopeIds[] = $currentId;

            if (!isset($childrenByParent[$currentId])) {
                continue;
            }

            foreach ($childrenByParent[$currentId] as $childId) {
                if (!isset($seen[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        sort($scopeIds);

        return $scopeIds;
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
