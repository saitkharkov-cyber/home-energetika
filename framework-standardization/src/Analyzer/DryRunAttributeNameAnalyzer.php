<?php

namespace FrameworkStandardization\Analyzer;

use FrameworkStandardization\Contract\AttributeNameAnalyzerInterface;

final class DryRunAttributeNameAnalyzer implements AttributeNameAnalyzerInterface
{
    public function analyze(array $canonical, array $rawData, array $attributeNameStructure)
    {
        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('name_analysis_failed'));
        }

        if (!isset($attributeNameStructure['target_attribute']) || !is_array($attributeNameStructure['target_attribute'])) {
            return $this->failed(array('target_attribute_missing'));
        }

        if (!isset($attributeNameStructure['found_attributes']) || !is_array($attributeNameStructure['found_attributes']) || $attributeNameStructure['found_attributes'] === array()) {
            return $this->failed(array('found_attributes_missing'));
        }

        $targetAttribute = $attributeNameStructure['target_attribute'];
        $foundAttributes = $attributeNameStructure['found_attributes'];

        if (!isset($targetAttribute['attribute_id']) || (int)$targetAttribute['attribute_id'] !== 0) {
            return $this->failed(array('target_attribute_missing'));
        }

        if (!isset($foundAttributes[0]['attribute_id']) || (int)$foundAttributes[0]['attribute_id'] !== 0) {
            return $this->failed(array('found_attributes_missing'));
        }

        $targetAttribute = array(
            'attribute_id' => 0,
            'attribute_name' => 'Dry-run pump diameter',
            'attribute_group_id' => 0,
            'attribute_group_name' => 'Dry-run attributes',
            'source' => 'dry_run_fixture',
        );

        $foundAttribute = array(
            'attribute_id' => 0,
            'attribute_name' => 'Dry-run pump diameter',
            'attribute_group_id' => 0,
            'attribute_group_name' => 'Dry-run attributes',
            'usage_count' => 1,
            'sample_values' => array('96 мм'),
            'source' => 'dry_run_fixture',
        );

        return array(
            'analyzed' => 1,
            'target_attribute' => $targetAttribute,
            'found_attributes' => array($foundAttribute),
            'exact_matches' => array(
                array(
                    'attribute_id' => 0,
                    'reason' => 'target_attribute_fixture_match',
                    'source' => 'dry_run_fixture',
                ),
            ),
            'similar_name_candidates' => array(),
            'rejected_name_candidates' => array(),
            'diagnostics' => array(
                'total_found' => 1,
                'target_usage_count' => 1,
                'most_frequent_attribute_id' => 0,
                'ambiguous_names_count' => 0,
                'source' => 'dry_run_fixture',
            ),
            'synonym_candidates' => array(
                'proposed' => array(),
                'rejected' => array(),
                'ambiguous' => array(),
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
            'target_attribute' => array(),
            'found_attributes' => array(),
            'exact_matches' => array(),
            'similar_name_candidates' => array(),
            'rejected_name_candidates' => array(),
            'diagnostics' => array(),
            'synonym_candidates' => array(
                'proposed' => array(),
                'rejected' => array(),
                'ambiguous' => array(),
            ),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }
}
