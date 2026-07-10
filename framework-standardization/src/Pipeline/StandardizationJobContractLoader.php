<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Normalizer\NormalizerRegistry;

final class StandardizationJobContractLoader
{
    private $baseDir;
    private $normalizers;

    public function __construct($baseDir, NormalizerRegistry $normalizers)
    {
        $this->baseDir = rtrim(str_replace('\\', '/', (string) $baseDir), '/');
        $this->normalizers = $normalizers;
    }

    public function load($path)
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException('pipeline_job_config_not_found');
        }

        $job = require $path;

        if (!is_array($job)) {
            throw new \InvalidArgumentException('pipeline_job_contract_invalid');
        }

        return $this->validate($job);
    }

    public function validate(array $job)
    {
        if (!array_key_exists('job_version', $job)) {
            throw new \InvalidArgumentException('pipeline_job_version_required');
        }

        if ((int) $job['job_version'] !== 1) {
            throw new \InvalidArgumentException('pipeline_job_version_unknown');
        }

        if (!isset($job['job_key']) || trim((string) $job['job_key']) === '') {
            throw new \InvalidArgumentException('pipeline_job_key_required');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $job['job_key'])) {
            throw new \InvalidArgumentException('pipeline_job_key_invalid');
        }

        if (!isset($job['runtime_config']) || trim((string) $job['runtime_config']) === '') {
            throw new \InvalidArgumentException('pipeline_runtime_config_required');
        }

        if (!is_file($this->resolvePath($job['runtime_config']))) {
            throw new \InvalidArgumentException('pipeline_runtime_config_not_found');
        }

        if (!isset($job['scope']) || !is_array($job['scope'])) {
            throw new \InvalidArgumentException('pipeline_scope_required');
        }

        $categoryIds = $this->readPositiveIntList($job['scope'], 'category_ids', 'pipeline_category_ids_invalid');

        if (count($categoryIds) === 0) {
            throw new \InvalidArgumentException('pipeline_category_ids_required');
        }

        if (!isset($job['target']) || !is_array($job['target'])) {
            throw new \InvalidArgumentException('pipeline_target_required');
        }

        $searchTerms = $this->readStringList($job['target'], 'search_terms', 'pipeline_search_terms_invalid');

        if (count($searchTerms) === 0) {
            throw new \InvalidArgumentException('pipeline_search_terms_required');
        }

        if (!isset($job['target']['canonical_attribute_id']) || (int) $job['target']['canonical_attribute_id'] <= 0) {
            throw new \InvalidArgumentException('pipeline_canonical_attribute_required');
        }

        $canonicalAttributeId = (int) $job['target']['canonical_attribute_id'];
        $includedAliasAttributeIds = array();

        if (isset($job['target']['included_alias_attribute_ids'])) {
            $this->assertRawIntListUnique($job['target'], 'included_alias_attribute_ids', 'pipeline_included_alias_attribute_ids_duplicate');
            $includedAliasAttributeIds = $this->readPositiveIntList($job['target'], 'included_alias_attribute_ids', 'pipeline_included_alias_attribute_ids_invalid');
            $candidateAttributeIds = array_merge(array($canonicalAttributeId), $includedAliasAttributeIds);
        } else {
            $candidateAttributeIds = $this->readPositiveIntList($job['target'], 'candidate_attribute_ids', 'pipeline_candidate_attribute_ids_invalid');

            foreach ($candidateAttributeIds as $candidateAttributeId) {
                if ((int) $candidateAttributeId !== $canonicalAttributeId) {
                    $includedAliasAttributeIds[] = (int) $candidateAttributeId;
                }
            }
        }

        $excludedAttributeIds = $this->readPositiveIntList($job['target'], 'excluded_attribute_ids', 'pipeline_excluded_attribute_ids_invalid');

        if (count($candidateAttributeIds) === 0) {
            throw new \InvalidArgumentException('pipeline_candidate_attribute_ids_required');
        }

        if (!in_array($canonicalAttributeId, $candidateAttributeIds, true)) {
            throw new \InvalidArgumentException('pipeline_canonical_not_in_candidates');
        }

        if (in_array($canonicalAttributeId, $includedAliasAttributeIds, true)) {
            throw new \InvalidArgumentException('pipeline_canonical_in_aliases');
        }

        if (in_array($canonicalAttributeId, $excludedAttributeIds, true)) {
            throw new \InvalidArgumentException('pipeline_canonical_also_excluded');
        }

        foreach ($includedAliasAttributeIds as $aliasAttributeId) {
            if (in_array($aliasAttributeId, $excludedAttributeIds, true)) {
                throw new \InvalidArgumentException('pipeline_alias_also_excluded');
            }
        }

        foreach ($candidateAttributeIds as $candidateAttributeId) {
            if (in_array($candidateAttributeId, $excludedAttributeIds, true)) {
                throw new \InvalidArgumentException('pipeline_candidate_also_excluded');
            }
        }

        if (!isset($job['normalization']) || !is_array($job['normalization'])) {
            throw new \InvalidArgumentException('pipeline_normalization_required');
        }

        if (!isset($job['normalization']['normalizer_key']) || trim((string) $job['normalization']['normalizer_key']) === '') {
            throw new \InvalidArgumentException('pipeline_normalizer_required');
        }

        if (!$this->normalizers->has($job['normalization']['normalizer_key'])) {
            throw new \InvalidArgumentException('pipeline_normalizer_unknown');
        }

        if (!isset($job['normalization']['canonical_unit']) || trim((string) $job['normalization']['canonical_unit']) === '') {
            throw new \InvalidArgumentException('pipeline_canonical_unit_required');
        }

        if (isset($job['normalization']['normalized_value_type'])) {
            $normalizedValueType = trim((string) $job['normalization']['normalized_value_type']);

            if ($normalizedValueType === '') {
                throw new \InvalidArgumentException('pipeline_normalized_value_type_invalid');
            }

            $job['normalization']['normalized_value_type'] = $normalizedValueType;
        }

        if (isset($job['normalization']['allowed_canonical_values'])) {
            $allowedCanonicalValues = $this->readCanonicalValueList($job['normalization'], 'allowed_canonical_values', 'pipeline_allowed_canonical_values_invalid');

            if (count($allowedCanonicalValues) === 0) {
                throw new \InvalidArgumentException('pipeline_allowed_canonical_values_required');
            }

            $job['normalization']['allowed_canonical_values'] = $allowedCanonicalValues;
        }

        if (!isset($job['output']) || !is_array($job['output'])) {
            throw new \InvalidArgumentException('pipeline_output_required');
        }

        $format = isset($job['output']['format']) ? (string) $job['output']['format'] : '';

        if ($format !== 'markdown') {
            throw new \InvalidArgumentException('pipeline_output_format_unknown');
        }

        if (!isset($job['output']['directory']) || trim((string) $job['output']['directory']) === '') {
            throw new \InvalidArgumentException('pipeline_output_directory_required');
        }

        $this->assertOutputPathAllowed($job['output']['directory']);

        if (!isset($job['safety']) || !is_array($job['safety'])) {
            throw new \InvalidArgumentException('pipeline_safety_required');
        }

        $this->assertSafety($job['safety']);

        $job['scope']['category_ids'] = $categoryIds;
        $job['target']['search_terms'] = $searchTerms;
        $job['target']['canonical_attribute_id'] = $canonicalAttributeId;
        $job['target']['candidate_attribute_ids'] = $candidateAttributeIds;
        $job['target']['included_alias_attribute_ids'] = $includedAliasAttributeIds;
        $job['target']['excluded_attribute_ids'] = $excludedAttributeIds;

        return $job;
    }

    public function resolvePath($path)
    {
        $path = (string) $path;

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || strpos($path, '\\\\') === 0) {
            return $path;
        }

        return $this->baseDir . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    public function assertOutputPathAllowed($path)
    {
        $normalized = str_replace('\\', '/', (string) $path);

        if (strpos($normalized, '..') !== false) {
            throw new \InvalidArgumentException('pipeline_output_path_not_allowed');
        }

        $allowed = 'framework-standardization/runtime/reports';

        if ($normalized !== $allowed && strpos($normalized, $allowed . '/') !== 0) {
            throw new \InvalidArgumentException('pipeline_output_path_not_allowed');
        }
    }

    private function readPositiveIntList(array $source, $key, $error)
    {
        if (!isset($source[$key]) || !is_array($source[$key])) {
            throw new \InvalidArgumentException($error);
        }

        $values = array();

        foreach ($source[$key] as $value) {
            if (!is_int($value) && !preg_match('/^[1-9][0-9]*$/', (string) $value)) {
                throw new \InvalidArgumentException($error);
            }

            $intValue = (int) $value;

            if ($intValue <= 0) {
                throw new \InvalidArgumentException($error);
            }

            if (!in_array($intValue, $values, true)) {
                $values[] = $intValue;
            }
        }

        return $values;
    }

    private function assertRawIntListUnique(array $source, $key, $error)
    {
        if (!isset($source[$key]) || !is_array($source[$key])) {
            return;
        }

        $seen = array();

        foreach ($source[$key] as $value) {
            $value = (int) $value;

            if (isset($seen[$value])) {
                throw new \InvalidArgumentException($error);
            }

            $seen[$value] = true;
        }
    }

    private function readCanonicalValueList(array $source, $key, $error)
    {
        if (!isset($source[$key]) || !is_array($source[$key])) {
            throw new \InvalidArgumentException($error);
        }

        $values = array();
        $seen = array();

        foreach ($source[$key] as $value) {
            $value = trim((string) $value);

            if ($value === '' || !preg_match('/^[0-9]+$/', $value)) {
                throw new \InvalidArgumentException($error);
            }

            $value = (string) (int) $value;

            if ($value === '0') {
                throw new \InvalidArgumentException($error);
            }

            if (isset($seen[$value])) {
                throw new \InvalidArgumentException('pipeline_allowed_canonical_values_duplicate');
            }

            $seen[$value] = true;
            $values[] = $value;
        }

        return $values;
    }

    private function readStringList(array $source, $key, $error)
    {
        if (!isset($source[$key]) || !is_array($source[$key])) {
            throw new \InvalidArgumentException($error);
        }

        $values = array();

        foreach ($source[$key] as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                throw new \InvalidArgumentException($error);
            }

            $values[] = $value;
        }

        return $values;
    }

    private function assertSafety(array $safety)
    {
        $expected = array(
            'read_only' => true,
            'allow_sql_generation' => false,
            'allow_apply_plan' => false,
            'allow_apply' => false,
        );

        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $safety) || $safety[$key] !== $value) {
                throw new \InvalidArgumentException('pipeline_safety_' . $key . '_invalid');
            }
        }
    }
}
