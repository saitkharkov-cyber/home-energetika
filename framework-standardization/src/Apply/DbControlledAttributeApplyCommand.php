<?php

namespace FrameworkStandardization\Apply;

use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use PDO;

final class DbControlledAttributeApplyCommand
{
    private $pdo;
    private $dbPrefix;
    private $contract;
    private $normalizer;

    public function __construct(PDO $pdo, $dbPrefix, array $contract, SimpleMetersNormalizer $normalizer)
    {
        $this->pdo = $pdo;
        $this->dbPrefix = $dbPrefix;
        $this->contract = $contract;
        $this->normalizer = $normalizer;
    }

    public function run($runtimeMode, $confirmApply)
    {
        if ((string) $this->contract['normalizer_key'] !== 'simple_meters') {
            throw new \RuntimeException('normalizer_not_supported');
        }

        if ($confirmApply && empty($this->contract['confirmation_required'])) {
            throw new \RuntimeException('confirmation_not_allowed_by_contract');
        }

        if ($confirmApply && empty($this->contract['runtime_allowlist']['controlled_local_dump']['allow_confirm_apply'])) {
            throw new \RuntimeException('confirm_apply_not_allowed_by_contract_runtime');
        }

        if ($confirmApply) {
            throw new \RuntimeException('confirm_apply_not_enabled_for_generic_attribute_apply_step_d');
        }

        $plan = $this->buildPlan();
        $expectedCountsMatch = $this->expectedCountsMatch($plan) ? 1 : 0;
        $sourceBasedPlanAvailable = (count($plan['updates']) > 0 || count($plan['inserts']) > 0 || $plan['already_applied_count'] > 0) ? 1 : 0;
        $dryRunLimitation = $sourceBasedPlanAvailable
            ? 'none'
            : 'canonical apply dry-run after alias cleanup has limited source rows';

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_attribute_apply',
            'target_key' => (string) $this->contract['target_key'],
            'target_meaning' => (string) $this->contract['target_meaning'],
            'dry_run' => 1,
            'confirm_apply' => 0,
            'category_scope' => (int) $this->contract['category_scope_id'],
            'canonical_attribute_id' => (int) $this->contract['canonical_attribute_id'],
            'alias_attribute_ids' => $this->contract['alias_attribute_ids'],
            'normalizer_key' => (string) $this->contract['normalizer_key'],
            'target_table' => (string) $this->contract['allowed_table'],
            'update_existing_canonical_row_count' => count($plan['updates']),
            'insert_missing_canonical_row_count' => count($plan['inserts']),
            'already_applied_count' => $plan['already_applied_count'],
            'source_based_already_applied_count' => $plan['already_applied_count'],
            'canonical_only_verified_count' => $plan['canonical_only_verified_count'],
            'source_based_plan_available' => $sourceBasedPlanAvailable,
            'dry_run_limitation' => $dryRunLimitation,
            'unresolved_excluded_count' => $plan['unresolved_excluded_count'],
            'duplicate_or_conflict_count' => $plan['duplicate_or_conflict_count'],
            'expected_update_after_cleanup_count' => (int) $this->contract['expected_canonical_update_count_after_cleanup'],
            'expected_insert_after_cleanup_count' => (int) $this->contract['expected_canonical_insert_count_after_cleanup'],
            'expected_already_applied_count' => (int) $this->contract['expected_canonical_already_applied_count'],
            'expected_unresolved_excluded_count' => (int) $this->contract['expected_unresolved_excluded_count'],
            'expected_counts_match' => $expectedCountsMatch,
            'actual_updated_count' => 0,
            'actual_inserted_count' => 0,
            'transaction_started' => 0,
            'transaction_committed' => 0,
            'transaction_rolled_back' => 0,
            'rollback_reason' => 'none',
            'post_apply_verification_ok' => $expectedCountsMatch,
            'safety_markers' => array(
                'db_controlled' => 1,
                'update_executed' => 0,
                'insert_executed' => 0,
                'sql_applied' => 0,
                'product_data_changed' => 0,
                'production_ready' => 0,
                'cache_rebuild_performed' => 0,
                'touches_oc_attribute' => 0,
                'touches_oc_attribute_description' => 0,
                'source_alias_rows_changed' => 0,
                'canonical_rows_deleted' => 0,
            ),
        );
    }

    private function buildPlan()
    {
        $categoryScopeIds = $this->loadCategoryScopeIds();
        $rows = $this->loadSourceRows($categoryScopeIds);
        $groups = $this->groupRowsByProductAndLanguage($rows);
        $updates = array();
        $inserts = array();
        $alreadyAppliedCount = 0;
        $canonicalOnlyVerifiedCount = 0;
        $unresolvedExcludedCount = 0;
        $duplicateOrConflictCount = 0;

        foreach ($groups as $group) {
            $canonicalRows = array();
            $sourceRows = array();
            $normalizedValues = array();
            $hasDuplicateExactRow = false;

            foreach ($group as $row) {
                if ((int) $row['exact_row_count'] !== 1) {
                    $hasDuplicateExactRow = true;
                }

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
                    $unresolvedExcludedCount++;
                    continue;
                }

                $normalizedValues[(string) $normalized['normalized_value']] = (string) $normalized['normalized_value'];
            }

            if ($hasDuplicateExactRow) {
                $duplicateOrConflictCount++;
                continue;
            }

            if (count($sourceRows) === 0) {
                if (count($canonicalRows) === 1 && (int) $canonicalRows[0]['exact_row_count'] === 1) {
                    $normalizedCanonical = $this->normalizer->normalize((string) $canonicalRows[0]['text']);

                    if ($normalizedCanonical['normalized_value'] !== null
                        && (string) $normalizedCanonical['normalized_value'] === trim((string) $canonicalRows[0]['text'])) {
                        $canonicalOnlyVerifiedCount++;
                    }
                }
                continue;
            }

            if (count($normalizedValues) === 0) {
                continue;
            }

            if (count($normalizedValues) !== 1) {
                $duplicateOrConflictCount++;
                continue;
            }

            $normalizedValue = reset($normalizedValues);

            if (count($canonicalRows) === 0) {
                $inserts[] = $this->buildPlanRow($group, $normalizedValue);
                continue;
            }

            if (count($canonicalRows) !== 1) {
                $duplicateOrConflictCount++;
                continue;
            }

            $canonicalText = trim((string) $canonicalRows[0]['text']);

            if ($canonicalText === (string) $normalizedValue) {
                $alreadyAppliedCount++;
                continue;
            }

            $updates[] = $this->buildPlanRow($group, $normalizedValue);
        }

        return array(
            'updates' => $updates,
            'inserts' => $inserts,
            'already_applied_count' => $alreadyAppliedCount,
            'canonical_only_verified_count' => $canonicalOnlyVerifiedCount,
            'unresolved_excluded_count' => $unresolvedExcludedCount,
            'duplicate_or_conflict_count' => $duplicateOrConflictCount,
        );
    }

    private function buildPlanRow(array $group, $normalizedValue)
    {
        $first = reset($group);

        return array(
            'product_id' => (int) $first['product_id'],
            'language_id' => (int) $first['language_id'],
            'canonical_attribute_id' => (int) $this->contract['canonical_attribute_id'],
            'normalized_value' => (string) $normalizedValue,
        );
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

    private function expectedCountsMatch(array $plan)
    {
        return count($plan['updates']) === (int) $this->contract['expected_canonical_update_count_after_cleanup']
            && count($plan['inserts']) === (int) $this->contract['expected_canonical_insert_count_after_cleanup']
            && $plan['already_applied_count'] === (int) $this->contract['expected_canonical_already_applied_count']
            && $plan['unresolved_excluded_count'] === (int) $this->contract['expected_unresolved_excluded_count'];
    }

    private function loadCategoryScopeIds()
    {
        $sql = 'SELECT category_id, parent_id FROM ' . $this->dbPrefix . 'category';
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $childrenByParent = array();

        foreach ($rows as $row) {
            $parentId = (int) $row['parent_id'];

            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }

            $childrenByParent[$parentId][] = (int) $row['category_id'];
        }

        $rootCategoryId = (int) $this->contract['category_scope_id'];
        $scope = array($rootCategoryId => true);
        $queue = array($rootCategoryId);

        while (count($queue) > 0) {
            $current = array_shift($queue);

            if (!isset($childrenByParent[$current])) {
                continue;
            }

            foreach ($childrenByParent[$current] as $childId) {
                if (!isset($scope[$childId])) {
                    $scope[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_keys($scope);
    }

    private function loadSourceRows(array $categoryScopeIds)
    {
        $attributeIds = array_merge(
            array((int) $this->contract['canonical_attribute_id']),
            $this->contract['alias_attribute_ids']
        );
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $attributeIds, $params);
        $sql = 'SELECT pa.product_id, pa.attribute_id, pa.language_id, pa.text, COUNT(*) AS exact_row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'AND EXISTS (';
        $sql .= 'SELECT 1 FROM ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'WHERE p2c.product_id = pa.product_id ';
        $sql .= 'AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ')';
        $sql .= ') ';
        $sql .= 'GROUP BY pa.product_id, pa.attribute_id, pa.language_id, pa.text ';
        $sql .= 'ORDER BY pa.product_id, pa.language_id, pa.attribute_id, pa.text';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function groupRowsByProductAndLanguage(array $rows)
    {
        $groups = array();

        foreach ($rows as $row) {
            $key = (int) $row['product_id'] . '|' . (int) $row['language_id'];

            if (!isset($groups[$key])) {
                $groups[$key] = array();
            }

            $groups[$key][] = $row;
        }

        return $groups;
    }

    private function buildPlaceholders($prefix, array $values, array &$params)
    {
        $placeholders = array();
        $index = 0;

        foreach ($values as $value) {
            $key = ':' . $prefix . '_' . $index;
            $params[$key] = (int) $value;
            $placeholders[] = $key;
            $index++;
        }

        return $placeholders;
    }
}
