<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Apply\DbControlledMaxHeadApplyCommand;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-controlled-apply-max-head.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-controlled-apply-max-head.php path/to/runtime.php --category-id=11900213 --canonical-attribute-id=12 --attribute-ids=12,101,119,81 --canonical-unit=m [--confirm-apply] [--format=plain|markdown]');
    }

    $runtimeFile = $argv[1];
    $options = parseCliOptions($argv);

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    $command = new DbControlledMaxHeadApplyCommand();
    $result = $command->run($runtimeConfig, $options);

    if ($options['format'] === 'markdown') {
        printResultMarkdown($result);
    } else {
        printResultPlain($result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'controlled_apply_max_head_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $options = array(
        'category_id' => null,
        'canonical_attribute_id' => null,
        'attribute_ids' => null,
        'canonical_unit' => null,
        'confirm_apply' => 0,
        'format' => 'plain',
    );

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '') {
            continue;
        }

        if ($arg === '--confirm-apply') {
            $options['confirm_apply'] = 1;
            continue;
        }

        if (strpos($arg, '--category-id=') === 0) {
            $options['category_id'] = parsePositiveInt(substr($arg, strlen('--category-id=')), 'invalid_category_id');
            continue;
        }

        if (strpos($arg, '--canonical-attribute-id=') === 0) {
            $options['canonical_attribute_id'] = parsePositiveInt(substr($arg, strlen('--canonical-attribute-id=')), 'invalid_canonical_attribute_id');
            continue;
        }

        if (strpos($arg, '--attribute-ids=') === 0) {
            $options['attribute_ids'] = parseAttributeIds(substr($arg, strlen('--attribute-ids=')));
            continue;
        }

        if (strpos($arg, '--canonical-unit=') === 0) {
            $options['canonical_unit'] = substr($arg, strlen('--canonical-unit='));
            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $format = substr($arg, strlen('--format='));

            if ($format !== 'plain' && $format !== 'markdown') {
                throw new \InvalidArgumentException('unsupported_format');
            }

            $options['format'] = $format;
            continue;
        }

        throw new \InvalidArgumentException('unexpected_cli_argument');
    }

    if ($options['category_id'] === null) {
        throw new \InvalidArgumentException('category_id_required');
    }

    if ($options['canonical_attribute_id'] === null) {
        throw new \InvalidArgumentException('canonical_attribute_id_required');
    }

    if ($options['attribute_ids'] === null) {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    if ($options['canonical_unit'] === null || $options['canonical_unit'] === '') {
        throw new \InvalidArgumentException('canonical_unit_required');
    }

    return $options;
}

function parsePositiveInt($value, $error)
{
    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        throw new \InvalidArgumentException($error);
    }

    return (int) $value;
}

function parseAttributeIds($value)
{
    if ($value === '') {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    $parts = explode(',', $value);
    $attributeIds = array();

    foreach ($parts as $part) {
        $part = trim($part);

        if (!preg_match('/^[1-9][0-9]*$/', $part)) {
            throw new \InvalidArgumentException('invalid_attribute_ids');
        }

        $id = (int) $part;

        if (!in_array($id, $attributeIds, true)) {
            $attributeIds[] = $id;
        }
    }

    return $attributeIds;
}

function printResultPlain(array $result)
{
    echo "runtime_mode: " . $result['runtime_mode'] . "\n";
    echo "command: " . $result['command'] . "\n";
    echo "dry_run: " . $result['dry_run'] . "\n";
    echo "confirm_apply: " . $result['confirm_apply'] . "\n";
    echo "category_scope: " . $result['category_scope'] . "\n";
    echo "canonical_attribute_id: " . $result['canonical_attribute_id'] . "\n";
    echo "attribute_ids: " . implode(',', $result['attribute_ids']) . "\n";
    echo "canonical_unit: " . $result['canonical_unit'] . "\n";
    echo "target_table: " . $result['target_table'] . "\n";
    echo "target_columns: " . implode(',', $result['target_columns']) . "\n";
    echo "update_existing_canonical_row_count: " . $result['update_existing_canonical_row_count'] . "\n";
    echo "insert_missing_canonical_row_count: " . $result['insert_missing_canonical_row_count'] . "\n";
    echo "keep_existing_source_row_count: " . $result['keep_existing_source_row_count'] . "\n";
    echo "unresolved_excluded_count: " . $result['unresolved_excluded_count'] . "\n";
    echo "schema_blocker_count: " . $result['schema_blocker_count'] . "\n";
    echo "conflicts_count: " . $result['conflicts_count'] . "\n";
    echo "preflight_ok: " . $result['preflight_ok'] . "\n";
    echo "sql_applied: " . $result['sql_applied'] . "\n";
    echo "product_data_changed: " . $result['product_data_changed'] . "\n";
    echo "production_ready: " . $result['production_ready'] . "\n";
    echo "cache_rebuild_performed: " . $result['cache_rebuild_performed'] . "\n";
    echo "apply_execution_not_enabled_for_this_step: " . $result['apply_execution_not_enabled_for_this_step'] . "\n";
    echo "\npreflight_checks:\n";

    foreach ($result['preflight_checks'] as $check) {
        echo "- " . $check['check'] . ": " . $check['status'] . "\n";
    }
}

function printResultMarkdown(array $result)
{
    echo "# Controlled apply max head\n\n";
    echo "- runtime_mode: " . markdownCell($result['runtime_mode']) . "\n";
    echo "- command: " . markdownCell($result['command']) . "\n";
    echo "- dry_run: " . markdownCell($result['dry_run']) . "\n";
    echo "- confirm_apply: " . markdownCell($result['confirm_apply']) . "\n";
    echo "- category_scope: " . markdownCell($result['category_scope']) . "\n";
    echo "- canonical_attribute_id: " . markdownCell($result['canonical_attribute_id']) . "\n";
    echo "- attribute_ids: " . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo "- canonical_unit: " . markdownCell($result['canonical_unit']) . "\n\n";
    echo "## Summary\n\n";
    echo "- update_existing_canonical_row_count: " . $result['update_existing_canonical_row_count'] . "\n";
    echo "- insert_missing_canonical_row_count: " . $result['insert_missing_canonical_row_count'] . "\n";
    echo "- keep_existing_source_row_count: " . $result['keep_existing_source_row_count'] . "\n";
    echo "- unresolved_excluded_count: " . $result['unresolved_excluded_count'] . "\n";
    echo "- schema_blocker_count: " . $result['schema_blocker_count'] . "\n";
    echo "- conflicts_count: " . $result['conflicts_count'] . "\n";
    echo "- preflight_ok: " . $result['preflight_ok'] . "\n";
    echo "- sql_applied: " . $result['sql_applied'] . "\n";
    echo "- product_data_changed: " . $result['product_data_changed'] . "\n";
    echo "- production_ready: " . $result['production_ready'] . "\n";
    echo "- cache_rebuild_performed: " . $result['cache_rebuild_performed'] . "\n";
    echo "- apply_execution_not_enabled_for_this_step: " . $result['apply_execution_not_enabled_for_this_step'] . "\n\n";
    echo "## Preflight checks\n\n";
    echo "| check | status |\n";
    echo "| --- | --- |\n";

    foreach ($result['preflight_checks'] as $check) {
        echo "| " . markdownCell($check['check']) . " | " . markdownCell($check['status']) . " |\n";
    }
}

function markdownCell($value)
{
    $value = (string) $value;
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
    $value = str_replace('|', '\\|', $value);

    if ($value === '') {
        return 'none';
    }

    return $value;
}
