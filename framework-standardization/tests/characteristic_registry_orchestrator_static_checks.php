<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Discovery\DbReadOnlyCharacteristicDiscovery;
use FrameworkStandardization\Orchestration\DbReadOnlyCharacteristicRegistryOrchestrator;
use FrameworkStandardization\Registry\CharacteristicRegistryBuilder;

final class CharacteristicRegistryOrchestratorFakeDb implements ReadOnlyDbConnectionInterface
{
    private $rows;
    public $fetchAllCalls;
    public $fetchOneCalls;
    public $lastSql;
    public $lastParams;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->fetchAllCalls = 0;
        $this->fetchOneCalls = 0;
        $this->lastSql = '';
        $this->lastParams = array();
    }

    public function fetchOne($sql, array $params)
    {
        $this->fetchOneCalls++;
        throw new \RuntimeException('fetch_one_must_not_be_called');
    }

    public function fetchAll($sql, array $params)
    {
        $this->fetchAllCalls++;
        $this->lastSql = $sql;
        $this->lastParams = $params;

        return $this->rows;
    }
}

final class CharacteristicRegistryOrchestratorNormalizerRegistry
{
    private $keys;

    public function __construct(array $keys)
    {
        $this->keys = array();
        foreach ($keys as $key) {
            $this->keys[(string) $key] = true;
        }
    }

    public function has($key)
    {
        return isset($this->keys[(string) $key]);
    }
}

function cro_check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }

    echo $label . ": ok\n";
}

function cro_scope()
{
    return array(
        'root_category_id' => 11900213,
        'scope_mode' => 'hierarchical_category_path_exists',
    );
}

function cro_db_row($attributeId, $name, $group, $usageCount, $distinctProducts)
{
    return array(
        'attribute_id' => $attributeId,
        'attribute_name' => $name,
        'attribute_group_name' => $group,
        'usage_count' => $usageCount,
        'distinct_products' => $distinctProducts,
    );
}

function cro_voltage_decision()
{
    return array(
        'characteristic_key' => 'voltage',
        'decision_status' => 'approved',
        'canonical_attribute_id' => 15,
        'included_alias_attribute_ids' => array(57),
        'excluded_attribute_ids' => array(73),
        'normalizer_key' => 'voltage',
        'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
    );
}

function cro_orchestrator(array $dbRows)
{
    $db = new CharacteristicRegistryOrchestratorFakeDb($dbRows);
    $discovery = new DbReadOnlyCharacteristicDiscovery($db, 'oc_', 1);
    $builder = new CharacteristicRegistryBuilder(new CharacteristicRegistryOrchestratorNormalizerRegistry(array('voltage')));

    return array(
        'db' => $db,
        'orchestrator' => new DbReadOnlyCharacteristicRegistryOrchestrator($discovery, $builder),
    );
}

function cro_find_row(array $result, $attributeId)
{
    foreach ($result['rows'] as $row) {
        if ($row['attribute_id'] === $attributeId) {
            return $row;
        }
    }

    echo "find_row_" . $attributeId . ": failed\n";
    exit(1);
}

function cro_has_marker(array $row, $dimension, $marker)
{
    return in_array($marker, $row['status_markers'][$dimension], true);
}

function cro_word_present($sql, $word)
{
    return preg_match('/\b' . preg_quote($word, '/') . '\b/i', $sql) === 1;
}

function cro_result_contains_forbidden_key($value)
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if (in_array($key, array(
                'pipeline_executed_result',
                'normalized_value',
                'normalized_values',
                'sql',
                'sql_statements',
                'apply_plan',
                'safe_to_apply',
                'product_id',
                'product_ids',
                'production_operation',
                'cache_operation',
            ), true)) {
                return true;
            }

            if (cro_result_contains_forbidden_key($child)) {
                return true;
            }
        }
    }

    return false;
}

$fixture = cro_orchestrator(array(
    cro_db_row('57', 'Напряжение питания', 'Параметры насоса', '117', '117'),
    cro_db_row('15', 'Напряжение', 'Параметры насоса', '400', '400'),
    cro_db_row('501', 'Цвет', 'Общие', '3', '3'),
));

$result = $fixture['orchestrator']->build(cro_scope(), array(cro_voltage_decision()));
$db = $fixture['db'];

