<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\Pipeline\ReviewPackageWriter;
use FrameworkStandardization\Pipeline\StandardizationJobContractLoader;
use FrameworkStandardization\Pipeline\StandardizationPipeline;

$repoDir = dirname(__DIR__);
$repoDir = dirname($repoDir);
$registry = NormalizerRegistry::createDefault();
$loader = new StandardizationJobContractLoader($repoDir, $registry);

function check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }

    echo $label . ": ok\n";
}

function check_fail($label, $callback, $expectedMessage)
{
    try {
        call_user_func($callback);
        echo $label . ": failed_no_exception\n";
        exit(1);
    } catch (Exception $e) {
        check_true($label, $e->getMessage() === $expectedMessage);
    }
}

function valid_job()
{
    return array(
        'job_version' => 1,
        'job_key' => 'submersible_pumps_voltage',
        'runtime_config' => 'framework-standardization/config/runtime/prod.snapshot.local.php',
        'scope' => array('category_ids' => array(11900213)),
        'target' => array(
            'search_terms' => array('напряжение'),
            'canonical_attribute_id' => 15,
            'included_alias_attribute_ids' => array(57, 79, 99, 118, 170),
            'excluded_attribute_ids' => array(73),
        ),
        'normalization' => array(
            'normalizer_key' => 'voltage',
            'canonical_unit' => 'V',
            'normalized_value_type' => 'integer_enum',
            'allowed_canonical_values' => array('220', '380'),
        ),
        'output' => array(
            'format' => 'markdown',
            'directory' => 'framework-standardization/runtime/reports',
        ),
        'safety' => array(
            'read_only' => true,
            'allow_sql_generation' => false,
            'allow_apply_plan' => false,
            'allow_apply' => false,
        ),
    );
}

$loader->validate(valid_job());
echo "valid_voltage_job: ok\n";

