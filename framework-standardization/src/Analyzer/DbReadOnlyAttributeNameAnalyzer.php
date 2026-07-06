<?php

namespace FrameworkStandardization\Analyzer;

use FrameworkStandardization\Contract\AttributeNameAnalyzerInterface;

final class DbReadOnlyAttributeNameAnalyzer implements AttributeNameAnalyzerInterface
{
    public function analyze(array $canonical, array $rawData, array $attributeNameStructure)
    {
        if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
            return $this->failed(array('name_analysis_failed'));
        }

        if (!isset($canonical['target_attribute_id']) || (int)$canonical['target_attribute_id'] <= 0) {
            return $this->failed(array('target_attribute_missing'));
        }

        if (!isset($attributeNameStructure['target_attribute']) || !is_array($attributeNameStructure['target_attribute']) || $attributeNameStructure['target_attribute'] === array()) {
            return $this->failed(array('target_attribute_missing'));
        }

        if (!isset($attributeNameStructure['found_attributes']) || !is_array($attributeNameStructure['found_attributes']) || $attributeNameStructure['found_attributes'] === array()) {
            return $this->failed(array('found_attributes_missing'));
        }

        $targetAttribute = $attributeNameStructure['target_attribute'];
        $foundAttributes = $attributeNameStructure['found_attributes'];
        $targetAttributeId = (int)$canonical['target_attribute_id'];
        $exactMatches = array();
        $targetUsageCount = 0;

        foreach ($foundAttributes as $foundAttribute) {
            if (!isset($foundAttribute['attribute_id']) || (int)$foundAttribute['attribute_id'] <= 0) {
                return $this->failed(array('found_attributes_missing'));
            }

            if ((int)$foundAttribute['attribute_id'] === $targetAttributeId) {
                $targetUsageCount = isset($foundAttribute['usage_count']) ? (int)$foundAttribute['usage_count'] : 0;
                $exactMatches[] = array(
                    'attribute_id' => $targetAttributeId,
                    'reason' => 'target_attribute_db_match',
                    'source' => 'local_dump_db_readonly',
                );
            }
        }

        if (!isset($targetAttribute['attribute_id']) || (int)$targetAttribute['attribute_id'] !== $targetAttributeId) {
            return $this->failed(array('target_attribute_missing'));
        }

        return array(
            'analyzed' => 1,
            'target_attribute' => $targetAttribute,
            'found_attributes' => $foundAttributes,
            'exact_matches' => $exactMatches,
            'similar_name_candidates' => array(),
            'rejected_name_candidates' => array(),
            'diagnostics' => array(
                'total_found' => count($foundAttributes),
                'target_usage_count' => $targetUsageCount,
                'most_frequent_attribute_id' => $this->findMostFrequentAttributeId($foundAttributes),
                'ambiguous_names_count' => 0,
                'source' => 'local_dump_db_readonly',
            ),
            'synonym_candidates' => array(
                'proposed' => array(),
                'rejected' => array(),
                'ambiguous' => array(),
            ),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }

    private function findMostFrequentAttributeId(array $foundAttributes)
    {
        $mostFrequentAttributeId = 0;
        $mostFrequentUsageCount = -1;

        foreach ($foundAttributes as $foundAttribute) {
            $usageCount = isset($foundAttribute['usage_count']) ? (int)$foundAttribute['usage_count'] : 0;

            if ($usageCount > $mostFrequentUsageCount) {
                $mostFrequentUsageCount = $usageCount;
                $mostFrequentAttributeId = isset($foundAttribute['attribute_id']) ? (int)$foundAttribute['attribute_id'] : 0;
            }
        }

        return $mostFrequentAttributeId;
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
            'source' => 'local_dump_db_readonly',
        );
    }
}
