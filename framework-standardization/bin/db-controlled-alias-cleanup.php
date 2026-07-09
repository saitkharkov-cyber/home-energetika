<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Apply\DbControlledAliasCleanupCommand;
use FrameworkStandardization\Contract\AttributeContractLoader;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-controlled-alias-cleanup.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '' || !isset($argv[2]) || trim($argv[2]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-controlled-alias-cleanup.php path/to/runtime.php path/to/contract.php [--confirm-apply] [--format=plain|markdown]');
    }

    $runtimeFile = $argv[1];
    $contractFile = $argv[2];
    $options = parseCliOptions($argv);

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
    $command = new DbControlledAliasCleanupCommand($pdo, $runtimeConfig->getDbPrefix(), $contract, new SimpleMetersNormalizer());
    $result = $command->run($runtimeConfig->getRuntimeMode(), !empty($options['confirm_apply']));

    if ($options['format'] === 'markdown') {
        printMarkdown($result);
    } else {
        printPlain($result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'controlled_alias_cleanup_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $options = array(
        'confirm_apply' => 0,
        'format' => 'plain',
    );

    for ($i = 3; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '--confirm-apply') {
            $options['confirm_apply'] = 1;
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

    return $options;
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
    echo "dry_run: " . $result['dry_run'] . "\n";
    echo "confirm_apply: " . $result['confirm_apply'] . "\n";
    echo "category_scope: " . $result['category_scope'] . "\n";
    echo "canonical_attribute_id: " . $result['canonical_attribute_id'] . "\n";
    echo "alias_attribute_ids: " . implode(',', $result['alias_attribute_ids']) . "\n";
    echo "normalizer_key: " . $result['normalizer_key'] . "\n";
    echo "target_table: " . $result['target_table'] . "\n";
    echo "planned_delete_count: " . $result['planned_delete_count'] . "\n";
    echo "planned_keep_alias_count: " . $result['planned_keep_alias_count'] . "\n";
    echo "actual_deleted_count: " . $result['actual_deleted_count'] . "\n";
    echo "remaining_alias_rows: " . $result['remaining_alias_rows'] . "\n";
    echo "remaining_not_removable_rows: " . $result['remaining_not_removable_rows'] . "\n";
    echo "expected_alias_delete_count: " . $result['expected_alias_delete_count'] . "\n";
    echo "expected_remaining_alias_rows: " . $result['expected_remaining_alias_rows'] . "\n";
    echo "not_removed_unresolved_or_excluded_count: " . $result['not_removed_unresolved_or_excluded_count'] . "\n";
    echo "expected_counts_match: " . $result['expected_counts_match'] . "\n";
    echo "transaction_started: " . $result['transaction_started'] . "\n";
    echo "transaction_committed: " . $result['transaction_committed'] . "\n";
    echo "transaction_rolled_back: " . $result['transaction_rolled_back'] . "\n";
    echo "rollback_reason: " . $result['rollback_reason'] . "\n";
    echo "post_cleanup_verification_ok: " . $result['post_cleanup_verification_ok'] . "\n";
    printBreakdownPlain('breakdown_before', $result['breakdown_before']);
    printBreakdownPlain('breakdown_after', $result['breakdown_after']);
    echo "\nsafety_markers:\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }
}

function printBreakdownPlain($title, array $breakdown)
{
    echo "\n" . $title . ":\n";

    foreach ($breakdown as $row) {
        echo "- alias_attribute_id: " . $row['alias_attribute_id'];
        echo ", total_rows: " . $row['total_rows'];
        echo ", safely_removable: " . $row['safely_removable'];
        echo ", not_removable: " . $row['not_removable'] . "\n";
    }
}

function printMarkdown(array $result)
{
    echo "# Контролируемая очистка alias rows\n\n";
    echo "- runtime_mode: " . markdownCell($result['runtime_mode']) . "\n";
    echo "- command: " . markdownCell($result['command']) . "\n";
    echo "- target_key: " . markdownCell($result['target_key']) . "\n";
    echo "- target_meaning: " . markdownCell($result['target_meaning']) . "\n";
    echo "- dry_run: " . markdownCell($result['dry_run']) . "\n";
    echo "- confirm_apply: " . markdownCell($result['confirm_apply']) . "\n";
    echo "- category_scope: " . markdownCell($result['category_scope']) . "\n";
    echo "- canonical_attribute_id: " . markdownCell($result['canonical_attribute_id']) . "\n";
    echo "- alias_attribute_ids: " . markdownCell(implode(',', $result['alias_attribute_ids'])) . "\n";
    echo "- normalizer_key: " . markdownCell($result['normalizer_key']) . "\n";
    echo "- target_table: " . markdownCell($result['target_table']) . "\n\n";
    echo "## Сводка\n\n";
    echo "- planned_delete_count: " . $result['planned_delete_count'] . "\n";
    echo "- planned_keep_alias_count: " . $result['planned_keep_alias_count'] . "\n";
    echo "- actual_deleted_count: " . $result['actual_deleted_count'] . "\n";
    echo "- remaining_alias_rows: " . $result['remaining_alias_rows'] . "\n";
    echo "- remaining_not_removable_rows: " . $result['remaining_not_removable_rows'] . "\n";
    echo "- expected_alias_delete_count: " . $result['expected_alias_delete_count'] . "\n";
    echo "- expected_remaining_alias_rows: " . $result['expected_remaining_alias_rows'] . "\n";
    echo "- not_removed_unresolved_or_excluded_count: " . $result['not_removed_unresolved_or_excluded_count'] . "\n";
    echo "- expected_counts_match: " . $result['expected_counts_match'] . "\n";
    echo "- transaction_started: " . $result['transaction_started'] . "\n";
    echo "- transaction_committed: " . $result['transaction_committed'] . "\n";
    echo "- transaction_rolled_back: " . $result['transaction_rolled_back'] . "\n";
    echo "- rollback_reason: " . markdownCell($result['rollback_reason']) . "\n";
    echo "- post_cleanup_verification_ok: " . $result['post_cleanup_verification_ok'] . "\n\n";
    printBreakdownMarkdown('Разбивка до операции', $result['breakdown_before']);
    printBreakdownMarkdown('Разбивка после операции', $result['breakdown_after']);
    echo "\n## Маркеры безопасности\n\n";
    echo "```text\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }

    echo "```\n";
}

function printBreakdownMarkdown($title, array $breakdown)
{
    echo "## " . markdownCell($title) . "\n\n";
    echo "| alias_attribute_id | total_rows | safely_removable | not_removable |\n";
    echo "| --- | ---: | ---: | ---: |\n";

    foreach ($breakdown as $row) {
        echo "| " . markdownCell($row['alias_attribute_id']) . " | " . markdownCell($row['total_rows']) . " | " . markdownCell($row['safely_removable']) . " | " . markdownCell($row['not_removable']) . " |\n";
    }

    echo "\n";
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