cro_check_true('single_fetch_all', $db->fetchAllCalls === 1);
cro_check_true('fetch_one_not_called', $db->fetchOneCalls === 0);
cro_check_true('sql_read_only_select', stripos(ltrim($db->lastSql), 'SELECT') === 0);

foreach (array('INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE') as $forbiddenWord) {
    cro_check_true('sql_omits_' . strtolower($forbiddenWord), !cro_word_present($db->lastSql, $forbiddenWord));
}

cro_check_true('scope_params_passed_to_discovery', $db->lastParams === array(':root_category_id' => 11900213, ':language_id' => 1));
cro_check_true('builder_payload_type', $result['builder'] === 'read_only_characteristic_registry');
cro_check_true('scope_passed_to_builder', $result['scope'] === cro_scope());
cro_check_true('discovery_rows_reach_builder', count($result['rows']) === 3);

$canonical = cro_find_row($result, 15);
$alias = cro_find_row($result, 57);
$unmatched = cro_find_row($result, 501);

cro_check_true('legacy_canonical_applied', $canonical['legacy_match']['role'] === 'canonical');
cro_check_true('legacy_alias_applied', $alias['legacy_match']['role'] === 'alias');
cro_check_true('unmatched_attribute_preserved', $unmatched['legacy_match']['role'] === 'unmatched');
cro_check_true('canonical_status_markers', cro_has_marker($canonical, 'discovery_contract', 'contract_approved') && cro_has_marker($canonical, 'normalizer', 'normalizer_ready') && cro_has_marker($canonical, 'processing_review', 'read_only_ready'));
cro_check_true('alias_status_markers', cro_has_marker($alias, 'discovery_contract', 'contract_approved') && cro_has_marker($alias, 'processing_review', 'read_only_ready'));
cro_check_true('unmatched_status_markers', cro_has_marker($unmatched, 'discovery_contract', 'discovered') && cro_has_marker($unmatched, 'discovery_contract', 'contract_required'));
cro_check_true('summary_counts', $result['summary'] === array(
    'total_discovered' => 3,
    'contract_required' => 1,
    'contract_draft' => 0,
    'contract_approved' => 2,
    'normalizer_required' => 0,
    'normalizer_ready' => 2,
    'read_only_ready' => 2,
    'blocked' => 0,
));

cro_check_true('orchestrator_marks_db_connected', $result['safety'] === array(
    'read_only' => 1,
    'db_connected' => 1,
    'pipeline_executed' => 0,
    'normalization_performed' => 0,
    'sql_generated' => 0,
    'apply_plan_created' => 0,
    'apply_performed' => 0,
    'product_data_changed' => 0,
    'production_touched' => 0,
    'cache_rebuild_performed' => 0,
));
cro_check_true('orchestrator_keeps_mutation_safety_markers_zero', $result['safety']['pipeline_executed'] === 0 && $result['safety']['normalization_performed'] === 0 && $result['safety']['sql_generated'] === 0 && $result['safety']['apply_plan_created'] === 0 && $result['safety']['apply_performed'] === 0 && $result['safety']['product_data_changed'] === 0 && $result['safety']['production_touched'] === 0 && $result['safety']['cache_rebuild_performed'] === 0);
cro_check_true('no_pipeline_normalization_sql_apply_product_or_cache_fields', !cro_result_contains_forbidden_key($result));

$emptyFixture = cro_orchestrator(array());
$emptyResult = $emptyFixture['orchestrator']->build(cro_scope(), array(cro_voltage_decision()));
cro_check_true('empty_discovery_single_fetch_all', $emptyFixture['db']->fetchAllCalls === 1);
cro_check_true('empty_discovery_registry', $emptyResult['rows'] === array() && $emptyResult['summary'] === array(
    'total_discovered' => 0,
    'contract_required' => 0,
    'contract_draft' => 0,
    'contract_approved' => 0,
    'normalizer_required' => 0,
    'normalizer_ready' => 0,
    'read_only_ready' => 0,
    'blocked' => 0,
));
cro_check_true('empty_discovery_marks_db_connected', $emptyResult['safety']['db_connected'] === 1);
cro_check_true('empty_safety_markers_unchanged', $emptyResult['safety'] === $result['safety']);

echo "characteristic_registry_orchestrator_checks_completed: ok\n";
