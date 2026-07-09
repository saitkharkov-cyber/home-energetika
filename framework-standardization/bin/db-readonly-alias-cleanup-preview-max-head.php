<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\Preview\DbReadOnlyAliasCleanupPreviewMaxHead;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-alias-cleanup-preview-max-head.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-alias-cleanup-preview-max-head.php path/to/runtime.php [--format=plain|markdown]');
    }

    $runtimeFile = $argv[1];
    $format = parseFormat($argv);

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    assertControlledReadonlyRuntime($runtimeConfig);
    $pdo = createPdo($runtimeConfig);
    $preview = new DbReadOnlyAliasCleanupPreviewMaxHead($pdo, $runtimeConfig->getDbPrefix());
    $result = $preview->generate();

    if ($format === 'markdown') {
        printMarkdown($result);
    } else {
        printPlain($result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'alias_cleanup_preview_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseFormat(array $argv)
{
    $format = 'plain';

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if (strpos($arg, '--format=') === 0) {
            $format = substr($arg, strlen('--format='));

            if ($format !== 'plain' && $format !== 'markdown') {
                throw new \InvalidArgumentException('unsupported_format');
            }

            continue;
        }

        throw new \InvalidArgumentException('unexpected_cli_argument');
    }

    return $format;
}

function assertControlledReadonlyRuntime(OpenCartRuntimeConfig $runtimeConfig)
{
    $database = $runtimeConfig->getDatabase();

    if ($runtimeConfig->getRuntimeMode() !== 'db_readonly') {
        throw new \RuntimeException('runtime_mode_must_be_db_readonly');
    }

    if (!isset($database['host']) || $database['host'] !== '127.0.1.19') {
        throw new \RuntimeException('db_host_not_allowed');
    }

    if (!isset($database['dbname']) || $database['dbname'] !== 'he_framework_local_dump') {
        throw new \RuntimeException('db_name_not_allowed');
    }

    if ($runtimeConfig->getDbPrefix() !== 'oc_') {
        throw new \RuntimeException('db_prefix_not_allowed');
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

function printPlain(array $result)
{
    echo "runtime_mode: " . $result['runtime_mode'] . "\n";
    echo "command: " . $result['command'] . "\n";
    echo "category_scope: " . $result['category_scope'] . "\n";
    echo "category_scope_ids_count: " . $result['category_scope_ids_count'] . "\n";
    echo "canonical_attribute_id: " . $result['canonical_attribute_id'] . "\n";
    echo "alias_attribute_ids: " . implode(',', $result['alias_attribute_ids']) . "\n";
    echo "target_table: " . $result['target_table'] . "\n";
    echo "total_alias_rows_in_scope: " . $result['total_alias_rows_in_scope'] . "\n";
    echo "safely_removable_alias_rows: " . $result['safely_removable_alias_rows'] . "\n";
    echo "not_removable_alias_rows: " . $result['not_removable_alias_rows'] . "\n";
    echo "\nbreakdown_by_alias_attribute_id:\n";

    foreach ($result['breakdown_by_alias_attribute_id'] as $row) {
        echo "- alias_attribute_id: " . $row['alias_attribute_id'];
        echo ", total_rows: " . $row['total_rows'];
        echo ", safely_removable: " . $row['safely_removable'];
        echo ", not_removable: " . $row['not_removable'] . "\n";
    }

    echo "\nnot_removable_reasons:\n";

    foreach ($result['not_removable_reasons'] as $reason => $count) {
        echo "- " . $reason . ": " . $count . "\n";
    }

    echo "\nsample_safely_removable:\n";
    printPlainSamples($result['sample_safely_removable']);
    echo "\nsample_not_removable:\n";
    printPlainSamples($result['sample_not_removable']);
    echo "\nsafety_markers:\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }
}

function printPlainSamples(array $samples)
{
    foreach ($samples as $row) {
        echo "- product_id: " . $row['product_id'];
        echo ", attribute_id: " . $row['attribute_id'];
        echo ", attribute_name: " . $row['attribute_name'];
        echo ", language_id: " . $row['language_id'];
        echo ", raw_value: " . $row['raw_value'];
        echo ", canonical_value: " . $row['canonical_value'];
        echo ", reason: " . $row['reason'] . "\n";
    }
}

function printMarkdown(array $result)
{
    echo "# DB-readonly alias cleanup preview — max head\n\n";
    echo "- runtime_mode: " . markdownCell($result['runtime_mode']) . "\n";
    echo "- command: " . markdownCell($result['command']) . "\n";
    echo "- category_scope: " . markdownCell($result['category_scope']) . "\n";
    echo "- category_scope_ids_count: " . markdownCell($result['category_scope_ids_count']) . "\n";
    echo "- canonical_attribute_id: " . markdownCell($result['canonical_attribute_id']) . "\n";
    echo "- alias_attribute_ids: " . markdownCell(implode(',', $result['alias_attribute_ids'])) . "\n";
    echo "- target_table: " . markdownCell($result['target_table']) . "\n\n";
    echo "## Summary\n\n";
    echo "- total_alias_rows_in_scope: " . $result['total_alias_rows_in_scope'] . "\n";
    echo "- safely_removable_alias_rows: " . $result['safely_removable_alias_rows'] . "\n";
    echo "- not_removable_alias_rows: " . $result['not_removable_alias_rows'] . "\n\n";
    echo "## Breakdown\n\n";
    echo "| alias_attribute_id | total_rows | safely_removable | not_removable |\n";
    echo "| --- | ---: | ---: | ---: |\n";

    foreach ($result['breakdown_by_alias_attribute_id'] as $row) {
        echo "| " . markdownCell($row['alias_attribute_id']) . " | " . markdownCell($row['total_rows']) . " | " . markdownCell($row['safely_removable']) . " | " . markdownCell($row['not_removable']) . " |\n";
    }

    echo "\n## Not removable reasons\n\n";
    echo "| reason | count |\n";
    echo "| --- | ---: |\n";

    foreach ($result['not_removable_reasons'] as $reason => $count) {
        echo "| " . markdownCell($reason) . " | " . markdownCell($count) . " |\n";
    }

    echo "\n## Sample safely removable\n\n";
    printMarkdownSamples($result['sample_safely_removable']);
    echo "\n## Sample not removable\n\n";
    printMarkdownSamples($result['sample_not_removable']);
    echo "\n## Safety markers\n\n";
    echo "```text\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }

    echo "```\n";
}

function printMarkdownSamples(array $samples)
{
    echo "| product_id | attribute_id | attribute_name | language_id | raw_value | canonical_value | reason |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($samples as $row) {
        echo "| " . markdownCell($row['product_id']);
        echo " | " . markdownCell($row['attribute_id']);
        echo " | " . markdownCell($row['attribute_name']);
        echo " | " . markdownCell($row['language_id']);
        echo " | " . markdownCell($row['raw_value']);
        echo " | " . markdownCell($row['canonical_value']);
        echo " | " . markdownCell($row['reason']) . " |\n";
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
