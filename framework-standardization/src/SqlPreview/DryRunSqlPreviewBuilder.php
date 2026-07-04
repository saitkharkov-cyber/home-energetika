<?php

namespace FrameworkStandardization\SqlPreview;

use FrameworkStandardization\Contract\SqlPreviewBuilderInterface;

final class DryRunSqlPreviewBuilder implements SqlPreviewBuilderInterface
{
    public function build(array $canonical, array $scope, array $attributeNameStructure, array $synonymCandidates, array $attributeValueStructure, array $valueReport, array $outputRules, array $valueRules)
    {
        $applyChanges = isset($outputRules['apply_changes']) ? $outputRules['apply_changes'] : 0;

        if ((string)$applyChanges !== '0') {
            return $this->failed(array('apply_changes_not_allowed'), $applyChanges);
        }

        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        if (!isset($scope['category_id']) || (int)$scope['category_id'] !== 11900213) {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        $normalizedValues = isset($attributeValueStructure['normalized_values']) && is_array($attributeValueStructure['normalized_values']) ? $attributeValueStructure['normalized_values'] : array();

        if ($normalizedValues === array()) {
            return $this->failed(array('normalized_values_missing'), $applyChanges);
        }

        $normalizedValue = $normalizedValues[0];

        if (!isset($normalizedValue['product_id']) || (int)$normalizedValue['product_id'] !== 0) {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        if (!isset($normalizedValue['attribute_id']) || (int)$normalizedValue['attribute_id'] !== 0) {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        if (!isset($normalizedValue['normalized_value']) || (int)$normalizedValue['normalized_value'] !== 96) {
            return $this->failed(array('sql_preview_build_failed'), $applyChanges);
        }

        $unknownValues = isset($attributeValueStructure['unknown_values']) && is_array($attributeValueStructure['unknown_values']) ? $attributeValueStructure['unknown_values'] : array();
        $invalidValues = isset($attributeValueStructure['invalid_values']) && is_array($attributeValueStructure['invalid_values']) ? $attributeValueStructure['invalid_values'] : array();
        $emptyValues = isset($attributeValueStructure['empty_values']) && is_array($attributeValueStructure['empty_values']) ? $attributeValueStructure['empty_values'] : array();
        $proposedSynonyms = isset($synonymCandidates['proposed']) && is_array($synonymCandidates['proposed']) ? $synonymCandidates['proposed'] : array();
        $ambiguousCandidates = isset($synonymCandidates['ambiguous']) && is_array($synonymCandidates['ambiguous']) ? $synonymCandidates['ambiguous'] : array();

        $sqlPreview = array(
            'enabled' => 1,
            'generated' => 1,
            'safe_to_apply' => 1,
            'apply_changes' => 0,
            'source' => 'dry_run_fixture',
            'mode' => 'preview_only',
            'blocked_by' => array(),
            'statements' => array(
                array(
                    'statement_type' => 'preview_only',
                    'operation' => 'update_product_attribute',
                    'sql' => '-- dry-run preview only: update product_attribute for product_id=0 attribute_id=0 normalized_value=96',
                    'source' => 'dry_run_fixture',
                ),
            ),
            'operations' => array(
                'would_create' => array(),
                'would_update' => array(
                    array(
                        'product_id' => 0,
                        'attribute_id' => 0,
                        'normalized_value' => 96,
                        'raw_text' => '96 мм',
                        'source' => 'dry_run_fixture',
                    ),
                ),
                'skipped' => array(),
                'blocked' => array(),
            ),
            'diagnostics' => array(
                'normalized_count' => 1,
                'unknown_count' => count($unknownValues),
                'invalid_count' => count($invalidValues),
                'empty_count' => count($emptyValues),
                'proposed_synonym_count' => count($proposedSynonyms),
                'ambiguous_candidate_count' => count($ambiguousCandidates),
                'unknown_value_policy' => isset($valueRules['unknown_value_policy']) ? $valueRules['unknown_value_policy'] : '',
                'source' => 'dry_run_fixture',
            ),
        );

        return array(
            'built' => 1,
            'sql_preview' => $sqlPreview,
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
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
                'source' => 'dry_run_fixture',
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
                    'normalized_count' => 0,
                    'unknown_count' => 0,
                    'invalid_count' => 0,
                    'empty_count' => 0,
                    'proposed_synonym_count' => 0,
                    'ambiguous_candidate_count' => 0,
                    'source' => 'dry_run_fixture',
                ),
            ),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }
}
