<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Discovery\DbReadOnlyCharacteristicDiscovery;

final class CharacteristicDiscoveryFakeDb implements ReadOnlyDbConnectionInterface
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

function cd_check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }

    echo $label . ": ok\n";
}

function cd_check_fail($label, $callback, $expectedMessage)
{
    try {
        call_user_func($callback);
        echo $label . ": failed_no_exception\n";
        exit(1);
    } catch (Exception $e) {
        cd_check_true($label, $e->getMessage() === $expectedMessage);
    }
}

function cd_scope()
{
    return array(
        'root_category_id' => 11900213,
        'scope_mode' => 'hierarchical_category_path_exists',
    );
}

function cd_row($attributeId, $name, $group, $usageCount, $distinctProducts)
{
    return array(
        'attribute_id' => $attributeId,
        'attribute_name' => $name,
        'attribute_group_name' => $group,
        'usage_count' => $usageCount,
        'distinct_products' => $distinctProducts,
    );
}

function cd_discovery(array $rows, $prefix, $languageId)
{
    $db = new CharacteristicDiscoveryFakeDb($rows);

    return array(
        'db' => $db,
        'service' => new DbReadOnlyCharacteristicDiscovery($db, $prefix, $languageId),
    );
}

function cd_word_present($sql, $word)
{
    return preg_match('/\b' . preg_quote($word, '/') . '\b/i', $sql) === 1;
}

function cd_result_contains_forbidden_key($value)
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if (in_array($key, array(
                'canonical_attribute_id',
                'included_alias_attribute_ids',
                'excluded_attribute_ids',
                'normalizer_key',
                'status_markers',
                'product_id',
                'product_ids',
                'raw_samples',
                'normalized_value',
                'sql',
                'apply_plan',
                'safe_to_apply',
            ), true)) {
                return true;
            }

            if (cd_result_contains_forbidden_key($child)) {
                return true;
            }
        }
    }

    return false;
}

foreach (array('bad prefix', 'bad-prefix', 'bad;prefix') as $badPrefix) {
    cd_check_fail('invalid_db_prefix_' . preg_replace('/[^A-Za-z0-9]+/', '_', $badPrefix), function () use ($badPrefix) {
        new DbReadOnlyCharacteristicDiscovery(new CharacteristicDiscoveryFakeDb(array()), $badPrefix, 1);
    }, 'characteristic_discovery_db_prefix_invalid');
}

$emptyPrefix = cd_discovery(array(), '', 1);
cd_check_true('empty_db_prefix_allowed', $emptyPrefix['service']->discover(cd_scope()) === array());

foreach (array(0, -1, '1') as $badLanguageId) {
    cd_check_fail('invalid_language_id_' . preg_replace('/[^A-Za-z0-9]+/', '_', (string) $badLanguageId), function () use ($badLanguageId) {
        new DbReadOnlyCharacteristicDiscovery(new CharacteristicDiscoveryFakeDb(array()), 'oc_', $badLanguageId);
    }, 'characteristic_discovery_language_id_invalid');
}

$valid = cd_discovery(array(), 'oc_', 1);
$badScope = cd_scope();
unset($badScope['root_category_id']);
cd_check_fail('missing_root_category', function () use ($valid, $badScope) {
    $valid['service']->discover($badScope);
}, 'characteristic_discovery_root_category_id_invalid');

$badScope = cd_scope();
$badScope['root_category_id'] = 0;
cd_check_fail('zero_root_category', function () use ($valid, $badScope) {
    $valid['service']->discover($badScope);
}, 'characteristic_discovery_root_category_id_invalid');

$badScope = cd_scope();
$badScope['root_category_id'] = '11900213';
cd_check_fail('string_root_category', function () use ($valid, $badScope) {
    $valid['service']->discover($badScope);
}, 'characteristic_discovery_root_category_id_invalid');

$badScope = cd_scope();
$badScope['scope_mode'] = ' ';
cd_check_fail('blank_scope_mode', function () use ($valid, $badScope) {
    $valid['service']->discover($badScope);
}, 'characteristic_discovery_scope_mode_invalid');

$badScope = cd_scope();
$badScope['scope_mode'] = 'direct_category';
cd_check_fail('unsupported_scope_mode', function () use ($valid, $badScope) {
    $valid['service']->discover($badScope);
}, 'characteristic_discovery_scope_mode_unsupported');

