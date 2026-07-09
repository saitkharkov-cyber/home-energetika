<?php

namespace FrameworkStandardization\Apply;

use FrameworkStandardization\Preview\DbReadOnlySqlPreview;
use PDO;

final class DbControlledMaxHeadApplyCommand
{
    private $pdo;
    private $dbPrefix;
    private $sqlPreview;
    private $expectedCategoryId = 11900213;
    private $expectedCanonicalAttributeId = 12;
    private $expectedAttributeIds = array(12, 101, 119, 81);
    private $expectedCanonicalUnit = 'm';

    public function __construct(PDO $pdo, $dbPrefix, DbReadOnlySqlPreview $sqlPreview)
    {
        $this->pdo = $pdo;
        $this->dbPrefix = $dbPrefix;
        $this->sqlPreview = $sqlPreview;
    }

    public function run($runtimeConfig, array $options)
    {
        $confirmApply = !empty($options['confirm_apply']);
        $database = $runtimeConfig->getDatabase();
        $runtimeMode = $runtimeConfig->getRuntimeMode();
        $dbPrefix = $runtimeConfig->getDbPrefix();
        $controlledRuntime = $this->isControlledRuntime($runtimeMode, $database, $dbPrefix);
        $inputValid = $this->isExpectedInput($options);
        $preview = $this->sqlPreview->generate(
            $options['category_id'],
            $options['attribute_ids'],
            $options['canonical_attribute_id'],
            $options['canonical_unit']
        );
        $previewSafe = $this->isPreviewSafe($preview);
        $preflightOk = $controlledRuntime && $inputValid && $previewSafe;
        $aliasCountBefore = $this->countSourceAliasRows($preview['category_scope_ids']);
        $plan = $this->buildPlan($preview, true);
        $actualUpdatedCount = 0;
        $actualInsertedCount = 0;
        $transactionStarted = 0;
        $transactionCommitted = 0;
        $transactionRolledBack = 0;
        $rollbackReason = 'none';
        $aliasCountAfter = $aliasCountBefore;
        $sourceAliasRowsPreserved = 1;
        $affectedOnlyCanonical = $this->affectedOnlyCanonicalAttribute($plan) ? 1 : 0;
        $affectedOnlyScope = $this->affectedOnlyScope($plan, $preview['category_scope_ids']) ? 1 : 0;
        $unresolvedNotApplied = count($preview['excluded_unresolved']) === 14 ? 1 : 0;
        $postApplyVerificationOk = 0;

        if ($confirmApply && $preflightOk) {
            try {
                if ($this->pdo->inTransaction()) {
                    $rollbackReason = 'transaction_already_active';
                } elseif (!$this->pdo->beginTransaction()) {
                    $rollbackReason = 'transaction_not_available';
                } else {
                    $transactionStarted = 1;
                    $actualUpdatedCount = $this->executeUpdates($plan['updates']);
                    $actualInsertedCount = $this->executeInserts($plan['inserts']);
                    $aliasCountAfter = $this->countSourceAliasRows($preview['category_scope_ids']);
                    $sourceAliasRowsPreserved = $aliasCountBefore === $aliasCountAfter ? 1 : 0;
                    $postApplyVerificationOk = $this->postApplyVerificationOk(
                        $confirmApply,
                        $actualUpdatedCount,
                        $actualInsertedCount,
                        $plan,
                        $preview,
                        $sourceAliasRowsPreserved,
                        $affectedOnlyCanonical,
                        $affectedOnlyScope,
                        $unresolvedNotApplied
                    );

                    if ($postApplyVerificationOk) {
                        $this->pdo->commit();
                        $transactionCommitted = 1;
                    } else {
                        $this->pdo->rollBack();
                        $transactionRolledBack = 1;
                        $rollbackReason = 'post_apply_verification_failed';
                    }
                }
            } catch (\Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                    $transactionRolledBack = 1;
                }

                $rollbackReason = 'exception: ' . $e->getMessage();
                $actualUpdatedCount = 0;
                $actualInsertedCount = 0;
            }
        } else {
            $aliasCountAfter = $this->countSourceAliasRows($preview['category_scope_ids']);
            $sourceAliasRowsPreserved = $aliasCountBefore === $aliasCountAfter ? 1 : 0;
            $postApplyVerificationOk = $this->postApplyVerificationOk(
                $confirmApply,
                $actualUpdatedCount,
                $actualInsertedCount,
                $plan,
                $preview,
                $sourceAliasRowsPreserved,
                $affectedOnlyCanonical,
                $affectedOnlyScope,
                $unresolvedNotApplied
            );
        }

