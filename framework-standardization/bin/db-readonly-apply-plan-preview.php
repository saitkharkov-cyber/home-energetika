<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\ApplyPlan\DbReadOnlyApplyPlanPreview;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Preview\DbReadOnlySqlPreview;
use FrameworkStandardization\Proposals\DbReadOnlyNormalizationProposals;
use FrameworkStandardization\Review\DbReadOnlyNormalizationReviewChain;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-apply-plan-preview.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-apply-plan-preview.php path/to/runtime.php --category-id=ID --attribute-ids=1,2 --canonical-attribute-id=ID --canonical-unit=m [--format=plain|markdown]');
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
    $sqlPreview = new DbReadOnlySqlPreview($db, $runtimeConfig->getDbPrefix(), $reviewChain);
    $applyPlanPreview = new DbReadOnlyApplyPlanPreview($sqlPreview);
    $result = $applyPlanPreview->generate(
        $options['category_id'],
        $options['attribute_ids'],
        $options['canonical_attribute_id'],
        $options['canonical_unit']
    );

    if ($options['format'] === 'markdown') {
        printApplyPlanPreviewMarkdown($result, $options['format']);
    } else {
        printApplyPlanPreviewPlain($result, $options['format']);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'apply_plan_preview_error: ' . $e->getMessage() . "\n");
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

function printApplyPlanPreviewPlain(array $result, $format)
{
    echo "runtime_mode: db_readonly\n";
    echo "command: apply_plan_preview\n";
    echo 'category_scope: ' . $result['category_id'] . "\n";
    echo 'attribute_ids: ' . implode(',', $result['attribute_ids']) . "\n";
    echo 'canonical_attribute_id: ' . $result['canonical_attribute_id'] . "\n";
    echo 'canonical_unit: ' . $result['canonical_unit'] . "\n";
    echo 'format: ' . $format . "\n";
    echo "\n";
    printSummaryPlain($result);
    echo "\npreflight_checks:\n";
    printNamedRowsPlain($result['preflight_checks']);
    echo "\noperation_groups:\n";
    printOperationGroupsPlain($result);
    echo "\nsql_statements_preview:\n";
    printStatementsPlain('update_existing_canonical_rows', $result['update_statements']);
    printStatementsPlain('insert_missing_canonical_rows', $result['insert_statements']);
    echo "\npost_apply_verification_plan:\n";
    printListPlain($result['post_apply_verification_plan']);
    echo "\nrollback_notes:\n";
    printListPlain($result['rollback_notes']);
    printSafetyMarkers($result);
}

function printApplyPlanPreviewMarkdown(array $result, $format)
{
    echo "# DB-readonly apply-plan preview\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: apply_plan_preview\n";
    echo '- category_scope: ' . markdownCell($result['category_id']) . "\n";
    echo '- attribute_ids: ' . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo '- canonical_attribute_id: ' . markdownCell($result['canonical_attribute_id']) . "\n";
    echo '- canonical_unit: ' . markdownCell($result['canonical_unit']) . "\n";
    echo '- format: ' . markdownCell($format) . "\n";
    echo "\n";
    echo "## Apply-plan summary\n";
    echo "\n";
    echo '- apply_plan_preview_generated: 1' . "\n";
    echo '- update_existing_canonical_row_count: ' . count($result['update_statements']) . "\n";
    echo '- insert_missing_canonical_row_count: ' . count($result['insert_statements']) . "\n";
    echo '- keep_existing_source_row_count: ' . $result['sql_preview']['keep_existing_source_row_count'] . "\n";
    echo '- unresolved_excluded_count: ' . $result['sql_preview']['unresolved_excluded_count'] . "\n";
    echo '- schema_blocker_count: ' . $result['sql_preview']['schema_blocker_count'] . "\n";
    echo '- conflicts_count: ' . $result['sql_preview']['conflicts_count'] . "\n";
    echo '- executable_apply_plan: 0' . "\n";
    echo '- sql_apply_allowed: 0' . "\n";
    echo "\n";
    echo "## Preflight checks\n";
    echo "\n";
    echo "| check | status | note |\n";
    echo "| --- | --- | --- |\n";

    foreach ($result['preflight_checks'] as $check) {
        echo '| ' . markdownCell($check['check']);
        echo ' | ' . markdownCell($check['status']);
        echo ' | ' . markdownCell($check['note']) . " |\n";
    }

    echo "\n";
    echo "## Operation groups\n";
    echo "\n";
    echo "### A. Update existing canonical rows\n";
    echo "\n";
    echo '- count: ' . count($result['update_statements']) . "\n";
    echo "- description: future operation would update existing `attribute_id=12` rows in `oc_product_attribute`.\n";
    echo "\n";
    echo "### B. Insert missing canonical rows\n";
    echo "\n";
    echo '- count: ' . count($result['insert_statements']) . "\n";
    echo "- description: future operation would insert missing canonical `attribute_id=12` rows for approved alias values.\n";
    echo "\n";
    echo "### C. Keep existing source alias rows\n";
    echo "\n";
    echo '- count: ' . $result['sql_preview']['keep_existing_source_row_count'] . "\n";
    echo "- description: source alias rows are not deleted or merged automatically.\n";
    echo "\n";
    echo "### D. Excluded unresolved\n";
    echo "\n";
    echo '- count: ' . $result['sql_preview']['unresolved_excluded_count'] . "\n";
    echo "- description: unresolved values stay unresolved and are not included.\n";
    echo "\n";
    echo "## SQL statements preview\n";
    echo "\n";
    echo "### Update existing canonical rows\n";
    echo "\n";
    printStatementsMarkdown($result['update_statements']);
    echo "### Insert missing canonical rows\n";
    echo "\n";
    printStatementsMarkdown($result['insert_statements']);
    echo "## Post-apply verification plan\n";
    echo "\n";
    printListMarkdown($result['post_apply_verification_plan']);
    echo "\n";
    echo "## Rollback notes\n";
    echo "\n";
    printListMarkdown($result['rollback_notes']);
    echo "\n";
    echo "## Safety markers\n";
    echo "\n";
    echo "```text\n";
    printSafetyMarkers($result);
    echo "```\n";
}

