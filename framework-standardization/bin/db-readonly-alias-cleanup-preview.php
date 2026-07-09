<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Contract\AttributeContractLoader;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\Preview\DbReadOnlyAliasCleanupPreview;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-alias-cleanup-preview.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '' || !isset($argv[2]) || trim($argv[2]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-alias-cleanup-preview.php path/to/runtime.php path/to/contract.php [--format=plain|markdown]');
    }

    $runtimeFile = $argv[1];
    $contractFile = $argv[2];
    $format = parseFormat($argv);

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    $loader = new AttributeContractLoader();
    $contract = $loader->load($contractFile);
    $loader->assertRuntimeAllowed($contract, $runtimeConfig);

    if ((string) $contract['normalizer_key'] !== 'simple_meters') {
        throw new \RuntimeException('normalizer_not_supported');
    }

    $pdo = createPdo($runtimeConfig);
    $preview = new DbReadOnlyAliasCleanupPreview($pdo, $runtimeConfig->getDbPrefix(), $contract, new SimpleMetersNormalizer());
    $result = $preview->generate($runtimeConfig->getRuntimeMode());

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

    for ($i = 3; $i < count($argv); $i++) {
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
    echo "target_key: " . $result['target_key'] . "\n";
    echo "target_meaning: " . $result['target_meaning'] . "\n";
    echo "category_scope: " . $result['category_scope'] . "\n";
    echo "category_scope_ids_count: " . $result['category_scope_ids_count'] . "\n";
    echo "canonical_attribute_id: " . $result['canonical_attribute_id'] . "\n";
    echo "alias_attribute_ids: " . implode(',', $result['alias_attribute_ids']) . "\n";
    echo "normalizer_key: " . $result['normalizer_key'] . "\n";
    echo "target_table: " . $result['target_table'] . "\n";
    echo "total_alias_rows_in_scope: " . $result['total_alias_rows_in_scope'] . "\n";
    echo "safely_removable_alias_rows: " . $result['safely_removable_alias_rows'] . "\n";
    echo "not_removable_alias_rows: " . $result['not_removable_alias_rows'] . "\n";
    echo "expected_alias_total_rows_after_cleanup: " . $result['expected_alias_total_rows_after_cleanup'] . "\n";
    echo "expected_alias_safely_removable_after_cleanup: " . $result['expected_alias_safely_removable_after_cleanup'] . "\n";
    echo "expected_alias_not_removable_after_cleanup: " . $result['expected_alias_not_removable_after_cleanup'] . "\n";
    echo "expected_alias_unresolved_or_excluded_after_cleanup: " . $result['expected_alias_unresolved_or_excluded_after_cleanup'] . "\n";
    echo "expected_counts_match: " . $result['expected_counts_match'] . "\n";
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
    echo "# DB-readonly preview очистки alias rows\n\n";
    echo "- runtime_mode: " . markdownCell($result['runtime_mode']) . "\n";
    echo "- command: " . markdownCell($result['command']) . "\n";
    echo "- target_key: " . markdownCell($result['target_key']) . "\n";
    echo "- target_meaning: " . markdownCell($result['target_meaning']) . "\n";
    echo "- category_scope: " . markdownCell($result['category_scope']) . "\n";
    echo "- category_scope_ids_count: " . markdownCell($result['category_scope_ids_count']) . "\n";
    echo "- canonical_attribute_id: " . markdownCell($result['canonical_attribute_id']) . "\n";
    echo "- alias_attribute_ids: " . markdownCell(implode(',', $result['alias_attribute_ids'])) . "\n";
    echo "- normalizer_key: " . markdownCell($result['normalizer_key']) . "\n";
    echo "- target_table: " . markdownCell($result['target_table']) . "\n\n";
    echo "## Сводка\n\n";
    echo "- total_alias_rows_in_scope: " . $result['total_alias_rows_in_scope'] . "\n";
    echo "- safely_removable_alias_rows: " . $result['safely_removable_alias_rows'] . "\n";
    echo "- not_removable_alias_rows: " . $result['not_removable_alias_rows'] . "\n";
    echo "- expected_alias_total_rows_after_cleanup: " . $result['expected_alias_total_rows_after_cleanup'] . "\n";
    echo "- expected_alias_safely_removable_after_cleanup: " . $result['expected_alias_safely_removable_after_cleanup'] . "\n";
    echo "- expected_alias_not_removable_after_cleanup: " . $result['expected_alias_not_removable_after_cleanup'] . "\n";
    echo "- expected_alias_unresolved_or_excluded_after_cleanup: " . $result['expected_alias_unresolved_or_excluded_after_cleanup'] . "\n";
    echo "- expected_counts_match: " . $result['expected_counts_match'] . "\n\n";
    echo "## Разбивка\n\n";
    echo "| alias_attribute_id | total_rows | safely_removable | not_removable |\n";
    echo "| --- | ---: | ---: | ---: |\n";

    foreach ($result['breakdown_by_alias_attribute_id'] as $row) {
        echo "| " . markdownCell($row['alias_attribute_id']) . " | " . markdownCell($row['total_rows']) . " | " . markdownCell($row['safely_removable']) . " | " . markdownCell($row['not_removable']) . " |\n";
    }

    echo "\n## Причины, почему строки не удаляются\n\n";
    echo "| reason | count |\n";
    echo "| --- | ---: |\n";

    foreach ($result['not_removable_reasons'] as $reason => $count) {
        echo "| " . markdownCell($reason) . " | " . markdownCell($count) . " |\n";
    }

    echo "\n## Примеры безопасно удаляемых строк\n\n";
    printMarkdownSamples($result['sample_safely_removable']);
    echo "\n## Примеры неудаляемых строк\n\n";
    printMarkdownSamples($result['sample_not_removable']);
    echo "\n## Маркеры безопасности\n\n";
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
