<?php

namespace FrameworkStandardization\Exporter;

use FrameworkStandardization\Contract\AttributeExporterInterface;

final class DryRunAttributeExporter implements AttributeExporterInterface
{
    public function export(array $canonical, array $scope, array $products)
    {
        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('canonical_missing'));
        }

        if (!isset($scope['category_id']) || (int)$scope['category_id'] !== 11900213) {
            return $this->failed(array('attribute_export_failed'));
        }

        if ($products === array()) {
            return $this->failed(array('scope_products_empty'));
        }

        if (!isset($products[0]['product_id']) || (int)$products[0]['product_id'] !== 0) {
            return $this->failed(array('product_attributes_export_failed'));
        }

        $attribute = array(
            'attribute_id' => 0,
            'attribute_name' => 'Dry-run pump diameter',
            'attribute_group_id' => 0,
            'attribute_group_name' => 'Dry-run attributes',
            'usage_count' => 1,
            'sample_values' => array('96 мм'),
            'source' => 'dry_run_fixture',
        );

        $attributeGroup = array(
            'attribute_group_id' => 0,
            'attribute_group_name' => 'Dry-run attributes',
            'source' => 'dry_run_fixture',
        );

        $productAttribute = array(
            'product_id' => 0,
            'attribute_id' => 0,
            'language_id' => 3,
            'text' => '96 мм',
            'source' => 'dry_run_fixture',
        );

        $targetAttribute = array(
            'attribute_id' => 0,
            'attribute_name' => 'Dry-run pump diameter',
            'attribute_group_id' => 0,
            'attribute_group_name' => 'Dry-run attributes',
            'source' => 'dry_run_fixture',
        );

        $rawValue = array(
            'product_id' => 0,
            'attribute_id' => 0,
            'raw_text' => '96 мм',
            'language_id' => 3,
            'source' => 'dry_run_fixture',
        );

        return array(
            'exported' => 1,
            'attributes' => array($attribute),
            'attribute_groups' => array($attributeGroup),
            'product_attributes' => array($productAttribute),
            'target_attribute' => $targetAttribute,
            'found_attributes' => array($attribute),
            'raw_values' => array($rawValue),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
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
            'source' => 'dry_run_fixture',
        );
    }
}