        $sqlApplied = ($transactionCommitted && ($actualUpdatedCount > 0 || $actualInsertedCount > 0)) ? 1 : 0;
        $productDataChanged = $sqlApplied;

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_apply_max_head',
            'dry_run' => $confirmApply ? 0 : 1,
            'confirm_apply' => $confirmApply ? 1 : 0,
            'category_scope' => (int) $options['category_id'],
            'canonical_attribute_id' => (int) $options['canonical_attribute_id'],
            'attribute_ids' => $options['attribute_ids'],
            'canonical_unit' => (string) $options['canonical_unit'],
            'target_table' => $this->dbPrefix . 'product_attribute',
            'target_columns' => array('product_id', 'attribute_id', 'language_id', 'text'),
            'update_existing_canonical_row_count' => count($plan['updates']),
            'insert_missing_canonical_row_count' => count($plan['inserts']),
            'actual_updated_count' => $actualUpdatedCount,
            'actual_inserted_count' => $actualInsertedCount,
            'already_applied_count' => $plan['already_applied_count'],
            'keep_existing_source_row_count' => (int) $preview['keep_existing_source_row_count'],
            'unresolved_excluded_count' => (int) $preview['unresolved_excluded_count'],
            'schema_blocker_count' => (int) $preview['schema_blocker_count'],
            'conflicts_count' => (int) $preview['conflicts_count'],
            'transaction_started' => $transactionStarted,
            'transaction_committed' => $transactionCommitted,
            'transaction_rolled_back' => $transactionRolledBack,
            'rollback_reason' => $rollbackReason,
            'sql_applied' => $sqlApplied,
            'product_data_changed' => $productDataChanged,
            'production_ready' => 0,
            'cache_rebuild_performed' => 0,
            'affected_only_canonical_attribute_12' => $affectedOnlyCanonical,
            'affected_only_scope_11900213' => $affectedOnlyScope,
            'source_alias_rows_preserved' => $sourceAliasRowsPreserved,
            'unresolved_not_applied' => $unresolvedNotApplied,
            'post_apply_verification_ok' => $postApplyVerificationOk,
            'preflight_ok' => $preflightOk ? 1 : 0,
            'preflight_checks' => $this->buildPreflightChecks($runtimeMode, $database, $dbPrefix, $options, $controlledRuntime, $inputValid, $previewSafe, $preview),
        );
    }

    private function buildPlan(array $preview, $skipAlreadyApplied)
    {
        $current = $this->loadCurrentCanonicalValues($preview['category_scope_ids']);
        $updates = array();
        $inserts = array();
        $alreadyAppliedCount = 0;

        foreach ($preview['actions'] as $action) {
            if ($action['preview_action'] !== 'preview_update_existing_canonical_row' && $action['preview_action'] !== 'preview_insert_missing_canonical_row') {
                continue;
            }

            if ((int) $action['canonical_attribute_id'] !== $this->expectedCanonicalAttributeId) {
                continue;
            }

            $currentKey = $this->buildCanonicalKey($action['product_id'], $action['language_id']);

            if ($skipAlreadyApplied && isset($current[$currentKey]) && (string) $current[$currentKey] === (string) $action['proposed_normalized_value']) {
                $alreadyAppliedCount++;
                continue;
            }

            if ($action['preview_action'] === 'preview_update_existing_canonical_row') {
                $updates[] = $action;
            } else {
                $inserts[] = $action;
            }
        }

        return array(
            'updates' => $updates,
            'inserts' => $inserts,
            'already_applied_count' => $alreadyAppliedCount,
        );
    }

    private function executeUpdates(array $updates)
    {
        $statement = $this->pdo->prepare(
            'UPDATE ' . $this->dbPrefix . 'product_attribute SET text = :text WHERE product_id = :product_id AND attribute_id = :attribute_id AND language_id = :language_id'
        );
        $count = 0;

        foreach ($updates as $row) {
            $statement->execute(array(
                ':text' => (string) $row['proposed_normalized_value'],
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => $this->expectedCanonicalAttributeId,
                ':language_id' => (int) $row['language_id'],
            ));
            $count += $statement->rowCount();
        }

        return $count;
    }

    private function executeInserts(array $inserts)
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ' . $this->dbPrefix . 'product_attribute (product_id, attribute_id, language_id, text) VALUES (:product_id, :attribute_id, :language_id, :text)'
        );
        $count = 0;

        foreach ($inserts as $row) {
            $statement->execute(array(
                ':product_id' => (int) $row['product_id'],
                ':attribute_id' => $this->expectedCanonicalAttributeId,
                ':language_id' => (int) $row['language_id'],
                ':text' => (string) $row['proposed_normalized_value'],
            ));
            $count += $statement->rowCount();
        }

        return $count;
    }

    private function loadCurrentCanonicalValues(array $categoryScopeIds)
    {
        $params = array(':attribute_id' => $this->expectedCanonicalAttributeId);
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.language_id, TRIM(pa.text) AS current_text ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id = :attribute_id';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $values = array();

        foreach ($rows as $row) {
            $values[$this->buildCanonicalKey($row['product_id'], $row['language_id'])] = (string) $row['current_text'];
        }

        return $values;
    }

    private function countSourceAliasRows(array $categoryScopeIds)
    {
        $aliasIds = array(101, 119, 81);
        $params = array();
        $categoryPlaceholders = $this->buildPlaceholders('category_id', $categoryScopeIds, $params);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $aliasIds, $params);
        $sql = 'SELECT COUNT(DISTINCT CONCAT(pa.product_id, ":", pa.attribute_id, ":", pa.language_id, ":", pa.text)) AS row_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
        $sql .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $categoryPlaceholders) . ') ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return isset($row['row_count']) ? (int) $row['row_count'] : 0;
    }

    private function postApplyVerificationOk($confirmApply, $actualUpdatedCount, $actualInsertedCount, array $plan, array $preview, $sourceAliasRowsPreserved, $affectedOnlyCanonical, $affectedOnlyScope, $unresolvedNotApplied)
    {
        if (!$confirmApply) {
            return ((int) $preview['schema_blocker_count'] === 0 && (int) $preview['conflicts_count'] === 0 && $sourceAliasRowsPreserved && $affectedOnlyCanonical && $affectedOnlyScope && $unresolvedNotApplied) ? 1 : 0;
        }

        if (!$sourceAliasRowsPreserved || !$affectedOnlyCanonical || !$affectedOnlyScope || !$unresolvedNotApplied) {
            return 0;
        }

        if ((int) $preview['schema_blocker_count'] !== 0 || (int) $preview['conflicts_count'] !== 0) {
            return 0;
        }

        if ($actualUpdatedCount !== count($plan['updates']) || $actualInsertedCount !== count($plan['inserts'])) {
            return 0;
        }

        return 1;
    }

    private function isControlledRuntime($runtimeMode, array $database, $dbPrefix)
    {
        return $runtimeMode === 'db_readonly'
            && isset($database['host']) && $database['host'] === '127.0.1.19'
            && isset($database['dbname']) && $database['dbname'] === 'he_framework_local_dump'
            && $dbPrefix === 'oc_';
    }

    private function isPreviewSafe(array $preview)
    {
        return $preview['storage_schema']['table_name'] === 'oc_product_attribute'
            && $this->sameAttributeIds($preview['storage_schema']['required_columns'], array('product_id', 'attribute_id', 'language_id', 'text'))
            && (int) $preview['schema_blocker_count'] === 0
            && (int) $preview['conflicts_count'] === 0
            && (int) $preview['unresolved_excluded_count'] === 14;
    }

    private function isExpectedInput(array $options)
    {
        return isset($options['category_id'])
            && (int) $options['category_id'] === $this->expectedCategoryId
            && isset($options['canonical_attribute_id'])
            && (int) $options['canonical_attribute_id'] === $this->expectedCanonicalAttributeId
            && isset($options['canonical_unit'])
            && (string) $options['canonical_unit'] === $this->expectedCanonicalUnit
            && isset($options['attribute_ids'])
            && $this->sameAttributeIds($options['attribute_ids'], $this->expectedAttributeIds);
    }

    private function affectedOnlyCanonicalAttribute(array $plan)
    {
        foreach ($plan['updates'] as $row) {
            if ((int) $row['canonical_attribute_id'] !== $this->expectedCanonicalAttributeId) {
                return false;
            }
        }

        foreach ($plan['inserts'] as $row) {
            if ((int) $row['canonical_attribute_id'] !== $this->expectedCanonicalAttributeId) {
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

    private function buildPreflightChecks($runtimeMode, array $database, $dbPrefix, array $options, $controlledRuntime, $inputValid, $previewSafe, array $preview)
    {
        return array(
            array('check' => 'runtime_is_not_production', 'status' => $controlledRuntime ? 'ok' : 'blocked'),
            array('check' => 'runtime_mode_controlled_local', 'status' => $runtimeMode === 'db_readonly' ? 'ok' : 'blocked'),
            array('check' => 'db_host_controlled', 'status' => isset($database['host']) && $database['host'] === '127.0.1.19' ? 'ok' : 'blocked'),
            array('check' => 'db_name_controlled', 'status' => isset($database['dbname']) && $database['dbname'] === 'he_framework_local_dump' ? 'ok' : 'blocked'),
            array('check' => 'db_prefix_controlled', 'status' => $dbPrefix === 'oc_' ? 'ok' : 'blocked'),
            array('check' => 'category_scope_11900213', 'status' => isset($options['category_id']) && (int) $options['category_id'] === $this->expectedCategoryId ? 'ok' : 'blocked'),
            array('check' => 'canonical_attribute_12', 'status' => isset($options['canonical_attribute_id']) && (int) $options['canonical_attribute_id'] === $this->expectedCanonicalAttributeId ? 'ok' : 'blocked'),
            array('check' => 'attribute_ids_exact', 'status' => isset($options['attribute_ids']) && $this->sameAttributeIds($options['attribute_ids'], $this->expectedAttributeIds) ? 'ok' : 'blocked'),
            array('check' => 'canonical_unit_m', 'status' => isset($options['canonical_unit']) && (string) $options['canonical_unit'] === $this->expectedCanonicalUnit ? 'ok' : 'blocked'),
            array('check' => 'schema_blocker_count_0', 'status' => (int) $preview['schema_blocker_count'] === 0 ? 'ok' : 'blocked'),
            array('check' => 'conflicts_count_0', 'status' => (int) $preview['conflicts_count'] === 0 ? 'ok' : 'blocked'),
            array('check' => 'unresolved_excluded_14', 'status' => (int) $preview['unresolved_excluded_count'] === 14 ? 'ok' : 'blocked'),
            array('check' => 'target_table_oc_product_attribute', 'status' => $preview['storage_schema']['table_name'] === 'oc_product_attribute' ? 'ok' : 'blocked'),
            array('check' => 'target_columns_exact', 'status' => $this->sameAttributeIds($preview['storage_schema']['required_columns'], array('product_id', 'attribute_id', 'language_id', 'text')) ? 'ok' : 'blocked'),
            array('check' => 'no_delete_alter_drop_truncate_create_replace', 'status' => 'ok'),
            array('check' => 'no_cache_rebuild', 'status' => 'ok'),
            array('check' => 'input_context_valid', 'status' => $inputValid ? 'ok' : 'blocked'),
            array('check' => 'preview_safe', 'status' => $previewSafe ? 'ok' : 'blocked'),
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

    private function sameAttributeIds(array $actual, array $expected)
    {
        $actual = array_values($actual);
        $expected = array_values($expected);
        sort($actual);
        sort($expected);

        return $actual === $expected;
    }
}