$fixture = cd_discovery(array(
    cd_row('57', 'Напряжение', null, '117', '100'),
    cd_row('15', 'Напряжение', 'Параметры насоса', '400', '390'),
    cd_row('44', 'Диаметр', '', '12', '12'),
), 'oc_', 1);
$result = $fixture['service']->discover(cd_scope());
$sql = $fixture['db']->lastSql;

cd_check_true('single_fetch_all', $fixture['db']->fetchAllCalls === 1);
cd_check_true('fetch_one_not_called', $fixture['db']->fetchOneCalls === 0);
cd_check_true('sql_starts_with_select', stripos(ltrim($sql), 'SELECT') === 0);

foreach (array(
    'product_attribute pa',
    'attribute_description ad',
    'attribute_group_description agd',
    'product_to_category scope_p2c',
    'category_path scope_cp',
    'EXISTS',
    'scope_cp.path_id = :root_category_id',
    'pa.language_id = :language_id',
    'COUNT(*)',
    'COUNT(DISTINCT pa.product_id)',
) as $requiredSql) {
    cd_check_true('sql_contains_' . preg_replace('/[^A-Za-z0-9]+/', '_', $requiredSql), strpos($sql, $requiredSql) !== false);
}

foreach (array('LIKE', 'parent_id', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE') as $forbiddenWord) {
    cd_check_true('sql_omits_' . strtolower($forbiddenWord), !cd_word_present($sql, $forbiddenWord));
}

cd_check_true('sql_no_direct_root_only_scope', strpos($sql, 'scope_p2c.category_id = :root_category_id') === false);
cd_check_true('params_exact', $fixture['db']->lastParams === array(':root_category_id' => 11900213, ':language_id' => 1));

cd_check_true('numeric_string_aggregates_to_int', is_int($result[0]['usage_count']) && is_int($result[0]['distinct_products']));

foreach ($result as $row) {
    cd_check_true('output_keys_' . $row['attribute_id'], array_keys($row) === array(
        'attribute_id',
        'attribute_name',
        'attribute_group_name',
        'usage_count',
        'distinct_products',
    ));
}

cd_check_true('same_name_different_ids_not_merged', count($result) === 3);
cd_check_true('null_group_to_empty_string', $result[2]['attribute_id'] === 57 && $result[2]['attribute_group_name'] === '');
cd_check_true('stable_sorting_name_then_id', $result[0]['attribute_id'] === 44 && $result[1]['attribute_id'] === 15 && $result[2]['attribute_id'] === 57);

$empty = cd_discovery(array(), 'oc_', 1);
cd_check_true('empty_db_result_empty_array', $empty['service']->discover(cd_scope()) === array());

cd_check_fail('duplicate_attribute_id_blocked', function () {
    $fixture = cd_discovery(array(
        cd_row('15', 'Напряжение', 'A', '1', '1'),
        cd_row('15', 'Напряжение', 'B', '1', '1'),
    ), 'oc_', 1);
    $fixture['service']->discover(cd_scope());
}, 'characteristic_discovery_duplicate_attribute_id');

cd_check_fail('invalid_attribute_id_blocked', function () {
    $fixture = cd_discovery(array(cd_row('0', 'Напряжение', 'A', '1', '1')), 'oc_', 1);
    $fixture['service']->discover(cd_scope());
}, 'characteristic_discovery_attribute_id_invalid');

cd_check_fail('blank_attribute_name_blocked', function () {
    $fixture = cd_discovery(array(cd_row('15', ' ', 'A', '1', '1')), 'oc_', 1);
    $fixture['service']->discover(cd_scope());
}, 'characteristic_discovery_attribute_name_required');

cd_check_fail('negative_usage_count_blocked', function () {
    $fixture = cd_discovery(array(cd_row('15', 'Напряжение', 'A', '-1', '1')), 'oc_', 1);
    $fixture['service']->discover(cd_scope());
}, 'characteristic_discovery_usage_count_invalid');

cd_check_fail('negative_distinct_products_blocked', function () {
    $fixture = cd_discovery(array(cd_row('15', 'Напряжение', 'A', '1', '-1')), 'oc_', 1);
    $fixture['service']->discover(cd_scope());
}, 'characteristic_discovery_distinct_products_invalid');

cd_check_true('output_has_no_semantic_or_mutation_fields', !cd_result_contains_forbidden_key($result));

echo "characteristic_discovery_checks_completed: ok\n";
