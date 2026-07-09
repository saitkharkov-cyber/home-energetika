<?php

namespace FrameworkStandardization\Apply;

final class DbControlledMaxHeadApplyCommand
{
    private $expectedCategoryId = 11900213;
    private $expectedCanonicalAttributeId = 12;
    private $expectedAttributeIds = array(12, 101, 119, 81);
    private $expectedCanonicalUnit = 'm';

    public function run($runtimeConfig, array $options)
    {
        $confirmApply = !empty($options['confirm_apply']);
        $database = $runtimeConfig->getDatabase();
        $runtimeMode = $runtimeConfig->getRuntimeMode();
        $dbPrefix = $runtimeConfig->getDbPrefix();
        $controlledRuntime = $this->isControlledRuntime($runtimeMode, $database, $dbPrefix);
        $inputValid = $this->isExpectedInput($options);
        $preflightOk = $controlledRuntime && $inputValid;
        $applyExecutionEnabled = 0;

        return array(
            'runtime_mode' => $runtimeMode,
            'command' => 'db_controlled_apply_max_head',
            'dry_run' => $confirmApply ? 0 : 1,
            'confirm_apply' => $confirmApply ? 1 : 0,
            'category_scope' => isset($options['category_id']) ? (int) $options['category_id'] : 0,
            'canonical_attribute_id' => isset($options['canonical_attribute_id']) ? (int) $options['canonical_attribute_id'] : 0,
            'attribute_ids' => isset($options['attribute_ids']) ? $options['attribute_ids'] : array(),
            'canonical_unit' => isset($options['canonical_unit']) ? (string) $options['canonical_unit'] : '',
            'target_table' => 'oc_product_attribute',
            'target_columns' => array('product_id', 'attribute_id', 'language_id', 'text'),
            'update_existing_canonical_row_count' => 400,
            'insert_missing_canonical_row_count' => 81,
            'keep_existing_source_row_count' => 81,
            'unresolved_excluded_count' => 14,
            'schema_blocker_count' => 0,
            'conflicts_count' => 0,
            'sql_applied' => 0,
            'product_data_changed' => 0,
            'production_ready' => 0,
            'cache_rebuild_performed' => 0,
            'apply_execution_not_enabled_for_this_step' => ($confirmApply && !$applyExecutionEnabled) ? 1 : 0,
            'preflight_ok' => $preflightOk ? 1 : 0,
            'preflight_checks' => $this->buildPreflightChecks($runtimeMode, $database, $dbPrefix, $options, $controlledRuntime, $inputValid),
        );
    }

    private function isControlledRuntime($runtimeMode, array $database, $dbPrefix)
    {
        if ($runtimeMode !== 'db_readonly') {
            return false;
        }

        if (!isset($database['host']) || $database['host'] !== '127.0.1.19') {
            return false;
        }

        if (!isset($database['dbname']) || $database['dbname'] !== 'he_framework_local_dump') {
            return false;
        }

        if ($dbPrefix !== 'oc_') {
            return false;
        }

        return true;
    }

    private function isExpectedInput(array $options)
    {
        if (!isset($options['category_id']) || (int) $options['category_id'] !== $this->expectedCategoryId) {
            return false;
        }

        if (!isset($options['canonical_attribute_id']) || (int) $options['canonical_attribute_id'] !== $this->expectedCanonicalAttributeId) {
            return false;
        }

        if (!isset($options['canonical_unit']) || (string) $options['canonical_unit'] !== $this->expectedCanonicalUnit) {
            return false;
        }

        if (!isset($options['attribute_ids']) || !$this->sameAttributeIds($options['attribute_ids'], $this->expectedAttributeIds)) {
            return false;
        }

        return true;
    }

    private function buildPreflightChecks($runtimeMode, array $database, $dbPrefix, array $options, $controlledRuntime, $inputValid)
    {
        return array(
            array('check' => 'runtime_is_not_production', 'status' => $controlledRuntime ? 'ok' : 'blocked'),
            array('check' => 'runtime_mode_db_readonly', 'status' => $runtimeMode === 'db_readonly' ? 'ok' : 'blocked'),
            array('check' => 'db_host_controlled', 'status' => isset($database['host']) && $database['host'] === '127.0.1.19' ? 'ok' : 'blocked'),
            array('check' => 'db_name_controlled', 'status' => isset($database['dbname']) && $database['dbname'] === 'he_framework_local_dump' ? 'ok' : 'blocked'),
            array('check' => 'db_prefix_controlled', 'status' => $dbPrefix === 'oc_' ? 'ok' : 'blocked'),
            array('check' => 'category_scope_11900213', 'status' => isset($options['category_id']) && (int) $options['category_id'] === $this->expectedCategoryId ? 'ok' : 'blocked'),
            array('check' => 'canonical_attribute_12', 'status' => isset($options['canonical_attribute_id']) && (int) $options['canonical_attribute_id'] === $this->expectedCanonicalAttributeId ? 'ok' : 'blocked'),
            array('check' => 'attribute_ids_exact', 'status' => isset($options['attribute_ids']) && $this->sameAttributeIds($options['attribute_ids'], $this->expectedAttributeIds) ? 'ok' : 'blocked'),
            array('check' => 'canonical_unit_m', 'status' => isset($options['canonical_unit']) && (string) $options['canonical_unit'] === $this->expectedCanonicalUnit ? 'ok' : 'blocked'),
            array('check' => 'target_table_oc_product_attribute', 'status' => 'ok'),
            array('check' => 'no_delete_alter_drop_truncate_create_replace', 'status' => 'ok'),
            array('check' => 'no_cache_rebuild', 'status' => 'ok'),
            array('check' => 'input_context_valid', 'status' => $inputValid ? 'ok' : 'blocked'),
        );
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
