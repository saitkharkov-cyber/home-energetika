<?php

namespace FrameworkStandardization\Apply;

use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use PDO;

final class DbControlledAliasCleanupCommand
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

        $beforePlan = $this->buildPlan();
        $afterPlan = $beforePlan;
        $actualDeletedCount = 0;
        $transactionStarted = 0;
        $transactionCommitted = 0;
        $transactionRolledBack = 0;
        $rollbackReason = 'none';

        if ($confirmApply) {
            try {
                if ($this->pdo->inTransaction()) {
                    $rollbackReason = 'transaction_already_active';
                } elseif (!$this->pdo->beginTransaction()) {
                    $rollbackReason = 'transaction_not_available';
                } else {
                    $transactionStarted = 1;
                    $actualDeletedCount = $this->executeDeletes($beforePlan['delete_rows']);
                    $afterPlan = $this->buildPlan();

                    if ($this->verifyConfirmApply($beforePlan, $afterPlan, $actualDeletedCount)) {
                        $this->pdo->commit();
                        $transactionCommitted = 1;
                    } else {
                        $this->pdo->rollBack();
                        $transactionRolledBack = 1;
                        $rollbackReason = 'post_cleanup_verification_failed';
                        $afterPlan = $beforePlan;
                    }
                }
            } catch (\Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                    $transactionRolledBack = 1;
                }

                $rollbackReason = 'exception: ' . $e->getMessage();
                $actualDeletedCount = 0;
                $afterPlan = $beforePlan;
            }
        }

        $deleteExecuted = ($transactionCommitted && $actualDeletedCount > 0) ? 1 : 0;
        $postCleanupVerificationOk = $confirmApply
            ? $this->verifyConfirmApply($beforePlan, $afterPlan, $actualDeletedCount)
            : $this->verifyDryRun($beforePlan);

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_alias_cleanup',
            'target_key' => (string) $this->contract['target_key'],
            'target_meaning' => (string) $this->contract['target_meaning'],
            'dry_run' => $confirmApply ? 0 : 1,
            'confirm_apply' => $confirmApply ? 1 : 0,
            'category_scope' => (int) $this->contract['category_scope_id'],
            'canonical_attribute_id' => (int) $this->contract['canonical_attribute_id'],
            'alias_attribute_ids' => $this->contract['alias_attribute_ids'],
            'normalizer_key' => (string) $this->contract['normalizer_key'],
            'target_table' => $this->dbPrefix . 'product_attribute',
            'planned_delete_count' => count($beforePlan['delete_rows']),
            'planned_keep_alias_count' => count($beforePlan['keep_rows']),
            'actual_deleted_count' => $actualDeletedCount,
            'remaining_alias_rows' => $afterPlan['total_alias_rows'],
            'remaining_not_removable_rows' => count($afterPlan['keep_rows']),
            'expected_alias_delete_count' => (int) $this->contract['historical_alias_delete_count'],
            'expected_remaining_alias_rows' => (int) $this->contract['historical_alias_remaining_count'],
            'not_removed_unresolved_or_excluded_count' => $afterPlan['reason_counts']['unresolved_or_excluded_value'],
            'expected_counts_match' => $this->expectedCountsMatch($afterPlan) ? 1 : 0,
            'transaction_started' => $transactionStarted,
            'transaction_committed' => $transactionCommitted,
            'transaction_rolled_back' => $transactionRolledBack,
            'rollback_reason' => $rollbackReason,
            'post_cleanup_verification_ok' => $postCleanupVerificationOk ? 1 : 0,
            'breakdown_before' => array_values($beforePlan['breakdown']),
            'breakdown_after' => array_values($afterPlan['breakdown']),
            'safety_markers' => array(
                'db_controlled' => 1,
                'delete_executed' => $deleteExecuted,
                'sql_applied' => $deleteExecuted,
                'product_data_changed' => $deleteExecuted,
                'production_ready' => 0,
                'cache_rebuild_performed' => 0,
                'touches_oc_attribute' => 0,
                'touches_oc_attribute_description' => 0,
                'canonical_rows_deleted' => 0,
            ),
        );
    }

    private function buildPlan()
    {
        $categoryScopeIds = $this->loadCategoryScopeIds();
        $scopeProductIds = $this->loadScopeProductIds($categoryScopeIds);
        $canonicalRows = $this->loadCanonicalRows($categoryScopeIds);
        $aliasRows = $this->loadAliasRows($categoryScopeIds);
        $deleteRows = array();
        $keepRows = array();
        $breakdown = $this->createEmptyBreakdown();
        $reasonCounts = $this->createEmptyReasonCounts();

        foreach ($aliasRows as $row) {
            $attributeId = (int) $row['attribute_id'];
            $breakdown[$attributeId]['total_rows']++;
            $classification = $this->classifyAliasRow($row, $canonicalRows, $scopeProductIds);

            if ($classification['safe']) {
                $breakdown[$attributeId]['safely_removable']++;
                $deleteRows[] = $row;
            } else {
                $breakdown[$attributeId]['not_removable']++;
                $reasonCounts[$classification['reason']]++;
                $keepRows[] = $row;
            }
        }

        return array(
            'delete_rows' => $deleteRows,
            'keep_rows' => $keepRows,
            'total_alias_rows' => count($aliasRows),
            'breakdown' => $breakdown,
            'reason_counts' => $reasonCounts,
        );
    }

    private function classifyAliasRow(array $row, array $canonicalRows, array $scopeProductIds)
    {
        $productId = (int) $row['product_id'];
        $languageId = (int) $row['language_id'];
        $key = $this->buildCanonicalKey($productId, $languageId);

        if (!isset($scopeProductIds[$productId])) {
            return array('safe' => false, 'reason' => 'product_outside_scope');
        }

        $normalized = $this->normalizer->normalize((string) $row['text']);

        if ($normalized['normalized_value'] === null) {
            return array('safe' => false, 'reason' => 'unresolved_or_excluded_value');
        }

        if ((int) $row['exact_row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case');
        }

        if (!isset($canonicalRows[$key])) {
            return array('safe' => false, 'reason' => 'missing_canonical_row');
        }

        if ($canonicalRows[$key]['row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case');
        }

        if ((string) $normalized['normalized_value'] !== (string) $canonicalRows[$key]['text']) {
            return array('safe' => false, 'reason' => 'canonical_value_mismatch');
        }

        return array('safe' => true, 'reason' => 'covered_by_canonical_row');
    }

    private function executeDeletes(array $deleteRows)
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM ' . $this->dbPrefix . 'product_attribute WHERE product_id = :product_id AND attribute_id = :attribute_id AND language_id = :language_id AND text = :text LIMIT 1'
        );
        $count = 0;

        foreach ($deleteRows as $row) {
            $statement->execute(array(
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => (int) $row['attribute_id'],
                ':language_id' => (int) $row['language_id'],
                ':text' => (string) $row['text'],
            ));
            $count += $statement->rowCount();
        }

        return $count;
    }

    private function verifyDryRun(array $plan)
    {
        return $this->expectedCountsMatch($plan)
            && count($plan['delete_rows']) === 0
            && count($plan['keep_rows']) === (int) $this->contract['expected_alias_not_removable_after_cleanup'];
    }

    private function verifyConfirmApply(array $beforePlan, array $afterPlan, $actualDeletedCount)
    {
        if ($actualDeletedCount !== count($beforePlan['delete_rows'])) {
            return false;
        }

        return $this->expectedCountsMatch($afterPlan);
    }

    private function expectedCountsMatch(array $plan)
    {
        return $plan['total_alias_rows'] === (int) $this->contract['expected_alias_total_rows_after_cleanup']
            && count($plan['delete_rows']) === (int) $this->contract['expected_alias_safely_removable_after_cleanup']
            && count($plan['keep_rows']) === (int) $this->contract['expected_alias_not_removable_after_cleanup']
            && $plan['reason_counts']['unresolved_or_excluded_value'] === (int) $this->contract['expected_alias_unresolved_or_excluded_after_cleanup'];
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

    private function loadCanonicalRows(array $categoryScopeIds)
    {
        $params = array(':attribute_id' => (int) $this->contract['canonical_attribute_id']);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT pa.product_id, pa.language_id, TRIM(pa.text) AS text, COUNT(*) AS row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id ';
        $sql .= 'GROUP BY pa.product_id, pa.language_id, TRIM(pa.text)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $canonicalRows = array();

        foreach ($rows as $row) {
            $key = $this->buildCanonicalKey($row['product_id'], $row['language_id']);

            if (!isset($canonicalRows[$key])) {
                $canonicalRows[$key] = array(
                    'text' => (string) $row['text'],
                    'row_count' => (int) $row['row_count'],
                );
                continue;
            }

            $canonicalRows[$key]['row_count'] += (int) $row['row_count'];
        }

        return $canonicalRows;
    }

    private function loadAliasRows(array $categoryScopeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $this->contract['alias_attribute_ids'], $params);
        $sql = 'SELECT pa.product_id, pa.attribute_id, pa.language_id, pa.text, COUNT(*) AS exact_row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'GROUP BY pa.product_id, pa.attribute_id, pa.language_id, pa.text ';
        $sql .= 'ORDER BY pa.attribute_id, pa.product_id, pa.language_id, pa.text';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createEmptyBreakdown()
    {
        $breakdown = array();

        foreach ($this->contract['alias_attribute_ids'] as $attributeId) {
            $breakdown[(int) $attributeId] = array(
                'alias_attribute_id' => (int) $attributeId,
                'total_rows' => 0,
                'safely_removable' => 0,
                'not_removable' => 0,
            );
        }

        return $breakdown;
    }

    private function createEmptyReasonCounts()
    {
        return array(
            'missing_canonical_row' => 0,
            'canonical_value_mismatch' => 0,
            'unresolved_or_excluded_value' => 0,
            'product_outside_scope' => 0,
            'duplicate_or_conflict_case' => 0,
        );
    }

    private function buildCanonicalKey($productId, $languageId)
    {
        return (int) $productId . '|' . (int) $languageId;
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
