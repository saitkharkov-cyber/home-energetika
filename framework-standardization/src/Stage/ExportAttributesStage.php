<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\AttributeExporterInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class ExportAttributesStage implements StageInterface
{
    private $exporter;

    public function __construct(AttributeExporterInterface $exporter)
    {
        $this->exporter = $exporter;
    }

    public function getName()
    {
        return 'export_attributes';
    }

    public function run(AttributeContext $context)
    {
        $canonical = $context->getCanonical();
        $scope = $context->getScope();
        $rawData = $context->getRawData();
        $products = isset($rawData['products']) && is_array($rawData['products']) ? $rawData['products'] : array();
        $result = $this->exporter->export($canonical, $scope, $products);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $attributes = isset($result['attributes']) && is_array($result['attributes']) ? $result['attributes'] : array();
        $attributeGroups = isset($result['attribute_groups']) && is_array($result['attribute_groups']) ? $result['attribute_groups'] : array();
        $productAttributes = isset($result['product_attributes']) && is_array($result['product_attributes']) ? $result['product_attributes'] : array();
        $rawValues = isset($result['raw_values']) && is_array($result['raw_values']) ? $result['raw_values'] : array();
        $summary = array(
            'canonical_code' => isset($canonical['canonical_code']) ? $canonical['canonical_code'] : '',
            'scope_type' => isset($scope['type']) ? $scope['type'] : '',
            'category_id' => isset($scope['category_id']) ? $scope['category_id'] : '',
            'product_count' => count($products),
            'attribute_count' => count($attributes),
            'product_attribute_count' => count($productAttributes),
            'raw_value_count' => count($rawValues),
            'source' => isset($result['source']) ? $result['source'] : 'unknown',
        );

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setRawDataAttributes($attributes);
        $context->setRawDataAttributeGroups($attributeGroups);
        $context->setRawDataProductAttributes($productAttributes);
        $context->setAttributeNameStructure(array(
            'target_attribute' => isset($result['target_attribute']) && is_array($result['target_attribute']) ? $result['target_attribute'] : array(),
            'found_attributes' => isset($result['found_attributes']) && is_array($result['found_attributes']) ? $result['found_attributes'] : array(),
        ));
        $context->setAttributeValueRawValues($rawValues);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }
}
