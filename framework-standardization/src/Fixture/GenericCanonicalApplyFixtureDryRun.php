<?php

namespace FrameworkStandardization\Fixture;

use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;

final class GenericCanonicalApplyFixtureDryRun
{
    private $contract;
    private $rows;
    private $normalizer;

    public function __construct(array $contract, array $rows, SimpleMetersNormalizer $normalizer)
    {
        $this->contract = $contract;
        $this->rows = $rows;
        $this->normalizer = $normalizer;
    }

    public function run()
    {
        $this->validateContract();
        $plan = $this->buildPlan();
        $expectedCountsMatch = $this->expectedCountsMatch($plan) ? 1 : 0;
        $sourceBasedPlanAvailable = (
            $plan['update_existing_canonical_row_count'] > 0
            || $plan['insert_missing_canonical_row_count'] > 0
        ) ? 1 : 0;

        return array(
            'command' => 'fixture_canonical_apply_dry_run',
            'fixture_only' => 1,
            'target_key' => (string) $this->contract['target_key'],
            'target_meaning' => (string) $this->contract['target_meaning'],
            'canonical_attribute_id' => (int) $this->contract['canonical_attribute_id'],
            'alias_attribute_ids' => $this->contract['alias_attribute_ids'],
            'normalizer_key' => (string) $this->contract['normalizer_key'],
            'update_existing_canonical_row_count' => $plan['update_existing_canonical_row_count'],
            'insert_missing_canonical_row_count' => $plan['insert_missing_canonical_row_count'],
            'already_applied_count' => $plan['already_applied_count'],
            'unresolved_excluded_count' => $plan['unresolved_excluded_count'],
            'duplicate_or_conflict_count' => $plan['duplicate_or_conflict_count'],
            'out_of_scope_ignored_count' => $plan['out_of_scope_ignored_count'],
            'source_based_plan_available' => $sourceBasedPlanAvailable,
            'expected_counts_match' => $expectedCountsMatch,
            'dry_run_expected_counts_ok' => $expectedCountsMatch,
            'post_apply_verification_ok' => 0,
            'dry_run' => 1,
            'confirm_apply' => 0,
            'sql_applied' => 0,
            'product_data_changed' => 0,
            'safety_markers' => array(
                'fixture_only' => 1,
                'db_connected' => 0,
                'update_executed' => 0,
                'insert_executed' => 0,
                'delete_executed' => 0,
                'sql_applied' => 0,
                'product_data_changed' => 0,
                'production_ready' => 0,
                'cache_rebuild_performed' => 0,
                'source_alias_rows_changed' => 0,
                'canonical_rows_deleted' => 0,
            ),
        );
    }

    private function validateContract()
    {
        $required = array(
            'target_key',
            'target_meaning',
            'fixture_only',
            'category_scope_ids',
            'canonical_attribute_id',
            'alias_attribute_ids',
            'normalizer_key',
            'expected_counts',
            'safety',
        );

        foreach ($required as $key) {
            if (!array_key_exists($key, $this->contract)) {
                throw new \InvalidArgumentException('fixture_contract_missing_' . $key);
            }
        }

        if (empty($this->contract['fixture_only'])) {
            throw new \InvalidArgumentException('fixture_contract_not_fixture_only');
        }

        if ((string) $this->contract['normalizer_key'] !== 'simple_meters') {
            throw new \InvalidArgumentException('fixture_normalizer_not_supported');
        }

        if (!is_array($this->contract['alias_attribute_ids']) || count($this->contract['alias_attribute_ids']) === 0) {
            throw new \InvalidArgumentException('fixture_alias_attribute_ids_required');
        }

        $this->validateSafety();
    }

