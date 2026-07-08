<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Preview\DbReadOnlySqlPreview;
use FrameworkStandardization\Proposals\DbReadOnlyNormalizationProposals;
use FrameworkStandardization\Review\DbReadOnlyNormalizationReviewChain;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-sql-preview.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-sql-preview.php path/to/runtime.php --category-id=ID --attribute-ids=1,2 --canonical-attribute-id=ID --canonical-unit=m [--format=plain|markdown]');
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
    assertLocalRuntime($runtimeConfig);

    $db = new PdoReadOnlyDbConnection(createPdo($runtimeConfig));
    $proposalGenerator = new DbReadOnlyNormalizationProposals($db, $runtimeConfig->getDbPrefix(), 1);
    $reviewChain = new DbReadOnlyNormalizationReviewChain($proposalGenerator);
    $preview = new DbReadOnlySqlPreview($db, $runtimeConfig->getDbPrefix(), $reviewChain);
    $result = $preview->generate(
        $options['category_id'],
        $options['attribute_ids'],
        $options['canonical_attribute_id'],
        $options['canonical_unit']
    );

    if ($options['format'] === 'markdown') {
        printSqlPreviewMarkdown($result, $options['format']);
    } else {
        printSqlPreviewPlain($result, $options['format']);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'sql_preview_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $categoryId = null;
    $attributeIds = null;
    $canonicalAttributeId = null;
    $canonicalUnit = null;
    $format = 'plain';

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '') {
            continue;
        }

        if (strpos($arg, '--category-id=') === 0) {
            $categoryId = parsePositiveInt(substr($arg, strlen('--category-id=')), 'invalid_category_id');
            continue;
        }

        if (strpos($arg, '--attribute-ids=') === 0) {
            $attributeIds = parseAttributeIds(substr($arg, strlen('--attribute-ids=')));
            continue;
        }

        if (strpos($arg, '--canonical-attribute-id=') === 0) {
            $canonicalAttributeId = parsePositiveInt(substr($arg, strlen('--canonical-attribute-id=')), 'invalid_canonical_attribute_id');
            continue;
        }

        if (strpos($arg, '--canonical-unit=') === 0) {
            $canonicalUnit = substr($arg, strlen('--canonical-unit='));

            if ($canonicalUnit !== 'm') {
                throw new \InvalidArgumentException('unsupported_canonical_unit');
            }

            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $formatValue = substr($arg, strlen('--format='));

            if ($formatValue !== 'plain' && $formatValue !== 'markdown') {
                throw new \InvalidArgumentException('unsupported_format');
            }

            $format = $formatValue;
            continue;
        }

        throw new \InvalidArgumentException('unexpected_cli_argument');
    }

    if ($categoryId === null) {
        throw new \InvalidArgumentException('invalid_category_id');
    }

    if ($attributeIds === null) {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    if ($canonicalAttributeId === null) {
        throw new \InvalidArgumentException('invalid_canonical_attribute_id');
    }

    if (!in_array($canonicalAttributeId, $attributeIds, true)) {
        throw new \InvalidArgumentException('canonical_attribute_id_not_in_attribute_ids');
    }

    if ($canonicalUnit === null) {
        throw new \InvalidArgumentException('unsupported_canonical_unit');
    }

    return array(
        'category_id' => $categoryId,
        'attribute_ids' => $attributeIds,
        'canonical_attribute_id' => $canonicalAttributeId,
        'canonical_unit' => $canonicalUnit,
        'format' => $format,
    );
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

    if (count($attributeIds) === 0) {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    return $attributeIds;
}

function assertLocalRuntime(OpenCartRuntimeConfig $runtimeConfig)
{
    $database = $runtimeConfig->getDatabase();

    if ($runtimeConfig->getRuntimeMode() !== 'db_readonly') {
        throw new \InvalidArgumentException('runtime_mode_not_db_readonly');
    }

    if (!isset($database['driver']) || $database['driver'] !== 'pdo_mysql') {
        throw new \InvalidArgumentException('runtime_driver_not_supported');
    }

    if (!isset($database['host']) || $database['host'] !== '127.0.1.19') {
        throw new \InvalidArgumentException('runtime_host_not_allowed');
    }

    if (!isset($database['dbname']) || $database['dbname'] !== 'he_framework_local_dump') {
        throw new \InvalidArgumentException('runtime_dbname_not_allowed');
    }

    if ($runtimeConfig->getDbPrefix() !== 'oc_') {
        throw new \InvalidArgumentException('runtime_db_prefix_not_allowed');
    }
}

function createPdo(OpenCartRuntimeConfig $runtimeConfig)
{
    $database = $runtimeConfig->getDatabase();
    $dsn = 'mysql:host=' . $database['host'];
    $dsn .= ';port=' . $database['port'];
    $dsn .= ';dbname=' . $database['dbname'];
    $dsn .= ';charset=' . $database['charset'];

    $pdo = new \PDO($dsn, $database['username'], $database['password']);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function printSqlPreviewPlain(array $result, $format)
{
    echo "runtime_mode: db_readonly\n";
    echo "command: sql_preview\n";
    echo 'category_scope: ' . $result['category_id'] . "\n";
    echo 'attribute_ids: ' . implode(',', $result['attribute_ids']) . "\n";
    echo 'canonical_attribute_id: ' . $result['canonical_attribute_id'] . "\n";
    echo 'canonical_unit: ' . $result['canonical_unit'] . "\n";
    echo 'format: ' . $format . "\n";
    echo "\n";
    echo "storage_schema:\n";
    echo '  table_name: ' . $result['storage_schema']['table_name'] . "\n";
    echo '  relevant_columns: ' . implode(',', $result['storage_schema']['relevant_columns']) . "\n";
    echo '  schema_status: ' . $result['storage_schema']['schema_status'] . "\n";
    echo '  notes: ' . implode('; ', $result['storage_schema']['notes']) . "\n";
    echo "\n";
    printActionSummaryPlain($result);
    echo "\n";
    echo "sql_preview_actions:\n";

    foreach ($result['actions'] as $action) {
        echo '- product_id: ' . $action['product_id'] . "\n";
        echo '  source_attribute_id: ' . $action['source_attribute_id'] . "\n";
        echo '  source_attribute_name: ' . valueOrNone($action['source_attribute_name']) . "\n";
        echo '  canonical_attribute_id: ' . $action['canonical_attribute_id'] . "\n";
        echo '  raw_value: ' . valueOrNone($action['raw_value']) . "\n";
        echo '  proposed_normalized_value: ' . $action['proposed_normalized_value'] . "\n";
        echo '  canonical_unit: ' . $action['canonical_unit'] . "\n";
        echo '  preview_action: ' . $action['preview_action'] . "\n";
        echo '  reason: ' . $action['reason'] . "\n";
    }

    echo "\n";
    echo "sql_preview_statements:\n";

    foreach ($result['statements'] as $index => $statement) {
        echo ($index + 1) . '. ' . $statement . "\n";
    }

    echo "\n";
    echo "excluded_unresolved:\n";

    foreach ($result['excluded_unresolved'] as $row) {
        echo '- product_id: ' . $row['product_id'] . "\n";
        echo '  attribute_id: ' . $row['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($row['attribute_name']) . "\n";
        echo '  raw_value: ' . valueOrNone($row['raw_value']) . "\n";
        echo '  reason: ' . $row['reason'] . "\n";
    }

    printSafetyMarkers($result);
}

function printSqlPreviewMarkdown(array $result, $format)
{
    echo "# DB-readonly SQL preview\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: sql_preview\n";
    echo '- category_scope: ' . markdownCell($result['category_id']) . "\n";
    echo '- attribute_ids: ' . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo '- canonical_attribute_id: ' . markdownCell($result['canonical_attribute_id']) . "\n";
    echo '- canonical_unit: ' . markdownCell($result['canonical_unit']) . "\n";
    echo '- format: ' . markdownCell($format) . "\n";
    echo "\n";
    echo "## Storage schema summary\n";
    echo "\n";
    echo '- table_name: ' . markdownCell($result['storage_schema']['table_name']) . "\n";
    echo '- relevant_columns: ' . markdownCell(implode(',', $result['storage_schema']['relevant_columns'])) . "\n";
    echo '- schema_status: ' . markdownCell($result['storage_schema']['schema_status']) . "\n";
    echo '- notes: ' . markdownCell(implode('; ', $result['storage_schema']['notes'])) . "\n";
    echo "\n";
    echo "## SQL preview action summary\n";
    echo "\n";
    echo '- preview_update_existing_canonical_row_count: ' . $result['preview_update_existing_canonical_row_count'] . "\n";
    echo '- preview_insert_missing_canonical_row_count: ' . $result['preview_insert_missing_canonical_row_count'] . "\n";
    echo '- keep_existing_source_row_count: ' . $result['keep_existing_source_row_count'] . "\n";
    echo '- unresolved_excluded_count: ' . $result['unresolved_excluded_count'] . "\n";
    echo '- schema_blocker_count: ' . $result['schema_blocker_count'] . "\n";
    echo '- conflicts_count: ' . $result['conflicts_count'] . "\n";
    echo "\n";
    echo "## SQL preview actions\n";
    echo "\n";
    echo "| product_id | source_attribute_id | source_attribute_name | canonical_attribute_id | raw_value | proposed_normalized_value | canonical_unit | preview_action | reason |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($result['actions'] as $action) {
        echo '| ' . markdownCell($action['product_id']);
        echo ' | ' . markdownCell($action['source_attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($action['source_attribute_name']));
        echo ' | ' . markdownCell($action['canonical_attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($action['raw_value']));
        echo ' | ' . markdownCell($action['proposed_normalized_value']);
        echo ' | ' . markdownCell($action['canonical_unit']);
        echo ' | ' . markdownCell($action['preview_action']);
        echo ' | ' . markdownCell($action['reason']) . " |\n";
    }

    echo "\n";
    echo "## SQL preview statements\n";
    echo "\n";

    if (count($result['statements']) === 0) {
        echo "No SQL preview statements generated because of schema blocker or conflicts.\n";
    } else {
        foreach ($result['statements'] as $index => $statement) {
            echo ($index + 1) . ". Preview only:\n";
            echo "\n";
            echo "```sql\n";
            echo $statement . "\n";
            echo "```\n";
            echo "\n";
        }
    }

    echo "## Excluded unresolved\n";
    echo "\n";
    echo "| product_id | attribute_id | attribute_name | raw_value | reason |\n";
    echo "| --- | --- | --- | --- | --- |\n";

    foreach ($result['excluded_unresolved'] as $row) {
        echo '| ' . markdownCell($row['product_id']);
        echo ' | ' . markdownCell($row['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($row['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($row['raw_value']));
        echo ' | ' . markdownCell($row['reason']) . " |\n";
    }

    echo "\n";
    echo "## Safety markers\n";
    echo "\n";
    echo "```text\n";
    printSafetyMarkers($result);
    echo "```\n";
}

function printActionSummaryPlain(array $result)
{
    echo "sql_preview_action_summary:\n";
    echo '  preview_update_existing_canonical_row_count: ' . $result['preview_update_existing_canonical_row_count'] . "\n";
    echo '  preview_insert_missing_canonical_row_count: ' . $result['preview_insert_missing_canonical_row_count'] . "\n";
    echo '  keep_existing_source_row_count: ' . $result['keep_existing_source_row_count'] . "\n";
    echo '  unresolved_excluded_count: ' . $result['unresolved_excluded_count'] . "\n";
    echo '  schema_blocker_count: ' . $result['schema_blocker_count'] . "\n";
    echo '  conflicts_count: ' . $result['conflicts_count'] . "\n";
}

function printSafetyMarkers(array $result)
{
    echo "sql_preview_generated: 1\n";
    echo "sql_preview_printed_to_console: 1\n";
    echo "sql_files_created: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "sql_applied: 0\n";
    echo "apply_plan_created: 0\n";
    echo "product_data_changed: 0\n";
    echo "production_ready: 0\n";
    echo "cache_rebuild_required: 0\n";
    echo 'unresolved_values_excluded: ' . ($result['unresolved_excluded_count'] > 0 ? 1 : 0) . "\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
}

function valueOrNone($value)
{
    if ((string) $value === '') {
        return 'none';
    }

    return (string) $value;
}

function markdownCell($value)
{
    $value = valueOrNone($value);
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return $value;
}
