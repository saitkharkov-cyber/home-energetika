<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\AttributeValueAnalyzerInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class AnalyzeValuesStage implements StageInterface
{
    private $analyzer;

    public function __construct(AttributeValueAnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function getName()
    {
        return 'analyze_values';
    }

    public function run(AttributeContext $context)
    {
        $canonical = $context->getCanonical();
        $attributeValueStructure = $context->getAttributeValueStructure();
        $rawValues = isset($attributeValueStructure['raw_values']) && is_array($attributeValueStructure['raw_values']) ? $attributeValueStructure['raw_values'] : array();
        $rawJob = $context->getJob()->getRawJob();
        $valueRules = isset($rawJob['value_rules']) && is_array($rawJob['value_rules']) ? $rawJob['value_rules'] : array();
        $result = $this->analyzer->analyze($canonical, $rawValues, $valueRules);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $resolvedValueStructure = isset($result['attribute_value_structure']) && is_array($result['attribute_value_structure']) ? $result['attribute_value_structure'] : array();
        $diagnostics = isset($resolvedValueStructure['diagnostics']) && is_array($resolvedValueStructure['diagnostics']) ? $resolvedValueStructure['diagnostics'] : array();
        $summary = array(
            'canonical_code' => isset($canonical['canonical_code']) ? $canonical['canonical_code'] : '',
            'parser' => isset($valueRules['value_parser']) ? $valueRules['value_parser'] : '',
            'total_values' => isset($diagnostics['total_values']) ? $diagnostics['total_values'] : 0,
            'normalized_count' => isset($diagnostics['normalized_count']) ? $diagnostics['normalized_count'] : 0,
            'unknown_count' => isset($diagnostics['unknown_count']) ? $diagnostics['unknown_count'] : 0,
            'invalid_count' => isset($diagnostics['invalid_count']) ? $diagnostics['invalid_count'] : 0,
            'empty_count' => isset($diagnostics['empty_count']) ? $diagnostics['empty_count'] : 0,
            'source' => isset($result['source']) ? $result['source'] : 'unknown',
        );

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setAttributeValueStructure($resolvedValueStructure);
        $context->setValueReport(isset($result['value_report']) && is_array($result['value_report']) ? $result['value_report'] : array());
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }
}