function printSummaryPlain(array $result)
{
    echo "apply_plan_summary:\n";
    echo "  apply_plan_preview_generated: 1\n";
    echo '  update_existing_canonical_row_count: ' . count($result['update_statements']) . "\n";
    echo '  insert_missing_canonical_row_count: ' . count($result['insert_statements']) . "\n";
    echo '  keep_existing_source_row_count: ' . $result['sql_preview']['keep_existing_source_row_count'] . "\n";
    echo '  unresolved_excluded_count: ' . $result['sql_preview']['unresolved_excluded_count'] . "\n";
    echo '  schema_blocker_count: ' . $result['sql_preview']['schema_blocker_count'] . "\n";
    echo '  conflicts_count: ' . $result['sql_preview']['conflicts_count'] . "\n";
    echo "  executable_apply_plan: 0\n";
    echo "  sql_apply_allowed: 0\n";
}

function printOperationGroupsPlain(array $result)
{
    echo "- update_existing_canonical_rows: " . count($result['update_statements']) . "\n";
    echo "  description: future operation would update existing canonical rows\n";
    echo "- insert_missing_canonical_rows: " . count($result['insert_statements']) . "\n";
    echo "  description: future operation would insert missing canonical rows\n";
    echo "- keep_existing_source_alias_rows: " . $result['sql_preview']['keep_existing_source_row_count'] . "\n";
    echo "  description: source alias rows are not deleted or merged automatically\n";
    echo "- excluded_unresolved: " . $result['sql_preview']['unresolved_excluded_count'] . "\n";
    echo "  description: unresolved values stay unresolved and are not included\n";
}

function printNamedRowsPlain(array $rows)
{
    foreach ($rows as $row) {
        echo '- check: ' . $row['check'] . "\n";
        echo '  status: ' . $row['status'] . "\n";
        echo '  note: ' . $row['note'] . "\n";
    }
}

function printStatementsPlain($label, array $statements)
{
    echo $label . ":\n";

    foreach ($statements as $index => $statement) {
        echo '  ' . ($index + 1) . '. Preview only: ' . $statement . "\n";
    }
}

function printStatementsMarkdown(array $statements)
{
    foreach ($statements as $index => $statement) {
        echo ($index + 1) . ". Preview only:\n";
        echo "\n";
        echo "```sql\n";
        echo $statement . "\n";
        echo "```\n";
        echo "\n";
    }
}

function printListPlain(array $items)
{
    foreach ($items as $item) {
        echo '- ' . $item . "\n";
    }
}

function printListMarkdown(array $items)
{
    foreach ($items as $item) {
        echo '- ' . markdownCell($item) . "\n";
    }
}

function printSafetyMarkers(array $result)
{
    echo 'apply_plan_preview_generated: ' . (int) $result['apply_plan_preview_generated'] . "\n";
    echo 'executable_apply_plan: ' . (int) $result['executable_apply_plan'] . "\n";
    echo "sql_preview_generated: 1\n";
    echo "sql_preview_printed_to_console: 1\n";
    echo "sql_files_created: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "sql_applied: 0\n";
    echo "product_data_changed: 0\n";
    echo "production_ready: 0\n";
    echo "cache_rebuild_required: 0\n";
    echo 'unresolved_values_excluded: ' . ($result['sql_preview']['unresolved_excluded_count'] > 0 ? 1 : 0) . "\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
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
