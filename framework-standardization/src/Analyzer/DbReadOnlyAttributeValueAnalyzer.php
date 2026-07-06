<?php

namespace FrameworkStandardization\Analyzer;

use FrameworkStandardization\Contract\AttributeValueAnalyzerInterface;

final class DbReadOnlyAttributeValueAnalyzer implements AttributeValueAnalyzerInterface
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

        $emptyValues = array();

        foreach ($rawValues as $rawValue) {
            if (!isset($rawValue['product_id']) || (int)$rawValue['product_id'] <= 0) {
                return $this->failed(array('value_analysis_failed'));
            }

            if (!isset($rawValue['attribute_id']) || (int)$rawValue['attribute_id'] <= 0) {
                return $this->failed(array('value_analysis_failed'));
            }

            if (!isset($rawValue['raw_text']) || trim((string)$rawValue['raw_text']) === '') {
                $emptyValues[] = $rawValue;
            }
        }

        $attributeValueStructure = array(
            'raw_values' => $rawValues,
            'normalized_values' => array(),
            'unknown_values' => array(),
            'invalid_values' => array(),
            'empty_values' => $emptyValues,
            'diagnostics' => array(
                'total_values' => count($rawValues),
                'normalized_count' => 0,
                'unknown_count' => 0,
                'invalid_count' => 0,
                'empty_count' => count($emptyValues),
                'unique_normalized_values' => array(),
                'source' => 'local_dump_db_readonly',
            ),
        );

        return array(
            'analyzed' => 1,
            'attribute_value_structure' => $attributeValueStructure,
            'value_report' => array(
                'parser' => 'diameter_mm',
                'value_type' => isset($valueRules['value_type']) ? $valueRules['value_type'] : 'decimal',
                'unit' => isset($valueRules['unit']) ? $valueRules['unit'] : 'mm',
                'total_values' => count($rawValues),
                'normalized_values' => 0,
                'unknown_values' => 0,
                'invalid_values' => 0,
                'empty_values' => count($emptyValues),
                'examples' => $this->buildExamples($rawValues),
                'note' => 'db_readonly_values_not_normalized',
            ),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }

    private function buildExamples(array $rawValues)
    {
        $examples = array();

        foreach ($rawValues as $rawValue) {
            if (count($examples) >= 5) {
                break;
            }

            $examples[] = array(
                'product_id' => isset($rawValue['product_id']) ? (int)$rawValue['product_id'] : 0,
                'attribute_id' => isset($rawValue['attribute_id']) ? (int)$rawValue['attribute_id'] : 0,
                'raw_text' => isset($rawValue['raw_text']) ? (string)$rawValue['raw_text'] : '',
            );
        }

        return $examples;
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
            'source' => 'local_dump_db_readonly',
        );
    }
}