$job = valid_job();
unset($job['job_version']);
check_fail('missing_version', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_job_version_required');

$job = valid_job();
$job['job_version'] = 2;
check_fail('unknown_version', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_job_version_unknown');

$job = valid_job();
$job['scope']['category_ids'] = array();
check_fail('empty_categories', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_category_ids_required');

$job = valid_job();
$job['target']['canonical_attribute_id'] = 999;
$job['target']['candidate_attribute_ids'] = array(15, 57);
unset($job['target']['included_alias_attribute_ids']);
check_fail('canonical_not_in_candidates', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_canonical_not_in_candidates');

$job = valid_job();
$job['target']['included_alias_attribute_ids'] = array(57, 57);
check_fail('duplicate_alias_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_included_alias_attribute_ids_duplicate');

$job = valid_job();
$job['target']['included_alias_attribute_ids'] = array(15, 57);
check_fail('canonical_in_aliases_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_canonical_in_aliases');

$job = valid_job();
$job['target']['excluded_attribute_ids'] = array(57);
check_fail('excluded_overlap_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_alias_also_excluded');

$job = valid_job();
$job['normalization']['allowed_canonical_values'] = array(220, '220');
check_fail('duplicate_allowed_values_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_allowed_canonical_values_duplicate');

$job = valid_job();
$job['normalization']['allowed_canonical_values'] = array();
check_fail('empty_allowed_values_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_allowed_canonical_values_required');

$job = valid_job();
$job['normalization']['allowed_canonical_values'] = array('', '380');
check_fail('blank_allowed_values_rejected', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_allowed_canonical_values_invalid');

$job = valid_job();
$job['normalization']['allowed_canonical_values'] = array(220, '380');
$mixedAllowed = $loader->validate($job);
check_true('mixed_allowed_values_normalized_to_strings', $mixedAllowed['normalization']['allowed_canonical_values'] === array('220', '380'));

$validated = $loader->validate(valid_job());
check_true('aliases_accepted', $validated['target']['included_alias_attribute_ids'] === array(57, 79, 99, 118, 170));
check_true('allowed_values_accepted', $validated['normalization']['allowed_canonical_values'] === array('220', '380'));

$job = valid_job();
$job['normalization']['normalizer_key'] = 'unknown';
check_fail('unknown_normalizer', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_normalizer_unknown');

$job = valid_job();
$job['safety']['allow_apply'] = true;
check_fail('unsafe_safety_flag', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_safety_allow_apply_invalid');

$job = valid_job();
$job['output']['directory'] = '../outside';
check_fail('invalid_output_path', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_output_path_not_allowed');

check_true('registry_simple_meters', is_object($registry->get('simple_meters')));
check_true('registry_voltage', is_object($registry->get('voltage')));
check_fail('registry_unknown', function () use ($registry) { $registry->get('unknown'); }, 'pipeline_normalizer_unknown');

$voltage = $registry->get('voltage');
$cases = array(
    '220' => array('normalized', '220'),
    '220V' => array('normalized', '220'),
    '220 В' => array('normalized', '220'),
    '230' => array('normalized', '220'),
    '230 В' => array('normalized', '220'),
    '1  230 В' => array('normalized', '220'),
    '1x230 В' => array('normalized', '220'),
    '1230 В' => array('normalized', '220'),
    '200240 В' => array('normalized', '220'),
    '210..240' => array('normalized', '220'),
    '220230 В' => array('normalized', '220'),
    '220-240 В' => array('normalized', '220'),
    '230 В, 50 Гц / 220 В, 60 Гц' => array('normalized', '220'),
    '380' => array('normalized', '380'),
    '380V' => array('normalized', '380'),
    '380 В' => array('normalized', '380'),
    '400' => array('normalized', '380'),
    '400 В' => array('normalized', '380'),
    '3  400 В' => array('normalized', '380'),
    '3x400 В' => array('normalized', '380'),
    '3400 В' => array('normalized', '380'),
    '380400 В' => array('normalized', '380'),
    '380..420' => array('normalized', '380'),
    '400 В / 220240 В' => array('review_required', null, 'mixed_voltage_classes'),
    '220230 В / 380400 В' => array('review_required', null, 'mixed_voltage_classes'),
    '400 В; однофазная версия 230 В' => array('review_required', null, 'mixed_voltage_classes'),
    '110 В' => array('review_required', null, 'voltage_outside_allowed_classes'),
    '127 В' => array('review_required', null, 'voltage_outside_allowed_classes'),
    '480 В' => array('review_required', null, 'voltage_outside_allowed_classes'),
    '230 В / 110 В' => array('review_required', null, 'voltage_outside_allowed_classes'),
    '3  230 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '3x230 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '3~230 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '230 В (трёхфазный)' => array('review_required', null, 'phase_voltage_class_conflict'),
    '230 В (трехфазный)' => array('review_required', null, 'phase_voltage_class_conflict'),
    '230 В, 3 фазы' => array('review_required', null, 'phase_voltage_class_conflict'),
    '1  400 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '1x400 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '1~400 В' => array('review_required', null, 'phase_voltage_class_conflict'),
    '400 В (однофазный)' => array('review_required', null, 'phase_voltage_class_conflict'),
    '400 В, 1 фаза' => array('review_required', null, 'phase_voltage_class_conflict'),
    '230 В (однофазный)' => array('normalized', '220'),
    '400 В (трёхфазный)' => array('normalized', '380'),
    'неизвестно' => array('unsupported', null),
    '' => array('invalid', null),
);

foreach ($cases as $rawValue => $expected) {
    $result = $voltage->normalize($rawValue);
    $canonical = isset($result['canonical_value']) ? $result['canonical_value'] : null;
    $ambiguityReason = isset($expected[2]) ? $expected[2] : null;
    check_true(
        'voltage_' . ($rawValue === '' ? 'empty' : preg_replace('/[^A-Za-z0-9]+/', '_', $rawValue)),
        $result['status'] === $expected[0]
            && $canonical === $expected[1]
            && ($ambiguityReason === null || $result['ambiguity_reason'] === $ambiguityReason)
            && ($canonical === null || $canonical === '220' || $canonical === '380')
    );
}

$range = $voltage->normalize('220-240 V');
check_true('range_maps_to_enum', $range['value_type'] === 'range' && $range['canonical_value'] === '220');
$compound = $voltage->normalize('1  200240 В (однофазное)');
check_true('compound_keeps_phase', $compound['value_type'] === 'compound' && $compound['phase_count'] === 1 && $compound['canonical_value'] === '220');
check_true('frequency_kept_in_metadata', in_array('50', $voltage->normalize('230 В, 50 Гц')['metadata']['frequency_values'], true));

$pipeline = new StandardizationPipeline($repoDir, $registry);
$method = new ReflectionMethod('FrameworkStandardization\\Pipeline\\StandardizationPipeline', 'isUnchangedCanonicalValue');
$method->setAccessible(true);
$contractMethod = new ReflectionMethod('FrameworkStandardization\\Pipeline\\StandardizationPipeline', 'enforceCanonicalValueContract');
$contractMethod->setAccessible(true);
$jobForStatus = valid_job();
$contractViolation = $contractMethod->invoke($pipeline, array(
    'status' => 'normalized',
    'value_type' => 'single',
    'canonical_value' => '230',
    'unit' => 'V',
    'warnings' => array(),
    'ambiguity_reason' => '',
    'metadata' => array(),
), $jobForStatus);
check_true(
    'canonical_value_outside_contract_review_required',
    $contractViolation['status'] === 'review_required'
        && $contractViolation['canonical_value'] === null
        && $contractViolation['ambiguity_reason'] === 'canonical_value_outside_contract'
        && in_array('canonical_value_outside_contract', $contractViolation['warnings'], true)
);
$normalized220 = $voltage->normalize('220');
$normalized380 = $voltage->normalize('380');
check_true('status_attr15_220_unchanged', $method->invoke($pipeline, array('attribute_id' => 15, 'raw_value' => '220'), $normalized220, $jobForStatus) === true);
check_true('status_attr15_380_unchanged', $method->invoke($pipeline, array('attribute_id' => 15, 'raw_value' => '380'), $normalized380, $jobForStatus) === true);
check_true('status_attr15_220v_normalized', $method->invoke($pipeline, array('attribute_id' => 15, 'raw_value' => '220V'), $voltage->normalize('220V'), $jobForStatus) === false);
check_true('status_attr15_230_normalized', $method->invoke($pipeline, array('attribute_id' => 15, 'raw_value' => '230'), $voltage->normalize('230'), $jobForStatus) === false);
check_true('status_attr15_400_normalized', $method->invoke($pipeline, array('attribute_id' => 15, 'raw_value' => '400'), $voltage->normalize('400'), $jobForStatus) === false);
check_true('status_attr57_220_normalized', $method->invoke($pipeline, array('attribute_id' => 57, 'raw_value' => '220'), $normalized220, $jobForStatus) === false);
check_true('status_attr79_380_normalized', $method->invoke($pipeline, array('attribute_id' => 79, 'raw_value' => '380'), $normalized380, $jobForStatus) === false);
check_true('status_mixed_review_required', $voltage->normalize('400 В / 220240 В')['status'] === 'review_required');

$manifest = array(
    'database_name' => 'he_framework_prod_snapshot_20260710',
    'runtime_mode' => 'live_db_readonly',
    'password' => null,
);
$json = json_encode($manifest);
check_true('manifest_no_secret_value', strpos($json, 'secret') === false && strpos($json, 'placeholder') === false);
$loader->assertOutputPathAllowed('framework-standardization/runtime/reports');
check_fail('output_path_traversal_blocked', function () use ($loader) {
    $loader->assertOutputPathAllowed('framework-standardization/runtime/reports/../../x');
}, 'pipeline_output_path_not_allowed');

$writer = new ReviewPackageWriter($repoDir);
$samplePackage = array(
    'job_key' => 'submersible_pumps_voltage',
    'run_id' => '20260710160000_test',
    'output_directory' => 'framework-standardization/runtime/reports',
    'discovery' => array(
        'candidates' => array(),
    ),
    'inventory' => array(
        'attributes' => array(
            array(
                'attribute_id' => 15,
                'attribute_name' => 'Напряжение',
                'attribute_group_name' => 'Параметры насоса',
                'row_count' => 1,
                'distinct_product_count' => 1,
                'unique_raw_values_count' => 1,
                'blank_values_count' => 0,
                'raw_values' => array(),
            ),
        ),
        'overlap' => array(
            'products_with_multiple_configured_candidate_attributes' => 0,
            'canonical_attribute_coverage' => 1,
            'alias_candidate_coverage' => 0,
        ),
    ),
    'proposals' => array(
        'items' => array(),
    ),
    'scope_diagnostics' => array(
        'root_category_id' => 11900213,
        'scope_pattern' => 'hierarchical_category_path_exists',
        'counts' => array(
            'hierarchical_scope_rows' => 1,
            'direct_parent_rows' => 0,
            'rows_without_direct_parent' => 1,
            'products_without_direct_parent' => 1,
        ),
        'products_without_direct_parent' => array(
            array(
                'product_id' => 900001,
                'product_name' => 'Fixture product',
                'direct_category_ids' => array(11900214),
                'target_attribute_row_count' => 1,
                'attribute_ids' => array(15),
            ),
        ),
    ),
    'manifest' => array(
        'job_contract_version' => 1,
        'job_key' => 'submersible_pumps_voltage',
        'run_id' => '20260710160000_test',
        'start_time' => '2026-07-10T16:00:00+03:00',
        'end_time' => '2026-07-10T16:00:01+03:00',
        'runtime_mode' => 'live_db_readonly',
        'database_name' => 'he_framework_prod_snapshot_20260710',
        'scope' => array('category_ids' => array(11900213)),
        'target' => array(
            'search_terms' => array('напряжение'),
            'canonical_attribute_id' => 15,
            'canonical_attribute_name' => 'Напряжение',
            'candidate_attribute_ids' => array(15, 57),
            'excluded_attribute_ids' => array(73),
        ),
        'normalization' => array(
            'normalizer_key' => 'voltage',
            'canonical_unit' => 'V',
        ),
        'normalizer_key' => 'voltage',
        'generated_files' => array(
            'summary.md' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/summary.md',
            'discovery.md' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/discovery.md',
            'inventory.md' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/inventory.md',
            'proposals.md' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/proposals.md',
            'scope_diagnostics.md' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/scope_diagnostics.md',
            'manifest.json' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/manifest.json',
            'inventory.json' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/inventory.json',
            'proposals.json' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/proposals.json',
            'scope_diagnostics.json' => 'framework-standardization/runtime/reports/submersible_pumps_voltage/20260710160000_test/scope_diagnostics.json',
        ),
        'discovery' => array(
            'configured_candidates_found_in_scope' => array(15),
            'configured_candidates_missing_in_scope' => array(57),
            'unconfigured_discovered_candidates' => array(170),
            'excluded_discovered_candidates' => array(73),
        ),
        'inventory' => array(
            'total_inventory_rows' => 1,
            'products_with_multiple_configured_candidate_attributes' => 0,
        ),
        'counts' => array(
            'discovery_candidates' => 3,
            'inventory_attributes' => 1,
            'proposals_total' => 1,
            'proposal_statuses' => array(
                'normalized' => 1,
                'unchanged' => 0,
                'review_required' => 0,
                'unsupported' => 0,
                'invalid' => 0,
            ),
            'proposal_value_types' => array(
                'single' => 1,
                'range' => 0,
                'compound' => 0,
                'unsupported' => 0,
                'invalid' => 0,
            ),
            'scope_diagnostics' => array(
                'hierarchical_scope_rows' => 1,
                'direct_parent_rows' => 0,
                'rows_without_direct_parent' => 1,
                'products_without_direct_parent' => 1,
            ),
        ),
        'warnings' => array(),
        'safety_markers' => array(
            'read_only' => 1,
            'discovery_completed' => 1,
            'raw_values_inventory_completed' => 1,
            'normalization_proposals_created' => 1,
            'review_package_created' => 0,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'apply_performed' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
            'cache_rebuild_allowed' => 0,
        ),
        'final_pipeline_status' => 'static_test',
    ),
);
$files = $writer->buildFiles($samplePackage);
check_true('package_contains_summary', isset($files['summary.md']));
check_true('summary_contains_job_key', strpos($files['summary.md'], 'submersible_pumps_voltage') !== false);
check_true('summary_contains_runtime_mode', strpos($files['summary.md'], 'live_db_readonly') !== false);
check_true('summary_contains_db_name', strpos($files['summary.md'], 'he_framework_prod_snapshot_20260710') !== false);
check_true('summary_contains_canonical_attribute', strpos($files['summary.md'], 'canonical_attribute_id: 15') !== false);
check_true('summary_contains_proposal_counts', strpos($files['summary.md'], '- normalized: 1') !== false);
check_true('summary_contains_safety_markers', strpos($files['summary.md'], 'sql_generated: 0') !== false);
check_true('summary_contains_artifact_links', strpos($files['summary.md'], '(discovery.md)') !== false && strpos($files['summary.md'], '(manifest.json)') !== false);
check_true('summary_contains_scope_diagnostics', strpos($files['summary.md'], 'rows_without_direct_parent: 1') !== false);
check_true('manifest_contains_summary', strpos($files['manifest.json'], 'summary.md') !== false);
check_true('package_contains_scope_diagnostics', isset($files['scope_diagnostics.md']) && isset($files['scope_diagnostics.json']));
check_true('scope_diagnostics_contains_product', strpos($files['scope_diagnostics.md'], '900001') !== false);
check_true('summary_no_sensitive_markers', !preg_match('/password|secret|username|dsn|credential/i', $files['summary.md']));
check_true('manifest_no_sensitive_markers', !preg_match('/password|secret|username|dsn|credential/i', $files['manifest.json']));
check_true('partial_marker_not_success', strpos($files['summary.md'], 'review_package_created: 0') !== false);

foreach ($writer->getFileNames() as $fileName) {
    check_true('safe_generated_file_' . str_replace('.', '_', $fileName), preg_match('/^[A-Za-z0-9_.-]+$/', $fileName) === 1);
}

echo "static_checks_completed: ok\n";
