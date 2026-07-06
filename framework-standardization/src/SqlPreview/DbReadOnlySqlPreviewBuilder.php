<?php

namespace FrameworkStandardization\SqlPreview;

use FrameworkStandardization\Contract\SqlPreviewBuilderInterface;

final class DbReadOnlySqlPreviewBuilder implements SqlPreviewBuilderInterface
{
    public function build(array $canonical, array $scope, array $attributeNameStructure, array $synonymCandidates, array $attributeValueStructure, array $valueReport, array $outputRules, array $valueRules)
    {
        $applyChanges = isset($outputRules['apply_changes']) ? (int)$outputRules['apply_changes'] : 0;

        if ($applyChanges !== 0) {
            return $this->failed(array('apply_changes_not_allowed'), $applyChanges);
        }

        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        if (!isset($scope['category_id']) || (int)$scope['category_id'] !== 11900213) {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        $rawValues = isset($attributeValueStructure['raw_values']) && is_array($attributeValueStructure['raw_values']) ? $attributeValueStructure['raw_values'] : array();
        $normalizedValues = isset($attributeValueStructure['normalized_values']) && is_array($attributeValueStructure['normalized_values']) ? $attributeValueStructure['normalized_values'] : array();
        $diagnostics = isset($attributeValueStructure['diagnostics']) && is_array($attributeValueStructure['diagnostics']) ? $attributeValueStructure['diagnostics'] : array();
        $rawProfile = isset($diagnostics['raw_profile']) && is_array($diagnostics['raw_profile']) ? $diagnostics['raw_profile'] : array();
        $topRawValues = isset($rawProfile['top_raw_values']) && is_array($rawProfile['top_raw_values']) ? $rawProfile['top_raw_values'] : array();

        $sqlPreview = array(
            'enabled' => 1,
            'generated' => 0,
            'safe_to_apply' => 0,
            'apply_changes' => 0,
            'source' => 'local_dump_db_readonly',
            'mode' => 'preview_only',
            'blocked_by' => array('db_readonly_sql_preview_not_implemented'),
            'statements' => array(),
            'operations' => array(
                'would_create' => array(),
                'would_update' => array(),
                'skipped' => array(),
                'blocked' => array(),
            ),
            'diagnostics' => array(
                'raw_value_count' => count($rawValues),
                'normalized_count' => count($normalizedValues),
                'unknown_count' => $this->countValues($attributeValueStructure, 'unknown_values'),
                'invalid_count' => $this->countValues($attributeValueStructure, 'invalid_values'),
                'empty_count' => $this->countValues($attributeValueStructure, 'empty_values'),
                'raw_profile_present' => $rawProfile !== array() ? 1 : 0,
                'raw_profile_total_values' => isset($rawProfile['total_values']) ? (int)$rawProfile['total_values'] : 0,
                'unique_raw_values_count' => isset($rawProfile['unique_raw_values_count']) ? (int)$rawProfile['unique_raw_values_count'] : 0,
                'empty_values_count' => isset($rawProfile['empty_values_count']) ? (int)$rawProfile['empty_values_count'] : 0,
                'suspicious_no_digits_count' => isset($rawProfile['suspicious_no_digits_count']) ? (int)$rawProfile['suspicious_no_digits_count'] : 0,
                'suspicious_long_value_count' => isset($rawProfile['suspicious_long_value_count']) ? (int)$rawProfile['suspicious_long_value_count'] : 0,
                'suspicious_multiple_numbers_count' => isset($rawProfile['suspicious_multiple_numbers_count']) ? (int)$rawProfile['suspicious_multiple_numbers_count'] : 0,
                'top_raw_values_count' => count($topRawValues),
                'raw_profile_source' => isset($rawProfile['source']) ? (string)$rawProfile['source'] : '',
                'source' => 'local_dump_db_readonly',
            ),
        );

        return array(
            'built' => 1,
            'sql_preview' => $sqlPreview,
            'errors' => array(),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }

    private function countValues(array $attributeValueStructure, $key)
    {
        return isset($attributeValueStructure[$key]) && is_array($attributeValueStructure[$key]) ? count($attributeValueStructure[$key]) : 0;
    }

    private function failed(array $errors, $applyChanges)
    {
        return array(
            'built' => 0,
            'sql_preview' => array(
                'enabled' => 1,
                'generated' => 0,
                'safe_to_apply' => 0,
                'apply_changes' => (int)$applyChanges,
                'source' => 'local_dump_db_readonly',
                'mode' => 'preview_only',
                'blocked_by' => array(),
                'statements' => array(),
                'operations' => array(
                    'would_create' => array(),
                    'would_update' => array(),
                    'skipped' => array(),
                    'blocked' => array(),
                ),
                'diagnostics' => array(
                    'source' => 'local_dump_db_readonly',
                ),
            ),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }
}
