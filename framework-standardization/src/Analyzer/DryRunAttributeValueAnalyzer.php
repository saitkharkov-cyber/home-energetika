<?php

namespace FrameworkStandardization\Analyzer;

use FrameworkStandardization\Contract\AttributeValueAnalyzerInterface;

final class DryRunAttributeValueAnalyzer implements AttributeValueAnalyzerInterface
{
    public function analyze(array $canonical, array $rawValues, array $valueRules)
    {
        if ($rawValues === array()) {
            return $this->failed(array('raw_values_missing'));
        }

        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('value_analysis_failed'));
        }

        if (!isset($valueRules['value_parser']) || $valueRules['value_parser'] !== 'diameter_mm') {
            return $this->failed(array('value_parser_unknown'));
        }

        if (!isset($rawValues[0]['raw_text']) || $rawValues[0]['raw_text'] !== '96 мм') {
            return $this->failed(array('value_analysis_failed'));
        }

        $rawValue = array(
            'product_id' => 0,
            'attribute_id' => 0,
            'raw_text' => '96 мм',
            'language_id' => 3,
            'source' => 'dry_run_fixture',
        );

        $normalizedValue = array(
            'product_id' => 0,
            'attribute_id' => 0,
            'raw_text' => '96 мм',
            'normalized_value' => 96,
            'unit' => 'mm',
            'parser' => 'diameter_mm',
            'source' => 'dry_run_fixture',
        );

        $attributeValueStructure = array(
            'raw_values' => array($rawValue),
            'normalized_values' => array($normalizedValue),
            'unknown_values' => array(),
            'invalid_values' => array(),
            'empty_values' => array(),
            'diagnostics' => array(
                'total_values' => 1,
                'normalized_count' => 1,
                'unknown_count' => 0,
                'invalid_count' => 0,
                'empty_count' => 0,
                'unique_normalized_values' => array(96),
                'source' => 'dry_run_fixture',
            ),
        );

        return array(
            'analyzed' => 1,
            'attribute_value_structure' => $attributeValueStructure,
            'value_report' => array(
                'parser' => 'diameter_mm',
                'value_type' => isset($valueRules['value_type']) ? $valueRules['value_type'] : 'decimal',
                'unit' => 'mm',
                'total_values' => 1,
                'normalized_values' => 1,
                'unknown_values' => 0,
                'invalid_values' => 0,
                'empty_values' => 0,
                'examples' => array(
                    array(
                        'raw_text' => '96 мм',
                        'normalized_value' => 96,
                        'unit' => 'mm',
                    ),
                ),
            ),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }

    private function failed(array $errors)
    {
        return array(
            'analyzed' => 0,
            'attribute_value_structure' => array(
                'raw_values' => array(),
                'normalized_values' => array(),
                'unknown_values' => array(),
                'invalid_values' => array(),
                'empty_values' => array(),
                'diagnostics' => array(),
            ),
            'value_report' => array(),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }
}
