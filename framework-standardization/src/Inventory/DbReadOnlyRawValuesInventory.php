<?php

namespace FrameworkStandardization\Inventory;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;

final class DbReadOnlyRawValuesInventory
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

    public function inventory($categoryId, array $attributeIds)
    {
        $categoryId = (int) $categoryId;
        $categoryScopeIds = $this->loadCategoryScopeIds($categoryId);
        $attributes = array();

        foreach ($attributeIds as $attributeId) {
            $attributeId = (int) $attributeId;
            $attribute = $this->loadAttribute($attributeId);
            $rawValues = $this->loadRawValues($attributeId, $categoryScopeIds);
            $productsWithAttributeCount = $this->loadProductsWithAttributeCount($attributeId, $categoryScopeIds);

            $attributes[] = array(
                'attribute_id' => $attributeId,
                'attribute_name' => isset($attribute['attribute_name']) ? $attribute['attribute_name'] : '',
                'attribute_group_name' => isset($attribute['attribute_group_name']) ? $attribute['attribute_group_name'] : '',
                'products_with_attribute_count' => $productsWithAttributeCount,
                'distinct_raw_values_count' => count($rawValues),
                'raw_values' => $rawValues,
            );
        }

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'raw_values_inventory',
            'category_id' => $categoryId,
            'category_scope_ids' => $categoryScopeIds,
            'attribute_ids' => $attributeIds,
            'attributes' => $attributes,
            'raw_values_inventory_completed' => 1,
        );
    }

    private function loadAttribute($attributeId)
    {
        $sql = 'SELECT ad.attribute_id, ad.name AS attribute_name, ';
        $sql .= 'COALESCE(agd.name, \'\') AS attribute_group_name ';
        $sql .= 'FROM ' . $this->dbPrefix . 'attribute_description ad ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute a ON a.attribute_id = ad.attribute_id ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute_group_description agd ';
        $sql .= 'ON agd.attribute_group_id = a.attribute_group_id AND agd.language_id = ad.language_id ';
        $sql .= 'WHERE ad.attribute_id = :attribute_id AND ad.language_id = :language_id';

        return $this->db->fetchOne($sql, array(
            ':attribute_id' => (int) $attributeId,
            ':language_id' => $this->languageId,
        ));
    }

    private function loadProductsWithAttributeCount($attributeId, array $categoryScopeIds)
    {
        $params = array(
            ':attribute_id' => (int) $attributeId,
            ':language_id' => $this->languageId,
        );
        $placeholders = $this->buildCategoryPlaceholders($categoryScopeIds, $params);

        $sql = 'SELECT COUNT(DISTINCT pa.product_id) AS products_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $placeholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id AND pa.language_id = :language_id';

        $row = $this->db->fetchOne($sql, $params);

        return isset($row['products_count']) ? (int) $row['products_count'] : 0;
    }

    private function loadRawValues($attributeId, array $categoryScopeIds)
    {
        $params = array(
            ':attribute_id' => (int) $attributeId,
            ':language_id' => $this->languageId,
        );
        $placeholders = $this->buildCategoryPlaceholders($categoryScopeIds, $params);

        $sql = 'SELECT TRIM(pa.text) AS raw_value, COUNT(DISTINCT pa.product_id) AS products_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $placeholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id AND pa.language_id = :language_id ';
        $sql .= 'GROUP BY TRIM(pa.text) ';
        $sql .= 'ORDER BY products_count DESC, raw_value ASC';

        $rows = $this->db->fetchAll($sql, $params);
        $rawValues = array();

        foreach ($rows as $row) {
            $rawValue = isset($row['raw_value']) ? (string) $row['raw_value'] : '';
            $samples = $this->loadSamples($attributeId, $categoryScopeIds, $rawValue);

            $rawValues[] = array(
                'raw_value' => $rawValue,
                'count' => isset($row['products_count']) ? (int) $row['products_count'] : 0,
                'sample_product_ids' => $samples['product_ids'],
                'sample_product_names' => $samples['product_names'],
                'warnings' => $this->detectWarnings($rawValue),
            );
        }

        return $rawValues;
    }

    private function loadSamples($attributeId, array $categoryScopeIds, $rawValue)
    {
        $params = array(
            ':attribute_id' => (int) $attributeId,
            ':language_id' => $this->languageId,
            ':raw_value' => (string) $rawValue,
        );
        $placeholders = $this->buildCategoryPlaceholders($categoryScopeIds, $params);

        $sql = 'SELECT DISTINCT pa.product_id, COALESCE(pd.name, \'\') AS product_name ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $placeholders) . ') ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'product_description pd ';
        $sql .= 'ON pd.product_id = pa.product_id AND pd.language_id = pa.language_id ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id ';
        $sql .= 'AND pa.language_id = :language_id ';
        $sql .= 'AND TRIM(pa.text) = :raw_value ';
        $sql .= 'ORDER BY pa.product_id ASC ';
        $sql .= 'LIMIT 3';

        $rows = $this->db->fetchAll($sql, $params);
        $productIds = array();
        $productNames = array();

        foreach ($rows as $row) {
            if (isset($row['product_id'])) {
                $productIds[] = (string) $row['product_id'];
            }

            if (isset($row['product_name']) && $row['product_name'] !== '') {
                $productNames[] = (string) $row['product_name'];
            }
        }

        return array(
            'product_ids' => $productIds,
            'product_names' => $productNames,
        );
    }

    private function detectWarnings($rawValue)
    {
        $rawValue = trim((string) $rawValue);
        $warnings = array();

        if ($rawValue === '') {
            $warnings[] = 'empty_value';
            return $warnings;
        }

        if (!preg_match('/[0-9]/', $rawValue)) {
            $warnings[] = 'no_numeric_value';
        }

        preg_match_all('/[0-9]+(?:[,.][0-9]+)?/u', $rawValue, $numberMatches);
        $numberCount = isset($numberMatches[0]) ? count($numberMatches[0]) : 0;

        if ($numberCount > 1) {
            $warnings[] = 'multiple_numbers';
        }

        if (preg_match('/(\s-\s|–|—|\bот\b|\bдо\b)/ui', $rawValue)) {
            $warnings[] = 'range_value';
        }

        if (preg_match('/(м\.?|m)(\s|\.|$)/ui', $rawValue)) {
            $warnings[] = 'contains_unit_m';
        }

        if (preg_match('/вод\.?\s*ст\.?/ui', $rawValue)) {
            $warnings[] = 'contains_unit_m_water_column';
        }

        if ($numberCount > 0) {
            foreach ($numberMatches[0] as $numberMatch) {
                $number = (float) str_replace(',', '.', $numberMatch);

                if ($number > 500) {
                    $warnings[] = 'suspicious_large_value';
                    break;
                }
            }
        }

        $withoutExpectedUnits = preg_replace('/(м\.?|m|вод\.?\s*ст\.?)/ui', '', $rawValue);

        if (preg_match('/[A-Za-zА-Яа-яЁёІіЇїЄєҐґ]/u', $withoutExpectedUnits)) {
            $warnings[] = 'mixed_text_value';
        }

        return array_values(array_unique($warnings));
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

    private function buildCategoryPlaceholders(array $categoryIds, array &$params)
    {
        $placeholders = array();
        $index = 0;

        foreach ($categoryIds as $categoryId) {
            $key = ':category_id_' . $index;
            $params[$key] = (int) $categoryId;
            $placeholders[] = $key;
            $index++;
        }

        if (count($placeholders) === 0) {
            $key = ':category_id_empty';
            $params[$key] = 0;
            $placeholders[] = $key;
        }

        return $placeholders;
    }
}
