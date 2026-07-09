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

        $this->assertStorageContract();

        $plan = $this->buildPlan();
        $expectedCountsMatch = $this->expectedCountsMatch($plan) ? 1 : 0;
        $sourceBasedPlanAvailable = (count($plan['updates']) > 0 || count($plan['inserts']) > 0 || $plan['already_applied_count'] > 0) ? 1 : 0;
        $dryRunLimitation = $sourceBasedPlanAvailable
            ? 'none'
            : 'canonical apply dry-run after alias cleanup has limited source rows';
        $actualUpdatedCount = 0;
        $actualInsertedCount = 0;
        $transactionStarted = 0;
        $transactionCommitted = 0;
        $transactionRolledBack = 0;
        $rollbackReason = 'none';
        $sourceAliasRowsPreserved = 1;
        $affectedOnlyCanonical = $this->affectedOnlyCanonicalAttribute($plan) ? 1 : 0;
        $affectedOnlyScope = $this->affectedOnlyScope($plan, $plan['category_scope_ids']) ? 1 : 0;
        $unresolvedNotApplied = $plan['unresolved_excluded_count'] === (int) $this->contract['expected_unresolved_excluded_count'] ? 1 : 0;
        $confirmApplyEnabled = ($this->confirmApplyAllowedByContractRuntime($runtimeMode) && $expectedCountsMatch && $sourceBasedPlanAvailable && $affectedOnlyCanonical && $affectedOnlyScope && $unresolvedNotApplied) ? 1 : 0;
        $postApplyVerificationOk = $expectedCountsMatch;

        if ($confirmApply) {
            $this->assertConfirmApplyAllowed($runtimeMode, $expectedCountsMatch, $sourceBasedPlanAvailable, $affectedOnlyCanonical, $affectedOnlyScope, $unresolvedNotApplied);

            try {
                $sourceAliasRowsBefore = $this->countSourceAliasRows($plan['category_scope_ids']);

                if ($this->pdo->inTransaction()) {
                    $rollbackReason = 'transaction_already_active';
                    throw new \RuntimeException($rollbackReason);
                } elseif (!$this->pdo->beginTransaction()) {
                    $rollbackReason = 'transaction_not_available';
                    throw new \RuntimeException($rollbackReason);
                } else {
                    $transactionStarted = 1;
                    $actualUpdatedCount = $this->executeUpdates($plan['updates']);
                    $actualInsertedCount = $this->executeInserts($plan['inserts']);
                    $sourceAliasRowsAfter = $this->countSourceAliasRows($plan['category_scope_ids']);
                    $sourceAliasRowsPreserved = $sourceAliasRowsBefore === $sourceAliasRowsAfter ? 1 : 0;
                    $plannedCanonicalRowsVerified = $this->plannedCanonicalRowsVerified($plan);
                    $postApplyVerificationOk = $this->postApplyVerificationOk(
                        $actualUpdatedCount,
                        $actualInsertedCount,
                        $plan,
                        $sourceAliasRowsPreserved,
                        $affectedOnlyCanonical,
                        $affectedOnlyScope,
                        $unresolvedNotApplied,
                        $plannedCanonicalRowsVerified
                    );

                    if ($postApplyVerificationOk) {
                        $this->pdo->commit();
                        $transactionCommitted = 1;
                    } else {
                        $this->pdo->rollBack();
                        $transactionRolledBack = 1;
                        $rollbackReason = 'post_apply_verification_failed';
                        throw new \RuntimeException($rollbackReason);
                    }
                }
            } catch (\Exception $e) {
                if ($transactionStarted === 1 && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                    $transactionRolledBack = 1;
                }

                $rollbackReason = 'exception: ' . $e->getMessage();
                $actualUpdatedCount = 0;
                $actualInsertedCount = 0;

                throw new \RuntimeException('controlled_attribute_apply_failed: ' . $e->getMessage());
            }
        }

        $sqlApplied = ($transactionCommitted && ($actualUpdatedCount > 0 || $actualInsertedCount > 0)) ? 1 : 0;
        $productDataChanged = $sqlApplied;

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_attribute_apply',
            'target_key' => (string) $this->contract['target_key'],
            'target_meaning' => (string) $this->contract['target_meaning'],
            'dry_run' => $confirmApply ? 0 : 1,
            'confirm_apply' => $confirmApply ? 1 : 0,
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
            'actual_updated_count' => $actualUpdatedCount,
            'actual_inserted_count' => $actualInsertedCount,
            'transaction_started' => $transactionStarted,
            'transaction_committed' => $transactionCommitted,
            'transaction_rolled_back' => $transactionRolledBack,
            'rollback_reason' => $rollbackReason,
            'post_apply_verification_ok' => $postApplyVerificationOk,
            'write_path_structure_present' => 1,
            'confirm_apply_enabled' => $confirmApplyEnabled,
            'write_path_execution_enabled' => ($confirmApply && $transactionStarted) ? 1 : 0,
            'implementation_only' => 0,
            'safety_markers' => array(
                'db_controlled' => 1,
                'update_executed' => ($transactionCommitted && $actualUpdatedCount > 0) ? 1 : 0,
                'insert_executed' => ($transactionCommitted && $actualInsertedCount > 0) ? 1 : 0,
                'sql_applied' => $sqlApplied,
                'product_data_changed' => $productDataChanged,
                'production_ready' => 0,
                'cache_rebuild_performed' => 0,
                'touches_oc_attribute' => 0,
                'touches_oc_attribute_description' => 0,
                'source_alias_rows_changed' => 0,
                'canonical_rows_deleted' => 0,
            ),
        );
    }

    private function assertConfirmApplyAllowed($runtimeMode, $expectedCountsMatch, $sourceBasedPlanAvailable, $affectedOnlyCanonical, $affectedOnlyScope, $unresolvedNotApplied)
    {
        if (!$this->confirmApplyAllowedByContractRuntime($runtimeMode)) {
            throw new \RuntimeException('confirm_apply_not_allowed_by_contract_runtime');
        }

        if (!$sourceBasedPlanAvailable) {
            throw new \RuntimeException('source_based_plan_required_for_confirm_apply');
        }

        if (!$expectedCountsMatch) {
            throw new \RuntimeException('expected_counts_mismatch');
        }

        if (!$affectedOnlyCanonical) {
            throw new \RuntimeException('affected_rows_not_canonical_only');
        }

        if (!$affectedOnlyScope) {
            throw new \RuntimeException('affected_rows_outside_category_scope');
        }

        if (!$unresolvedNotApplied) {
            throw new \RuntimeException('unresolved_values_not_safely_excluded');
        }
    }

    private function confirmApplyAllowedByContractRuntime($runtimeMode)
    {
        if (empty($this->contract['confirmation_required'])) {
            return false;
        }

        if (!isset($this->contract['runtime_allowlist']['controlled_local_dump'])) {
            return false;
        }

        $runtime = $this->contract['runtime_allowlist']['controlled_local_dump'];

        if (empty($runtime['allow_confirm_apply'])) {
            return false;
        }

        if (!isset($runtime['runtime_mode']) || (string) $runtime['runtime_mode'] !== (string) $runtimeMode) {
            return false;
        }

        if (!empty($runtime['production_ready'])) {
            return false;
        }

        if (!empty($runtime['cache_rebuild_allowed'])) {
            return false;
        }

        return true;
    }

    private function assertStorageContract()
    {
        if ((string) $this->contract['allowed_table'] !== $this->dbPrefix . 'product_attribute') {
            throw new \RuntimeException('target_table_not_allowed');
        }

        if (!$this->sameValues($this->contract['allowed_columns'], array('product_id', 'attribute_id', 'language_id', 'text'))) {
            throw new \RuntimeException('target_columns_not_allowed');
        }
    }

    private function executeUpdates(array $updates)
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->contract['allowed_table'] . ' SET text = :text WHERE product_id = :product_id AND attribute_id = :attribute_id AND language_id = :language_id'
        );
        $count = 0;

        foreach ($updates as $row) {
            if ((int) $row['canonical_attribute_id'] !== (int) $this->contract['canonical_attribute_id']) {
                throw new \RuntimeException('update_attribute_not_canonical');
            }

            $statement->execute(array(
                ':text' => (string) $row['normalized_value'],
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => (int) $this->contract['canonical_attribute_id'],
                ':language_id' => (int) $row['language_id'],
            ));
            $count += $statement->rowCount();
        }

        return $count;
    }

    private function executeInserts(array $inserts)
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->contract['allowed_table'] . ' (product_id, attribute_id, language_id, text) VALUES (:product_id, :attribute_id, :language_id, :text)'
        );
        $count = 0;

        foreach ($inserts as $row) {
            if ((int) $row['canonical_attribute_id'] !== (int) $this->contract['canonical_attribute_id']) {
                throw new \RuntimeException('insert_attribute_not_canonical');
            }

            $statement->execute(array(
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => (int) $this->contract['canonical_attribute_id'],
                ':language_id' => (int) $row['language_id'],
                ':text' => (string) $row['normalized_value'],
            ));
            $count += $statement->rowCount();
        }

        return $count;
    }

    private function postApplyVerificationOk($actualUpdatedCount, $actualInsertedCount, array $plan, $sourceAliasRowsPreserved, $affectedOnlyCanonical, $affectedOnlyScope, $unresolvedNotApplied, $plannedCanonicalRowsVerified)
    {
        if (!$sourceAliasRowsPreserved || !$affectedOnlyCanonical || !$affectedOnlyScope || !$unresolvedNotApplied) {
            return 0;
        }

        if (!$plannedCanonicalRowsVerified) {
            return 0;
        }

        if ($actualUpdatedCount !== count($plan['updates']) || $actualInsertedCount !== count($plan['inserts'])) {
            return 0;
        }

        return 1;
    }

    private function plannedCanonicalRowsVerified(array $plan)
    {
        $plannedRows = array_merge($plan['updates'], $plan['inserts']);
        $foundRowsCount = 0;

        foreach ($plannedRows as $row) {
            $statement = $this->pdo->prepare(
                'SELECT product_id, attribute_id, language_id, text FROM ' . $this->contract['allowed_table'] . ' WHERE product_id = :product_id AND attribute_id = :attribute_id AND language_id = :language_id'
            );
            $statement->execute(array(
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => (int) $this->contract['canonical_attribute_id'],
                ':language_id' => (int) $row['language_id'],
            ));
            $canonicalRows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $foundRowsCount += count($canonicalRows);

            if (count($canonicalRows) !== 1) {
                return false;
            }

            $canonicalRow = $canonicalRows[0];

            if ((int) $canonicalRow['product_id'] !== (int) $row['product_id']) {
                return false;
            }

            if ((int) $canonicalRow['attribute_id'] !== (int) $this->contract['canonical_attribute_id']) {
                return false;
            }

            if ((int) $canonicalRow['language_id'] !== (int) $row['language_id']) {
                return false;
            }

            if (trim((string) $canonicalRow['text']) !== (string) $row['normalized_value']) {
                return false;
            }
        }

        return $foundRowsCount === count($plannedRows);
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
            'category_scope_ids' => $categoryScopeIds,
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

    private function affectedOnlyCanonicalAttribute(array $plan)
    {
        foreach ($plan['updates'] as $row) {
            if ((int) $row['canonical_attribute_id'] !== (int) $this->contract['canonical_attribute_id']) {
                return false;
            }
        }

        foreach ($plan['inserts'] as $row) {
            if ((int) $row['canonical_attribute_id'] !== (int) $this->contract['canonical_attribute_id']) {
                return false;
            }
        }

        return true;
    }

    private function affectedOnlyScope(array $plan, array $categoryScopeIds)
    {
        if (count($categoryScopeIds) === 0) {
            return false;
        }

        $affectedProductIds = array();

        foreach ($plan['updates'] as $row) {
            $affectedProductIds[(int) $row['product_id']] = true;
        }

        foreach ($plan['inserts'] as $row) {
            $affectedProductIds[(int) $row['product_id']] = true;
        }

        if (count($affectedProductIds) === 0) {
            return true;
        }

        $scopeProductIds = $this->loadScopeProductIds($categoryScopeIds);

        foreach ($affectedProductIds as $productId => $unused) {
            if (!isset($scopeProductIds[$productId])) {
                return false;
            }
        }

        return true;
    }

    private function countSourceAliasRows(array $categoryScopeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $this->contract['alias_attribute_ids'], $params);
        $sql = 'SELECT COUNT(DISTINCT CONCAT(pa.product_id, ":", pa.attribute_id, ":", pa.language_id, ":", pa.text)) AS row_count ';
        $sql .= 'FROM ' . $this->contract['allowed_table'] . ' pa ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'AND EXISTS (';
        $sql .= 'SELECT 1 FROM ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'WHERE p2c.product_id = pa.product_id ';
        $sql .= 'AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ')';
        $sql .= ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return isset($row['row_count']) ? (int) $row['row_count'] : 0;
    }

    private function loadScopeProductIds(array $categoryScopeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT DISTINCT product_id FROM ' . $this->dbPrefix . 'product_to_category ';
        $sql .= 'WHERE category_id IN (' . implode(', ', $categoryPlaceholders) . ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $productIds = array();

        foreach ($rows as $row) {
            $productIds[(int) $row['product_id']] = true;
        }

        return $productIds;
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

    private function sameValues(array $actual, array $expected)
    {
        $actual = array_values($actual);
        $expected = array_values($expected);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
