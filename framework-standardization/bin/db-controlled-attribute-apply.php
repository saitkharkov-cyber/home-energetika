<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Apply\DbControlledAttributeApplyCommand;
use FrameworkStandardization\Contract\AttributeContractLoader;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-controlled-attribute-apply.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '' || !isset($argv[2]) || trim($argv[2]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-controlled-attribute-apply.php path/to/runtime.php path/to/contract.php [--confirm-apply] [--format=plain|markdown]');
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
    $command = new DbControlledAttributeApplyCommand($pdo, $runtimeConfig->getDbPrefix(), $contract, new SimpleMetersNormalizer());
    $result = $command->run($runtimeConfig->getRuntimeMode(), !empty($options['confirm_apply']));

    if ($options['format'] === 'markdown') {
        printMarkdown($result);
    } else {
        printPlain($result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'controlled_attribute_apply_error: ' . $e->getMessage() . "\n");
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
    echo "update_existing_canonical_row_count: " . $result['update_existing_canonical_row_count'] . "\n";
    echo "insert_missing_canonical_row_count: " . $result['insert_missing_canonical_row_count'] . "\n";
    echo "already_applied_count: " . $result['already_applied_count'] . "\n";
    echo "source_based_already_applied_count: " . $result['source_based_already_applied_count'] . "\n";
    echo "canonical_only_verified_count: " . $result['canonical_only_verified_count'] . "\n";
    echo "source_based_plan_available: " . $result['source_based_plan_available'] . "\n";
    echo "dry_run_limitation: " . $result['dry_run_limitation'] . "\n";
    echo "unresolved_excluded_count: " . $result['unresolved_excluded_count'] . "\n";
    echo "duplicate_or_conflict_count: " . $result['duplicate_or_conflict_count'] . "\n";
    echo "expected_update_after_cleanup_count: " . $result['expected_update_after_cleanup_count'] . "\n";
    echo "expected_insert_after_cleanup_count: " . $result['expected_insert_after_cleanup_count'] . "\n";
    echo "expected_already_applied_count: " . $result['expected_already_applied_count'] . "\n";
    echo "expected_unresolved_excluded_count: " . $result['expected_unresolved_excluded_count'] . "\n";
    echo "expected_counts_match: " . $result['expected_counts_match'] . "\n";
    echo "actual_updated_count: " . $result['actual_updated_count'] . "\n";
    echo "actual_inserted_count: " . $result['actual_inserted_count'] . "\n";
    echo "transaction_started: " . $result['transaction_started'] . "\n";
    echo "transaction_committed: " . $result['transaction_committed'] . "\n";
    echo "transaction_rolled_back: " . $result['transaction_rolled_back'] . "\n";
    echo "rollback_reason: " . $result['rollback_reason'] . "\n";
    echo "post_apply_verification_ok: " . $result['post_apply_verification_ok'] . "\n";
    echo "\nsafety_markers:\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }
}

function printMarkdown(array $result)
{
    echo "# Контролируемое применение canonical values\n\n";
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
    echo "- update_existing_canonical_row_count: " . $result['update_existing_canonical_row_count'] . "\n";
    echo "- insert_missing_canonical_row_count: " . $result['insert_missing_canonical_row_count'] . "\n";
    echo "- already_applied_count: " . $result['already_applied_count'] . "\n";
    echo "- source_based_already_applied_count: " . $result['source_based_already_applied_count'] . "\n";
    echo "- canonical_only_verified_count: " . $result['canonical_only_verified_count'] . "\n";
    echo "- source_based_plan_available: " . $result['source_based_plan_available'] . "\n";
    echo "- dry_run_limitation: " . markdownCell($result['dry_run_limitation']) . "\n";
    echo "- unresolved_excluded_count: " . $result['unresolved_excluded_count'] . "\n";
    echo "- duplicate_or_conflict_count: " . $result['duplicate_or_conflict_count'] . "\n";
    echo "- expected_counts_match: " . $result['expected_counts_match'] . "\n";
    echo "- actual_updated_count: " . $result['actual_updated_count'] . "\n";
    echo "- actual_inserted_count: " . $result['actual_inserted_count'] . "\n";
    echo "- transaction_started: " . $result['transaction_started'] . "\n";
    echo "- transaction_committed: " . $result['transaction_committed'] . "\n";
    echo "- transaction_rolled_back: " . $result['transaction_rolled_back'] . "\n";
    echo "- rollback_reason: " . markdownCell($result['rollback_reason']) . "\n";
    echo "- post_apply_verification_ok: " . $result['post_apply_verification_ok'] . "\n\n";
    echo "## Маркеры безопасности\n\n";
    echo "```text\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }

    echo "```\n";
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
