<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\Pipeline\ReviewPackageWriter;
use FrameworkStandardization\Pipeline\StandardizationJobContractLoader;

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
            'candidate_attribute_ids' => array(15, 57, 79, 99, 118, 170),
            'excluded_attribute_ids' => array(73),
        ),
        'normalization' => array(
            'normalizer_key' => 'voltage',
            'canonical_unit' => 'V',
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
check_fail('canonical_not_in_candidates', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_canonical_not_in_candidates');

$job = valid_job();
$job['target']['excluded_attribute_ids'] = array(57);
check_fail('candidate_also_excluded', function () use ($loader, $job) { $loader->validate($job); }, 'pipeline_candidate_also_excluded');

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
    '220' => array('normalized', 'single'),
    '220V' => array('normalized', 'single'),
    '220 В' => array('normalized', 'single'),
    '380' => array('normalized', 'single'),
    '380V' => array('normalized', 'single'),
    '400' => array('normalized', 'single'),
    '200240' => array('normalized', 'range'),
    '220-240 V' => array('normalized', 'range'),
    '210..240' => array('normalized', 'range'),
    '380..420 В' => array('normalized', 'range'),
    '1  230 В' => array('review_required', 'compound'),
    '1  200240 В (однофазное)' => array('review_required', 'compound'),
    '230 В (1 фаза, 50 Гц)' => array('review_required', 'compound'),
    '1~230 В, 50 Гц / 1~220 В, 60 Гц' => array('review_required', 'compound'),
    'garbage' => array('unsupported', 'unsupported'),
    '' => array('invalid', 'invalid'),
);

foreach ($cases as $rawValue => $expected) {
    $result = $voltage->normalize($rawValue);
    check_true(
        'voltage_' . ($rawValue === '' ? 'empty' : preg_replace('/[^A-Za-z0-9]+/', '_', $rawValue)),
        $result['status'] === $expected[0] && $result['value_type'] === $expected[1]
    );
}

$range = $voltage->normalize('220-240 V');
check_true('range_not_single', $range['value_type'] === 'range' && $range['canonical_value'] === '220-240');
$compound = $voltage->normalize('1  200240 В (однофазное)');
check_true('compound_keeps_phase', $compound['value_type'] === 'compound' && $compound['phase_count'] === 1);
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
