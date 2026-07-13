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
    'migration_applied' => false,
    'alias_cleanup_applied' => false,
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