    private function validateSafety()
    {
        if (!isset($this->contract['safety']) || !is_array($this->contract['safety'])) {
            throw new \InvalidArgumentException('fixture_safety_missing');
        }

        $expected = array(
            'fixture_only' => true,
            'db_allowed' => false,
            'confirm_apply_allowed' => false,
            'production_ready' => false,
            'cache_rebuild_allowed' => false,
        );

        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $this->contract['safety'])) {
                throw new \InvalidArgumentException('fixture_safety_missing_' . $key);
            }

            if ($this->contract['safety'][$key] !== $value) {
                throw new \InvalidArgumentException('fixture_safety_invalid_' . $key);
            }
        }
    }

    private function buildPlan()
    {
        $groups = array();
        $outOfScopeIgnoredCount = 0;
        $scope = $this->buildScopeMap();
        $trackedAttributeIds = $this->buildTrackedAttributeMap();

        foreach ($this->rows as $row) {
            $this->validateRow($row);
            $attributeId = (int) $row['attribute_id'];

            if (!isset($trackedAttributeIds[$attributeId])) {
                continue;
            }

            if (!isset($scope[(int) $row['category_id']])) {
                $outOfScopeIgnoredCount++;
                continue;
            }

            $key = (int) $row['product_id'] . '|' . (int) $row['language_id'];

            if (!isset($groups[$key])) {
                $groups[$key] = array();
            }

            $groups[$key][] = $row;
        }

        $plan = array(
            'update_existing_canonical_row_count' => 0,
            'insert_missing_canonical_row_count' => 0,
            'already_applied_count' => 0,
            'unresolved_excluded_count' => 0,
            'duplicate_or_conflict_count' => 0,
            'out_of_scope_ignored_count' => $outOfScopeIgnoredCount,
        );

        foreach ($groups as $group) {
            $this->classifyGroup($group, $plan);
        }

        return $plan;
    }

    private function classifyGroup(array $group, array &$plan)
    {
        $canonicalRows = array();
        $sourceRows = array();
        $normalizedValues = array();
        $unresolvedSourceCount = 0;
        $exactRows = array();

        foreach ($group as $row) {
            $exactKey = (int) $row['product_id'] . '|'
                . (int) $row['attribute_id'] . '|'
                . (int) $row['language_id'] . '|'
                . (string) $row['text'];

            if (isset($exactRows[$exactKey])) {
                $plan['duplicate_or_conflict_count']++;
                return;
            }

            $exactRows[$exactKey] = true;

            if ((int) $row['attribute_id'] === (int) $this->contract['canonical_attribute_id']) {
                $canonicalRows[] = $row;
                continue;
            }

            if (!$this->isAliasAttributeId((int) $row['attribute_id'])) {
                continue;
            }

            $sourceRows[] = $row;
            $normalized = $this->normalizer->normalize((string) $row['text']);

            if ($normalized['normalized_value'] === null) {
                $unresolvedSourceCount++;
                continue;
            }

            $normalizedValues[(string) $normalized['normalized_value']] = (string) $normalized['normalized_value'];
        }

        if (count($sourceRows) === 0 || count($normalizedValues) === 0) {
            $plan['unresolved_excluded_count'] += $unresolvedSourceCount;
            return;
        }

        if ($unresolvedSourceCount > 0) {
            $plan['duplicate_or_conflict_count']++;
            return;
        }

        if (count($normalizedValues) !== 1) {
            $plan['duplicate_or_conflict_count']++;
            return;
        }

        $normalizedValue = reset($normalizedValues);

        if (count($canonicalRows) === 0) {
            $plan['insert_missing_canonical_row_count']++;
            return;
        }

        if (count($canonicalRows) !== 1) {
            $plan['duplicate_or_conflict_count']++;
            return;
        }

        if (trim((string) $canonicalRows[0]['text']) === (string) $normalizedValue) {
            $plan['already_applied_count']++;
            return;
        }

        $plan['update_existing_canonical_row_count']++;
    }

    private function expectedCountsMatch(array $plan)
    {
        foreach ($this->contract['expected_counts'] as $key => $expectedValue) {
            if (!array_key_exists($key, $plan)) {
                return false;
            }

            if ((int) $plan[$key] !== (int) $expectedValue) {
                return false;
            }
        }

        return true;
    }

    private function validateRow(array $row)
    {
        $required = array('product_id', 'language_id', 'category_id', 'attribute_id', 'text');

        foreach ($required as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \InvalidArgumentException('fixture_row_missing_' . $key);
            }
        }
    }

    private function buildScopeMap()
    {
        $scope = array();

        foreach ($this->contract['category_scope_ids'] as $categoryId) {
            $scope[(int) $categoryId] = true;
        }

        return $scope;
    }

    private function buildTrackedAttributeMap()
    {
        $attributeIds = array_merge(
            array((int) $this->contract['canonical_attribute_id']),
            $this->contract['alias_attribute_ids']
        );
        $map = array();

        foreach ($attributeIds as $attributeId) {
            $map[(int) $attributeId] = true;
        }

        return $map;
    }

    private function isAliasAttributeId($attributeId)
    {
        foreach ($this->contract['alias_attribute_ids'] as $aliasAttributeId) {
            if ((int) $aliasAttributeId === (int) $attributeId) {
                return true;
            }
        }

        return false;
    }
}
