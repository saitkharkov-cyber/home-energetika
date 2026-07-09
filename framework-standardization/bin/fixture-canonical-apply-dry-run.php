<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Fixture\GenericCanonicalApplyFixtureDryRun;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "fixture-canonical-apply-dry-run.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '' || !isset($argv[2]) || trim($argv[2]) === '') {
        throw new \InvalidArgumentException('usage: php bin/fixture-canonical-apply-dry-run.php path/to/fixture-contract.php path/to/fixture-rows.php');
    }

    for ($i = 3; $i < count($argv); $i++) {
        if (trim($argv[$i]) === '--confirm-apply') {
            throw new \InvalidArgumentException('fixture_confirm_apply_not_allowed');
        }

        throw new \InvalidArgumentException('unexpected_cli_argument');
    }

    $contract = loadArrayFile($argv[1], 'fixture_contract_not_found', 'fixture_contract_must_return_array');
    $rows = loadArrayFile($argv[2], 'fixture_rows_not_found', 'fixture_rows_must_return_array');
    $runner = new GenericCanonicalApplyFixtureDryRun($contract, $rows, new SimpleMetersNormalizer());
    $result = $runner->run();

    printPlain($result);
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'fixture_canonical_apply_dry_run_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function loadArrayFile($path, $notFoundError, $invalidError)
{
    if (!is_file($path)) {
        throw new \InvalidArgumentException($notFoundError);
    }

    $data = require $path;

    if (!is_array($data)) {
        throw new \InvalidArgumentException($invalidError);
    }

    return $data;
}

function printPlain(array $result)
{
    echo "command: " . $result['command'] . "\n";
    echo "fixture_only: " . $result['fixture_only'] . "\n";
    echo "target_key: " . $result['target_key'] . "\n";
    echo "target_meaning: " . $result['target_meaning'] . "\n";
    echo "canonical_attribute_id: " . $result['canonical_attribute_id'] . "\n";
    echo "alias_attribute_ids: " . implode(',', $result['alias_attribute_ids']) . "\n";
    echo "normalizer_key: " . $result['normalizer_key'] . "\n";
    echo "update_existing_canonical_row_count: " . $result['update_existing_canonical_row_count'] . "\n";
    echo "insert_missing_canonical_row_count: " . $result['insert_missing_canonical_row_count'] . "\n";
    echo "already_applied_count: " . $result['already_applied_count'] . "\n";
    echo "unresolved_excluded_count: " . $result['unresolved_excluded_count'] . "\n";
    echo "duplicate_or_conflict_count: " . $result['duplicate_or_conflict_count'] . "\n";
    echo "out_of_scope_ignored_count: " . $result['out_of_scope_ignored_count'] . "\n";
    echo "source_based_plan_available: " . $result['source_based_plan_available'] . "\n";
    echo "expected_counts_match: " . $result['expected_counts_match'] . "\n";
    echo "dry_run_expected_counts_ok: " . $result['dry_run_expected_counts_ok'] . "\n";
    echo "post_apply_verification_ok: " . $result['post_apply_verification_ok'] . "\n";
    echo "dry_run: " . $result['dry_run'] . "\n";
    echo "confirm_apply: " . $result['confirm_apply'] . "\n";
    echo "sql_applied: " . $result['sql_applied'] . "\n";
    echo "product_data_changed: " . $result['product_data_changed'] . "\n";
    echo "\nsafety_markers:\n";

    foreach ($result['safety_markers'] as $marker => $value) {
        echo $marker . ": " . $value . "\n";
    }
}
