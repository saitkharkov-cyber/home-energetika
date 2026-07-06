<?php

namespace FrameworkStandardization\Exporter;

use FrameworkStandardization\Contract\AttributeExporterInterface;
use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\OpenCart\OpenCartTableName;

final class DbReadOnlyAttributeExporter implements AttributeExporterInterface
{
    private $db;
    private $tableName;
    private $runtimeContext;

    public function __construct(ReadOnlyDbConnectionInterface $db, OpenCartTableName $tableName, array $runtimeContext)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        $this->runtimeContext = $runtimeContext;
    }

    public function export(array $canonical, array $scope, array $products)
    {
        try {
            return $this->exportSafely($canonical, $scope, $products);
        } catch (\Exception $e) {
            return $this->failed(array('attribute_export_failed'));
        }
    }

    private function exportSafely(array $canonical, array $scope, array $products)
    {
        $errors = $this->validateInput($canonical, $scope, $products);

        if ($errors !== array()) {
            return $this->failed($errors);
        }

        $targetAttribute = $this->loadTargetAttribute($canonical);

        if ($targetAttribute === array()) {
            return $this->failed(array('target_attribute_id_not_found'));
        }

        $productIds = $this->extractProductIds($products);
        $rows = $this->loadProductAttributes($productIds);

        if ($rows === array()) {
            return $this->failed(array('product_attributes_export_failed'));
        }

        return $this->succeeded($canonical, $targetAttribute, $rows);
    }

    private function validateInput(array $canonical, array $scope, array $products)
    {
        if (!$this->isSupportedRuntimeContext()) {
            return array('attribute_export_failed');
        }

        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return array('canonical_missing');
        }

        if (!isset($canonical['target_attribute_id']) || (int)$canonical['target_attribute_id'] <= 0) {
            return array('target_attribute_id_not_found');
        }

        if (!isset($canonical['target_attribute_group_id']) || (int)$canonical['target_attribute_group_id'] <= 0) {
            return array('target_attribute_id_not_found');
        }

        if (!isset($scope['category_id']) || (int)$scope['category_id'] !== 11900213) {
            return array('attribute_export_failed');
        }

        if ($products === array()) {
            return array('scope_products_empty');
        }

        $scopeProductIds = isset($scope['product_ids']) && is_array($scope['product_ids']) ? $this->normalizeProductIds($scope['product_ids']) : array();

        foreach ($products as $product) {
            if (!isset($product['product_id']) || (int)$product['product_id'] <= 0) {
                return array('product_attributes_export_failed');
            }

            if ($scopeProductIds !== array() && !in_array((int)$product['product_id'], $scopeProductIds, true)) {
                return array('product_outside_scope');
            }
        }

        return array();
    }

    private function isSupportedRuntimeContext()
    {
        if (!isset($this->runtimeContext['language_id']) || (int)$this->runtimeContext['language_id'] !== 1) {
            return false;
        }

        if (!isset($this->runtimeContext['source']) || $this->runtimeContext['source'] !== 'local_dump_db_readonly') {
            return false;
        }

        return true;
    }

    private function loadTargetAttribute(array $canonical)
    {
        $sql = "SELECT";
        $sql .= " a.attribute_id,";
        $sql .= " a.attribute_group_id,";
        $sql .= " ad.name AS attribute_name,";
        $sql .= " agd.name AS attribute_group_name";
        $sql .= " FROM " . $this->tableName->name('attribute') . " a";
        $sql .= " JOIN " . $this->tableName->name('attribute_description') . " ad ON ad.attribute_id = a.attribute_id";
        $sql .= " JOIN " . $this->tableName->name('attribute_group_description') . " agd ON agd.attribute_group_id = a.attribute_group_id";
        $sql .= " WHERE a.attribute_id = :attribute_id";
        $sql .= " AND a.attribute_group_id = :attribute_group_id";
        $sql .= " AND ad.language_id = :language_id";
        $sql .= " AND agd.language_id = :language_id";

        return $this->db->fetchOne($sql, array(
            ':attribute_id' => (int)$canonical['target_attribute_id'],
            ':attribute_group_id' => (int)$canonical['target_attribute_group_id'],
            ':language_id' => $this->getLanguageId(),
        ));
    }

    private function loadProductAttributes(array $productIds)
    {
        $params = array(
            ':language_id' => $this->getLanguageId(),
        );
        $placeholders = array();

        foreach ($productIds as $index => $productId) {
            $placeholder = ':product_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = (int)$productId;
        }

        $sql = "SELECT";
        $sql .= " pa.product_id,";
        $sql .= " pa.attribute_id,";
        $sql .= " pa.language_id,";
        $sql .= " pa.text,";
        $sql .= " a.attribute_group_id,";
        $sql .= " ad.name AS attribute_name,";
        $sql .= " agd.name AS attribute_group_name";
        $sql .= " FROM " . $this->tableName->name('product_attribute') . " pa";
        $sql .= " JOIN " . $this->tableName->name('attribute') . " a ON a.attribute_id = pa.attribute_id";
        $sql .= " JOIN " . $this->tableName->name('attribute_description') . " ad ON ad.attribute_id = pa.attribute_id";
        $sql .= " JOIN " . $this->tableName->name('attribute_group_description') . " agd ON agd.attribute_group_id = a.attribute_group_id";
        $sql .= " WHERE pa.language_id = :language_id";
        $sql .= " AND ad.language_id = :language_id";
        $sql .= " AND agd.language_id = :language_id";
        $sql .= " AND pa.product_id IN (" . implode(', ', $placeholders) . ")";
        $sql .= " ORDER BY pa.attribute_id, pa.product_id";

        return $this->db->fetchAll($sql, $params);
    }

    private function succeeded(array $canonical, array $targetAttribute, array $rows)
    {
        $attributesById = array();
        $attributeGroupsById = array();
        $productAttributes = array();
        $rawValues = array();
        $targetAttributeId = (int)$canonical['target_attribute_id'];

        foreach ($rows as $row) {
            $attributeId = (int)$row['attribute_id'];
            $attributeGroupId = (int)$row['attribute_group_id'];
            $text = isset($row['text']) ? (string)$row['text'] : '';

            if ($attributeId <= 0) {
                return $this->failed(array('product_attributes_export_failed'));
            }

            if (!isset($attributesById[$attributeId])) {
                $attributesById[$attributeId] = array(
                    'attribute_id' => $attributeId,
                    'attribute_name' => isset($row['attribute_name']) ? (string)$row['attribute_name'] : '',
                    'attribute_group_id' => $attributeGroupId,
                    'attribute_group_name' => isset($row['attribute_group_name']) ? (string)$row['attribute_group_name'] : '',
                    'usage_count' => 0,
                    'sample_values' => array(),
                    'source' => $this->getSource(),
                );
            }

            $attributesById[$attributeId]['usage_count']++;
            $this->addSampleValue($attributesById[$attributeId]['sample_values'], $text);

            if (!isset($attributeGroupsById[$attributeGroupId])) {
                $attributeGroupsById[$attributeGroupId] = array(
                    'attribute_group_id' => $attributeGroupId,
                    'attribute_group_name' => isset($row['attribute_group_name']) ? (string)$row['attribute_group_name'] : '',
                    'source' => $this->getSource(),
                );
            }

            $productAttribute = array(
                'product_id' => (int)$row['product_id'],
                'attribute_id' => $attributeId,
                'language_id' => (int)$row['language_id'],
                'text' => $text,
                'source' => $this->getSource(),
            );

            $productAttributes[] = $productAttribute;

            if ($attributeId === $targetAttributeId) {
                $rawValues[] = array(
                    'product_id' => (int)$row['product_id'],
                    'attribute_id' => $attributeId,
                    'raw_text' => $text,
                    'language_id' => (int)$row['language_id'],
                    'source' => $this->getSource(),
                );
            }
        }

        if ($rawValues === array()) {
            return $this->failed(array('product_attributes_export_failed'));
        }

        return array(
            'exported' => 1,
            'attributes' => array_values($attributesById),
            'attribute_groups' => array_values($attributeGroupsById),
            'product_attributes' => $productAttributes,
            'target_attribute' => array(
                'attribute_id' => (int)$targetAttribute['attribute_id'],
                'attribute_name' => isset($targetAttribute['attribute_name']) ? (string)$targetAttribute['attribute_name'] : '',
                'attribute_group_id' => (int)$targetAttribute['attribute_group_id'],
                'attribute_group_name' => isset($targetAttribute['attribute_group_name']) ? (string)$targetAttribute['attribute_group_name'] : '',
                'source' => $this->getSource(),
            ),
            'found_attributes' => array_values($attributesById),
            'raw_values' => $rawValues,
            'errors' => array(),
            'warnings' => array(),
            'source' => $this->getSource(),
        );
    }

    private function addSampleValue(array &$sampleValues, $text)
    {
        if ($text === '') {
            return;
        }

        if (in_array($text, $sampleValues, true)) {
            return;
        }

        if (count($sampleValues) >= $this->getMaxSampleValues()) {
            return;
        }

        $sampleValues[] = $text;
    }

    private function extractProductIds(array $products)
    {
        $productIds = array();

        foreach ($products as $product) {
            $productId = (int)$product['product_id'];

            if (!in_array($productId, $productIds, true)) {
                $productIds[] = $productId;
            }
        }

        return $productIds;
    }

    private function normalizeProductIds(array $productIds)
    {
        $normalized = array();

        foreach ($productIds as $productId) {
            $normalized[] = (int)$productId;
        }

        return $normalized;
    }

    private function getLanguageId()
    {
        return (int)$this->runtimeContext['language_id'];
    }

    private function getMaxSampleValues()
    {
        if (isset($this->runtimeContext['max_sample_values']) && (int)$this->runtimeContext['max_sample_values'] > 0) {
            return (int)$this->runtimeContext['max_sample_values'];
        }

        return 20;
    }

    private function getSource()
    {
        return isset($this->runtimeContext['source']) ? (string)$this->runtimeContext['source'] : 'local_dump_db_readonly';
    }

    private function failed(array $errors)
    {
        return array(
            'exported' => 0,
            'attributes' => array(),
            'attribute_groups' => array(),
            'product_attributes' => array(),
            'target_attribute' => array(),
            'found_attributes' => array(),
            'raw_values' => array(),
            'errors' => $errors,
            'warnings' => array(),
            'source' => $this->getSource(),
        );
    }
}
