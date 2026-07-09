<?php

namespace FrameworkStandardization\Apply;

use PDO;

final class DbControlledAliasCleanupMaxHeadCommand
{
    private $pdo;
    private $dbPrefix;
    private $categoryId = 11900213;
    private $canonicalAttributeId = 12;
    private $aliasAttributeIds = array(101, 119, 81);
    private $expectedDeleteCount = 81;
    private $expectedRemainingAliasRows = 14;

    public function __construct(PDO $pdo, $dbPrefix)
    {
        $this->pdo = $pdo;
        $this->dbPrefix = $dbPrefix;
    }

    public function run($runtimeConfig, $confirmApply)
    {
        $database = $runtimeConfig->getDatabase();
        $runtimeMode = $runtimeConfig->getRuntimeMode();
        $controlledRuntime = $this->isControlledRuntime($runtimeMode, $database, $runtimeConfig->getDbPrefix());

        if (!$controlledRuntime) {
            throw new \RuntimeException('runtime_not_allowed_for_alias_cleanup');
        }

        $beforePlan = $this->buildPlan();
        $actualDeletedCount = 0;
        $transactionStarted = 0;
        $transactionCommitted = 0;
        $transactionRolledBack = 0;
        $rollbackReason = 'none';
        $afterPlan = $beforePlan;
        $postCleanupVerificationOk = $confirmApply ? 0 : $this->verifyDryRun($beforePlan);

        if ($confirmApply && $controlledRuntime) {
            try {
                if ($this->pdo->inTransaction()) {
                    $rollbackReason = 'transaction_already_active';
                } elseif (!$this->pdo->beginTransaction()) {
                    $rollbackReason = 'transaction_not_available';
                } else {
                    $transactionStarted = 1;
                    $actualDeletedCount = $this->executeDeletes($beforePlan['delete_rows']);
                    $afterPlan = $this->buildPlan();
                    $postCleanupVerificationOk = $this->verifyConfirmApply($beforePlan, $afterPlan, $actualDeletedCount);

                    if ($postCleanupVerificationOk) {
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

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_alias_cleanup_max_head',
            'dry_run' => $confirmApply ? 0 : 1,
            'confirm_apply' => $confirmApply ? 1 : 0,
            'category_scope' => $this->categoryId,
            'canonical_attribute_id' => $this->canonicalAttributeId,
            'alias_attribute_ids' => $this->aliasAttributeIds,
            'target_table' => $this->dbPrefix . 'product_attribute',
            'planned_delete_count' => count($beforePlan['delete_rows']),
            'planned_keep_alias_count' => count($beforePlan['keep_rows']),
            'actual_deleted_count' => $actualDeletedCount,
            'remaining_alias_rows' => $afterPlan['total_alias_rows'],
            'remaining_not_removable_rows' => count($afterPlan['keep_rows']),
            'expected_delete_count' => $this->expectedDeleteCount,
            'expected_remaining_alias_rows' => $this->expectedRemainingAliasRows,
            'not_removed_unresolved_or_excluded_count' => $afterPlan['reason_counts']['unresolved_or_excluded_value'],
            'breakdown_before' => array_values($beforePlan['breakdown']),
            'breakdown_after' => array_values($afterPlan['breakdown']),
            'transaction_started' => $transactionStarted,
            'transaction_committed' => $transactionCommitted,
            'transaction_rolled_back' => $transactionRolledBack,
            'rollback_reason' => $rollbackReason,
            'post_cleanup_verification_ok' => $postCleanupVerificationOk,
            'preflight_ok' => $controlledRuntime ? 1 : 0,
            'safety_markers' => array(
                'db_controlled' => $controlledRuntime ? 1 : 0,
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

        $normalizedAlias = $this->normalizeSimpleMeters((string) $row['text']);

        if ($normalizedAlias === null) {
            return array('safe' => false, 'reason' => 'unresolved_or_excluded_value');
        }

        if ((int) $row['exact_row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case');
        }

        if (!isset($canonicalRows[$key])) {
            return array('safe' => false, 'reason' => 'missing_canonical_row_12');
        }

        if ($canonicalRows[$key]['row_count'] !== 1) {
            return array('safe' => false, 'reason' => 'duplicate_or_conflict_case');
        }

        if ((string) $normalizedAlias !== (string) $canonicalRows[$key]['text']) {
            return array('safe' => false, 'reason' => 'canonical_value_mismatch');
        }

        return array('safe' => true, 'reason' => 'covered_by_canonical_row_12');
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
        $pendingCleanupState = count($plan['delete_rows']) === $this->expectedDeleteCount
            && count($plan['keep_rows']) === $this->expectedRemainingAliasRows
            && $plan['total_alias_rows'] === ($this->expectedDeleteCount + $this->expectedRemainingAliasRows);

        $alreadyCleanedState = count($plan['delete_rows']) === 0
            && count($plan['keep_rows']) === $this->expectedRemainingAliasRows
            && $plan['total_alias_rows'] === $this->expectedRemainingAliasRows;

        return ($pendingCleanupState || $alreadyCleanedState) ? 1 : 0;
    }

    private function verifyConfirmApply(array $beforePlan, array $afterPlan, $actualDeletedCount)
    {
        $plannedDeleteCount = count($beforePlan['delete_rows']);

        if ($plannedDeleteCount !== $this->expectedDeleteCount && $plannedDeleteCount !== 0) {
            return 0;
        }

        if ($actualDeletedCount !== $plannedDeleteCount) {
            return 0;
        }

        if ($afterPlan['total_alias_rows'] !== $this->expectedRemainingAliasRows) {
            return 0;
        }

        if (count($afterPlan['delete_rows']) !== 0) {
            return 0;
        }

        if (count($afterPlan['keep_rows']) !== $this->expectedRemainingAliasRows) {
            return 0;
        }

        if ($afterPlan['reason_counts']['unresolved_or_excluded_value'] !== $this->expectedRemainingAliasRows) {
            return 0;
        }

        return 1;
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

        $scope = array($this->categoryId => true);
        $queue = array($this->categoryId);

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
        $params = array(':attribute_id' => $this->canonicalAttributeId);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.language_id, TRIM(pa.text) AS text ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $canonicalRows = array();

        foreach ($rows as $row) {
            $key = $this->buildCanonicalKey($row['product_id'], $row['language_id']);

            if (!isset($canonicalRows[$key])) {
                $canonicalRows[$key] = array(
                    'text' => (string) $row['text'],
                    'row_count' => 1,
                );
                continue;
            }

            $canonicalRows[$key]['row_count']++;
        }

        return $canonicalRows;
    }

    private function loadAliasRows(array $categoryScopeIds)
    {
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $this->aliasAttributeIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.attribute_id, pa.language_id, pa.text, ';
        $sql .= '(SELECT COUNT(*) FROM ' . $this->dbPrefix . 'product_attribute pa2 ';
        $sql .= 'WHERE pa2.product_id = pa.product_id AND pa2.attribute_id = pa.attribute_id ';
        $sql .= 'AND pa2.language_id = pa.language_id AND pa2.text = pa.text) AS exact_row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'ORDER BY pa.attribute_id, pa.product_id, pa.language_id, pa.text';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeSimpleMeters($rawValue)
    {
        $value = trim($rawValue);

        if ($value === '') {
            return null;
        }

        if (preg_match('/[a-zA-Zа-яА-Я]/u', str_replace(array('м', 'М', 'm', 'M'), '', $value))) {
            return null;
        }

        if (preg_match('/^\s*до\s+/ui', $value)) {
            return null;
        }

        if (preg_match('/[–—-]/u', $value)) {
            return null;
        }

        preg_match_all('/[0-9]+(?:[\.,][0-9]+)?/u', $value, $matches);

        if (count($matches[0]) !== 1) {
            return null;
        }

        $number = str_replace(',', '.', $matches[0][0]);

        if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $number)) {
            return null;
        }

        $float = (float) $number;

        if ((string) (int) $float === $number) {
            return (string) (int) $float;
        }

        return rtrim(rtrim(sprintf('%.6F', $float), '0'), '.');
    }

    private function isControlledRuntime($runtimeMode, array $database, $dbPrefix)
    {
        return $runtimeMode === 'db_readonly'
            && isset($database['host']) && $database['host'] === '127.0.1.19'
            && isset($database['dbname']) && $database['dbname'] === 'he_framework_local_dump'
            && $dbPrefix === 'oc_';
    }

    private function createEmptyBreakdown()
    {
        $breakdown = array();

        foreach ($this->aliasAttributeIds as $attributeId) {
            $breakdown[$attributeId] = array(
                'alias_attribute_id' => $attributeId,
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
            'missing_canonical_row_12' => 0,
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
