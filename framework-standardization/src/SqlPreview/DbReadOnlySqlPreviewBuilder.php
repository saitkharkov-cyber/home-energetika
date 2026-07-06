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
