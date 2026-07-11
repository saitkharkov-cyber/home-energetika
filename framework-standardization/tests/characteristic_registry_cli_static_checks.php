<?php

$repoDir = dirname(__DIR__);
$repoDir = dirname($repoDir);
$cliPath = $repoDir . DIRECTORY_SEPARATOR . 'framework-standardization' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'characteristic-registry.php';
$tempFiles = array();

function crc_check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        crc_cleanup();
        exit(1);
    }

    echo $label . ": ok\n";
}

function crc_temp_json($contents)
{
    global $tempFiles;

    $path = tempnam(sys_get_temp_dir(), 'char_registry_cli_');
    if ($path === false) {
        echo "temp_file_create: failed\n";
        exit(1);
    }

    file_put_contents($path, $contents);
    $tempFiles[] = $path;

    return $path;
}

function crc_cleanup()
{
    global $tempFiles;

    foreach ($tempFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function crc_run_cli(array $args)
{
    global $cliPath;

    $command = array_merge(array(PHP_BINARY, $cliPath), $args);
    $escaped = array();
    foreach ($command as $part) {
        $escaped[] = crc_shell_arg($part);
    }

    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $process = proc_open('"' . implode(' ', $escaped) . '"', $descriptorSpec, $pipes);
    if (!is_resource($process)) {
        echo "proc_open_cli: failed\n";
        crc_cleanup();
        exit(1);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return array(
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    );
}

function crc_shell_arg($value)
{
    return '"' . str_replace('"', '\\"', (string) $value) . '"';
}

function crc_valid_input()
{
    return array(
        'scope' => array(
            'root_category_id' => 11900213,
            'scope_mode' => 'hierarchical_category_path_exists',
        ),
        'discovered_attributes' => array(
            array(
                'attribute_id' => 15,
                'attribute_name' => 'Напряжение',
                'attribute_group_name' => 'Параметры насоса',
                'usage_count' => 400,
                'distinct_products' => 400,
            ),
            array(
                'attribute_id' => 57,
                'attribute_name' => 'Напряжение питания',
                'attribute_group_name' => 'Параметры насоса',
                'usage_count' => 117,
                'distinct_products' => 117,
            ),
            array(
                'attribute_id' => 501,
                'attribute_name' => 'Цвет',
                'attribute_group_name' => 'Общие',
                'usage_count' => 3,
                'distinct_products' => 3,
            ),
        ),
        'legacy_decisions' => array(
            array(
                'characteristic_key' => 'voltage',
                'decision_status' => 'approved',
                'canonical_attribute_id' => 15,
                'included_alias_attribute_ids' => array(57, 79, 99, 118, 170),
                'excluded_attribute_ids' => array(73),
                'normalizer_key' => 'voltage',
                'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
            ),
        ),
    );
}

function crc_json_file(array $input)
{
    return crc_temp_json(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function crc_assert_error($label, array $result, $marker)
{
    crc_check_true($label . '_exit_code', $result['exit_code'] === 1);
    crc_check_true($label . '_marker', strpos($result['stderr'], 'characteristic_registry_cli_error: ' . $marker) !== false);
    crc_check_true($label . '_stdout_empty', $result['stdout'] === '');
}

function crc_find_row(array $result, $attributeId)
{
    foreach ($result['rows'] as $row) {
        if ($row['attribute_id'] === $attributeId) {
            return $row;
        }
    }

    echo "find_cli_row_" . $attributeId . ": failed\n";
    crc_cleanup();
    exit(1);
}

function crc_has_marker(array $row, $dimension, $marker)
{
    return in_array($marker, $row['status_markers'][$dimension], true);
}

$result = crc_run_cli(array());
crc_assert_error('input_required', $result, 'characteristic_registry_cli_input_required');

$result = crc_run_cli(array(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'missing_characteristic_registry_input.json'));
crc_assert_error('missing_file', $result, 'characteristic_registry_cli_input_file_not_found');

$malformed = crc_temp_json('{bad json');
$result = crc_run_cli(array($malformed));
crc_assert_error('malformed_json', $result, 'characteristic_registry_cli_input_json_invalid');

$listRoot = crc_temp_json('[{"scope":{}}]');
$result = crc_run_cli(array($listRoot));
crc_assert_error('list_root', $result, 'characteristic_registry_cli_input_root_invalid');

$input = crc_valid_input();
unset($input['scope']);
$result = crc_run_cli(array(crc_json_file($input)));
crc_assert_error('missing_scope', $result, 'characteristic_registry_cli_scope_required');

$input = crc_valid_input();
unset($input['discovered_attributes']);
$result = crc_run_cli(array(crc_json_file($input)));
crc_assert_error('missing_discovered_attributes', $result, 'characteristic_registry_cli_discovered_attributes_required');

$input = crc_valid_input();
unset($input['legacy_decisions']);
$result = crc_run_cli(array(crc_json_file($input)));
crc_assert_error('missing_legacy_decisions', $result, 'characteristic_registry_cli_legacy_decisions_required');

$result = crc_run_cli(array('--unknown'));
crc_assert_error('unknown_argument', $result, 'characteristic_registry_cli_unexpected_argument');

$validPath = crc_json_file(crc_valid_input());
$result = crc_run_cli(array($validPath));
crc_check_true('valid_compact_exit_code', $result['exit_code'] === 0);
crc_check_true('valid_compact_stderr_empty', $result['stderr'] === '');
$decoded = json_decode($result['stdout'], true);
crc_check_true('valid_compact_json_decodes', is_array($decoded));
crc_check_true('valid_compact_builder', $decoded['builder'] === 'read_only_characteristic_registry');

$canonical = crc_find_row($decoded, 15);
crc_check_true('canonical_role', $canonical['legacy_match']['role'] === 'canonical');
crc_check_true('canonical_contract_approved', crc_has_marker($canonical, 'discovery_contract', 'contract_approved'));
crc_check_true('canonical_normalizer_ready', crc_has_marker($canonical, 'normalizer', 'normalizer_ready'));
crc_check_true('canonical_read_only_ready', crc_has_marker($canonical, 'processing_review', 'read_only_ready'));

$alias = crc_find_row($decoded, 57);
crc_check_true('alias_role', $alias['legacy_match']['role'] === 'alias');

$unmatched = crc_find_row($decoded, 501);
crc_check_true('unmatched_role', $unmatched['legacy_match']['role'] === 'unmatched');
crc_check_true('unmatched_discovered', crc_has_marker($unmatched, 'discovery_contract', 'discovered'));
crc_check_true('unmatched_contract_required', crc_has_marker($unmatched, 'discovery_contract', 'contract_required'));

$prettyResult = crc_run_cli(array($validPath, '--pretty'));
crc_check_true('pretty_exit_code', $prettyResult['exit_code'] === 0);
crc_check_true('pretty_contains_newlines', substr_count($prettyResult['stdout'], "\n") > 1);
crc_check_true('pretty_json_valid', is_array(json_decode($prettyResult['stdout'], true)));

crc_check_true('safety_read_only', $decoded['safety']['read_only'] === 1);
foreach (array('db_connected', 'pipeline_executed', 'normalization_performed', 'sql_generated', 'apply_plan_created', 'apply_performed', 'product_data_changed', 'production_touched', 'cache_rebuild_performed') as $marker) {
    crc_check_true('safety_' . $marker, $decoded['safety'][$marker] === 0);
}

crc_check_true('stdout_has_no_sql_stack_progress_or_markdown', strpos($result['stdout'], 'SQL') === false
    && strpos($result['stdout'], 'Stack trace') === false
    && strpos($result['stdout'], 'progress') === false
    && strpos($result['stdout'], '#') === false);

$runtimeDir = $repoDir . DIRECTORY_SEPARATOR . 'framework-standardization' . DIRECTORY_SEPARATOR . 'runtime';
crc_check_true('cli_does_not_create_runtime_output_file', !is_file($runtimeDir . DIRECTORY_SEPARATOR . 'characteristic-registry.json'));

crc_cleanup();
echo "characteristic_registry_cli_checks_completed: ok\n";
