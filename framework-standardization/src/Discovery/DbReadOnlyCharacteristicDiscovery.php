<?php

namespace FrameworkStandardization\Discovery;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;

final class DbReadOnlyCharacteristicDiscovery
{
    private $db;
    private $dbPrefix;
    private $languageId;

    public function __construct(ReadOnlyDbConnectionInterface $db, $dbPrefix, $languageId)
    {
        if (!is_string($dbPrefix) || preg_match('/^[A-Za-z0-9_]*$/', $dbPrefix) !== 1) {
            throw new \InvalidArgumentException('characteristic_discovery_db_prefix_invalid');
        }

        if (!is_int($languageId) || $languageId <= 0) {
            throw new \InvalidArgumentException('characteristic_discovery_language_id_invalid');
        }

        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
        $this->languageId = $languageId;
    }

    public function discover(array $scope)
    {
        $validatedScope = $this->validateScope($scope);
        $params = array(
            ':root_category_id' => $validatedScope['root_category_id'],
            ':language_id' => $this->languageId,
        );

        $sql = 'SELECT pa.attribute_id AS attribute_id, ';
        $sql .= 'ad.name AS attribute_name, ';
        $sql .= 'COALESCE(agd.name, \'\') AS attribute_group_name, ';
        $sql .= 'COUNT(*) AS usage_count, ';
        $sql .= 'COUNT(DISTINCT pa.product_id) AS distinct_products ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'attribute_description ad ';
        $sql .= 'ON ad.attribute_id = pa.attribute_id AND ad.language_id = pa.language_id ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'attribute a ';
        $sql .= 'ON a.attribute_id = pa.attribute_id ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute_group_description agd ';
        $sql .= 'ON agd.attribute_group_id = a.attribute_group_id AND agd.language_id = pa.language_id ';
        $sql .= 'WHERE pa.language_id = :language_id ';
        $sql .= 'AND EXISTS (';
        $sql .= 'SELECT 1 ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_to_category scope_p2c ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'category_path scope_cp ';
        $sql .= 'ON scope_cp.category_id = scope_p2c.category_id ';
        $sql .= 'AND scope_cp.path_id = :root_category_id ';
        $sql .= 'WHERE scope_p2c.product_id = pa.product_id';
        $sql .= ') ';
        $sql .= 'GROUP BY pa.attribute_id, ad.name, COALESCE(agd.name, \'\')';

        $rows = $this->db->fetchAll($sql, $params);
        $result = $this->buildRows($rows);

        usort($result, array($this, 'compareRows'));

        return $result;
    }

    private function validateScope(array $scope)
    {
        if (!isset($scope['root_category_id']) || !is_int($scope['root_category_id']) || $scope['root_category_id'] <= 0) {
            throw new \InvalidArgumentException('characteristic_discovery_root_category_id_invalid');
        }

        if (!isset($scope['scope_mode']) || !is_string($scope['scope_mode']) || trim($scope['scope_mode']) === '') {
            throw new \InvalidArgumentException('characteristic_discovery_scope_mode_invalid');
        }

        $scopeMode = trim($scope['scope_mode']);
        if ($scopeMode !== 'hierarchical_category_path_exists') {
            throw new \InvalidArgumentException('characteristic_discovery_scope_mode_unsupported');
        }

        return array(
            'root_category_id' => $scope['root_category_id'],
            'scope_mode' => $scopeMode,
        );
    }

    private function buildRows(array $dbRows)
    {
        $result = array();
        $seenAttributeIds = array();

        foreach ($dbRows as $dbRow) {
            if (!is_array($dbRow)) {
                throw new \InvalidArgumentException('characteristic_discovery_attribute_id_invalid');
            }

            $attributeId = $this->requirePositiveInteger($dbRow, 'attribute_id', 'characteristic_discovery_attribute_id_invalid');
            if (isset($seenAttributeIds[$attributeId])) {
                throw new \InvalidArgumentException('characteristic_discovery_duplicate_attribute_id');
            }
            $seenAttributeIds[$attributeId] = true;

            if (!isset($dbRow['attribute_name']) || !is_string($dbRow['attribute_name']) || trim($dbRow['attribute_name']) === '') {
                throw new \InvalidArgumentException('characteristic_discovery_attribute_name_required');
            }

            $usageCount = $this->requireNonNegativeInteger($dbRow, 'usage_count', 'characteristic_discovery_usage_count_invalid');
            $distinctProducts = $this->requireNonNegativeInteger($dbRow, 'distinct_products', 'characteristic_discovery_distinct_products_invalid');

            $result[] = array(
                'attribute_id' => $attributeId,
                'attribute_name' => trim($dbRow['attribute_name']),
                'attribute_group_name' => isset($dbRow['attribute_group_name']) && $dbRow['attribute_group_name'] !== null
                    ? (string) $dbRow['attribute_group_name']
                    : '',
                'usage_count' => $usageCount,
                'distinct_products' => $distinctProducts,
            );
        }

        return $result;
    }

    private function requirePositiveInteger(array $row, $key, $error)
    {
        if (!array_key_exists($key, $row)) {
            throw new \InvalidArgumentException($error);
        }

        $value = (int) $row[$key];
        if ($value <= 0) {
            throw new \InvalidArgumentException($error);
        }

        return $value;
    }

    private function requireNonNegativeInteger(array $row, $key, $error)
    {
        if (!array_key_exists($key, $row)) {
            throw new \InvalidArgumentException($error);
        }

        $value = (int) $row[$key];
        if ($value < 0) {
            throw new \InvalidArgumentException($error);
        }

        return $value;
    }

    private function compareRows(array $left, array $right)
    {
        $nameCompare = strcmp($left['attribute_name'], $right['attribute_name']);
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        if ($left['attribute_id'] === $right['attribute_id']) {
            return 0;
        }

        return $left['attribute_id'] < $right['attribute_id'] ? -1 : 1;
    }
}
