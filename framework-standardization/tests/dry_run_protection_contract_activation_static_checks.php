<?php

require dirname(__DIR__) . '/src/Normalizer/SimpleMetersNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/VoltageNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/NormalizerRegistry.php';

use FrameworkStandardization\Normalizer\BooleanYesNoNormalizer;
use FrameworkStandardization\Normalizer\NormalizerRegistry;

function drpca_check($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }
}

function drpca_utf8($base64)
{
    return base64_decode($base64);
}

$contract = require dirname(__DIR__) . '/config/attribute-contracts/dry_run_protection_11900213.php';
$yes = drpca_utf8('0JTQsA==');
$no = drpca_utf8('0J3QtdGC');

$expected = array(
    'target_key' => 'dry_run_protection',
    'category_scope_id' => 11900213,
    'canonical_attribute_id' => 47,
    'alias_attribute_ids' => array(82),
    'excluded_attribute_ids' => array(),
    'migration_direction' => '82 -> 47',
    'canonical_unit' => '',
    'value_type' => 'boolean_enum',
    'allowed_canonical_values' => array($yes, $no),
    'contract_status' => 'approved',
    'contract_approved' => true,
    'normalizer_key' => 'boolean_yes_no',
    'normalizer_status' => 'ready',
    'normalizer_ready' => true,
    'processing_review_status' => 'read_only_ready',
    'read_only_ready' => true,
    'apply_ready' => false,
    'migration_applied' => true,
    'alias_cleanup_applied' => true,
    'confirmation_required' => true,
    'canonical_apply_policy' => 'blocked_until_normalizer_ready_and_explicit_gate',
    'alias_cleanup_policy' => 'blocked_until_canonical_apply_verified_and_explicit_gate',
);

foreach ($expected as $key => $value) {
    drpca_check('contract_' . $key, isset($contract[$key]) && $contract[$key] === $value);
}

drpca_check('canonical_operations', $contract['canonical_apply_allowed_operations'] === array('SELECT'));
drpca_check('alias_operations', $contract['alias_cleanup_allowed_operations'] === array('SELECT'));

$runtime = $contract['runtime_allowlist']['controlled_local_dump'];
drpca_check('runtime_allow_confirm_apply', $runtime['allow_confirm_apply'] === false);
drpca_check('runtime_production_ready', $runtime['production_ready'] === false);
drpca_check('runtime_cache_rebuild_allowed', $runtime['cache_rebuild_allowed'] === false);

drpca_check('current_evidence', $contract['evidence'] === array('evidence_source' => 'controlled_local_dump_post_cleanup', 'scope_distinct_products' => 2467, 'canonical_attribute_47' => array('distinct_products' => 11, 'values' => array($yes => 3, $no => 8)), 'alias_attribute_82' => array('distinct_products' => 0, 'values' => array($yes => 0, $no => 0)), 'products_with_both_attributes' => 0));
drpca_check('current_evidence_no_historical_alias_rows', !array_key_exists('observed_alias_rows_before_migration', $contract['evidence']));
drpca_check('pre_migration_evidence', $contract['pre_migration_evidence'] === array('evidence_source' => 'controlled_local_dump_read_only', 'scope_distinct_products' => 2467, 'canonical_attribute_47' => array('distinct_products' => 10, 'values' => array($yes => 2, $no => 8)), 'alias_attribute_82' => array('distinct_products' => 1, 'values' => array($yes => 1)), 'products_with_both_attributes' => 0, 'observed_alias_rows_before_migration' => 1));
drpca_check('controlled_local_result', $contract['controlled_local_result'] === array('runtime_key' => 'controlled_local_dump', 'completion_date' => '2026-07-13', 'product_id' => 8197, 'language_id' => 1, 'source_attribute_id' => 82, 'canonical_attribute_id' => 47, 'canonical_value' => $yes, 'canonical_apply_first_action' => 'inserted', 'canonical_apply_idempotency_action' => 'already_applied', 'alias_cleanup_first_action' => 'deleted', 'alias_cleanup_idempotency_action' => 'already_cleaned', 'canonical_distinct_products_after' => 11, 'alias_rows_after' => 0, 'canonical_copy_preserved' => true, 'non_target_rows_unchanged' => true, 'transaction_committed' => true, 'production_touched' => false, 'cache_rebuild_performed' => false));
drpca_check('post_cleanup_expected_counts', $contract['expected_canonical_already_applied_count'] === 11 && $contract['expected_canonical_update_count_after_cleanup'] === 0 && $contract['expected_canonical_insert_count_after_cleanup'] === 0 && $contract['expected_unresolved_excluded_count'] === 0 && $contract['expected_alias_total_rows_after_cleanup'] === 0 && $contract['expected_alias_safely_removable_after_cleanup'] === 0 && $contract['expected_alias_not_removable_after_cleanup'] === 0 && $contract['expected_alias_unresolved_or_excluded_after_cleanup'] === 0);

foreach (array('INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE', 'CREATE', 'SQL generation', 'SQL apply', 'production/cache actions', 'cache rebuild') as $operation) {
    drpca_check('forbidden_' . preg_replace('/[^A-Za-z0-9]+/', '_', $operation), in_array($operation, $contract['forbidden_operations'], true));
}

$registry = NormalizerRegistry::createDefault();
drpca_check('registry_has_key', $registry->has('boolean_yes_no') === true);
$normalizer = $registry->get($contract['normalizer_key']);
drpca_check('registry_class', $normalizer instanceof BooleanYesNoNormalizer);

$yesResult = $normalizer->normalize($yes);
$noResult = $normalizer->normalize($no);
drpca_check('normalize_yes', $yesResult['status'] === 'normalized' && $yesResult['canonical_value'] === $yes && $yesResult['value_type'] === 'boolean_enum');
drpca_check('normalize_no', $noResult['status'] === 'normalized' && $noResult['canonical_value'] === $no);
drpca_check('normalize_deterministic', $normalizer->normalize($yes) === $yesResult);

echo "dry_run_protection_contract_activation_static_checks: ok\n";
