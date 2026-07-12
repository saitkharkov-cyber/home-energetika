<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Registry\CharacteristicRegistryBuilder;

final class CharacteristicRegistryBuilderTestNormalizerRegistry
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

function cr_check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }

    echo $label . ": ok\n";
}

function cr_check_fail($label, $callback, $expectedMessage)
{
    try {
        call_user_func($callback);
        echo $label . ": failed_no_exception\n";
        exit(1);
    } catch (Exception $e) {
        cr_check_true($label, $e->getMessage() === $expectedMessage);
    }
}

function cr_registry()
{
    return new CharacteristicRegistryBuilder(new CharacteristicRegistryBuilderTestNormalizerRegistry(array('voltage', 'simple_meters')));
}

function cr_scope()
{
    return array(
        'root_category_id' => 11900213,
        'scope_mode' => 'hierarchical_category_path_exists',
    );
}

function cr_discovery_row($attributeId, $name, $group, $usageCount, $distinctProducts)
{
    return array(
        'attribute_id' => $attributeId,
        'attribute_name' => $name,
        'attribute_group_name' => $group,
        'usage_count' => $usageCount,
        'distinct_products' => $distinctProducts,
    );
}

function cr_voltage_decision()
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

function cr_has_marker(array $row, $dimension, $marker)
{
    return in_array($marker, $row['status_markers'][$dimension], true);
}

function cr_find_row(array $result, $attributeId)
{
    foreach ($result['rows'] as $row) {
        if ($row['attribute_id'] === $attributeId) {
            return $row;
        }
    }

    echo "find_row_" . $attributeId . ": failed\n";
    exit(1);
}

function cr_result_contains_forbidden_keys($value)
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if (in_array($key, array('sql', 'sql_statements', 'normalized_value', 'normalized_values', 'apply_plan', 'product_id', 'product_ids', 'safe_to_apply'), true)) {
                return true;
            }

            if (cr_result_contains_forbidden_keys($child)) {
                return true;
            }
        }
    }

    return false;
}

cr_check_fail('normalizer_registry_validation', function () {
    new CharacteristicRegistryBuilder(new stdClass());
}, 'characteristic_registry_normalizer_registry_invalid');

$builder = cr_registry();

$badScope = cr_scope();
$badScope['root_category_id'] = 0;
cr_check_fail('scope_root_category_validation', function () use ($builder, $badScope) {
    $builder->build($badScope, array(), array());
}, 'characteristic_registry_root_category_id_invalid');

$badScope = cr_scope();
$badScope['scope_mode'] = 'direct_category_only';
cr_check_fail('scope_mode_validation', function () use ($builder, $badScope) {
    $builder->build($badScope, array(), array());
}, 'characteristic_registry_scope_mode_unsupported');

cr_check_fail('duplicate_discovery_id_rejected', function () use ($builder) {
    $builder->build(cr_scope(), array(
        cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 10, 10),
        cr_discovery_row(15, 'Напряжение дубль', 'Параметры насоса', 1, 1),
    ), array());
}, 'characteristic_registry_duplicate_attribute_id');

$result = $builder->build(cr_scope(), array(
    cr_discovery_row(501, 'Цвет', 'Общие', 3, 3),
), array(cr_voltage_decision()));
$row = cr_find_row($result, 501);
cr_check_true('unmatched_contract_required', $row['legacy_match']['matched'] === false && cr_has_marker($row, 'discovery_contract', 'contract_required'));
cr_check_true('unmatched_discovered', cr_has_marker($row, 'discovery_contract', 'discovered'));
cr_check_true('unmatched_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));

$result = $builder->build(cr_scope(), array(
    cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 400, 400),
), array(cr_voltage_decision()));
$row = cr_find_row($result, 15);
cr_check_true('canonical_approved_role', $row['legacy_match']['role'] === 'canonical');
cr_check_true('canonical_contract_approved', cr_has_marker($row, 'discovery_contract', 'contract_approved'));
cr_check_true('canonical_normalizer_ready', cr_has_marker($row, 'normalizer', 'normalizer_ready'));
cr_check_true('canonical_read_only_ready', cr_has_marker($row, 'processing_review', 'read_only_ready'));

$result = $builder->build(cr_scope(), array(
    cr_discovery_row(57, 'Напряжение питания', 'Параметры насоса', 117, 117),
), array(cr_voltage_decision()));
$row = cr_find_row($result, 57);
cr_check_true('alias_approved_role', $row['legacy_match']['role'] === 'alias');
cr_check_true('alias_read_only_ready', cr_has_marker($row, 'processing_review', 'read_only_ready'));

$result = $builder->build(cr_scope(), array(
    cr_discovery_row(999, 'Напряжение', 'Параметры насоса', 1, 1),
), array(cr_voltage_decision()));
$row = cr_find_row($result, 999);
cr_check_true('same_name_different_id_not_matched', $row['legacy_match']['role'] === 'unmatched' && cr_has_marker($row, 'discovery_contract', 'contract_required'));

$draft = cr_voltage_decision();
$draft['decision_status'] = 'draft';
$draft['normalizer_key'] = '';
$result = $builder->build(cr_scope(), array(
    cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 400, 400),
), array($draft));
$row = cr_find_row($result, 15);
cr_check_true('draft_contract_marker', cr_has_marker($row, 'discovery_contract', 'contract_draft'));
cr_check_true('draft_without_normalizer_ready', !cr_has_marker($row, 'normalizer', 'normalizer_ready'));
cr_check_true('draft_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));

$unknownNormalizer = cr_voltage_decision();
$unknownNormalizer['normalizer_key'] = 'unknown';
$result = $builder->build(cr_scope(), array(
    cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 400, 400),
), array($unknownNormalizer));
$row = cr_find_row($result, 15);
cr_check_true('unknown_normalizer_required', cr_has_marker($row, 'normalizer', 'normalizer_required'));
cr_check_true('unknown_normalizer_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));

$approvedWithoutNormalizer = array(
    'characteristic_key' => 'pump_diameter',
    'decision_status' => 'approved',
    'canonical_attribute_id' => 44,
    'included_alias_attribute_ids' => array(),
    'excluded_attribute_ids' => array(),
    'normalizer_key' => '',
    'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
);
$result = $builder->build(cr_scope(), array(
    cr_discovery_row(44, 'Диаметр насоса', 'Параметры насоса', 50, 50),
), array($approvedWithoutNormalizer));
$row = cr_find_row($result, 44);
cr_check_true('approved_without_normalizer_contract_approved', cr_has_marker($row, 'discovery_contract', 'contract_approved'));
cr_check_true('approved_without_normalizer_required', cr_has_marker($row, 'normalizer', 'normalizer_required'));
cr_check_true('approved_without_normalizer_without_ready', !cr_has_marker($row, 'normalizer', 'normalizer_ready'));
cr_check_true('approved_without_normalizer_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));
cr_check_true('approved_without_normalizer_not_blocked', !cr_has_marker($row, 'discovery_contract', 'blocked') && !cr_has_marker($row, 'normalizer', 'blocked') && !cr_has_marker($row, 'processing_review', 'blocked'));
cr_check_true('approved_without_normalizer_summary', $result['summary'] === array(
    'total_discovered' => 1,
    'contract_required' => 0,
    'contract_draft' => 0,
    'contract_approved' => 1,
    'normalizer_required' => 1,
    'normalizer_ready' => 0,
    'read_only_ready' => 0,
    'blocked' => 0,
));

$result = $builder->build(cr_scope(), array(
    cr_discovery_row(73, 'Напряжение', 'Параметры котла', 2, 2),
), array(cr_voltage_decision()));
$row = cr_find_row($result, 73);
cr_check_true('excluded_role_blocked', $row['legacy_match']['role'] === 'excluded' && cr_has_marker($row, 'processing_review', 'blocked'));
cr_check_true('excluded_reason', in_array('legacy_role_excluded', $row['block_reasons'], true));
cr_check_true('excluded_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));

$otherDecision = array(
    'characteristic_key' => 'voltage_duplicate',
    'decision_status' => 'approved',
    'canonical_attribute_id' => 15,
    'included_alias_attribute_ids' => array(),
    'excluded_attribute_ids' => array(),
    'normalizer_key' => 'voltage',
    'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
);
$result = $builder->build(cr_scope(), array(
    cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 400, 400),
), array(cr_voltage_decision(), $otherDecision));
$row = cr_find_row($result, 15);
cr_check_true('conflict_blocked', cr_has_marker($row, 'processing_review', 'blocked'));
cr_check_true('conflict_reason', in_array('legacy_mapping_conflict', $row['block_reasons'], true));
cr_check_true('conflict_without_read_only_ready', !cr_has_marker($row, 'processing_review', 'read_only_ready'));

$summaryResult = $builder->build(cr_scope(), array(
    cr_discovery_row(501, 'Цвет', 'Общие', 3, 3),
    cr_discovery_row(15, 'Напряжение', 'Параметры насоса', 400, 400),
    cr_discovery_row(57, 'Напряжение питания', 'Параметры насоса', 117, 117),
    cr_discovery_row(73, 'Напряжение', 'Параметры котла', 2, 2),
), array(cr_voltage_decision()));
cr_check_true('summary_counts', $summaryResult['summary'] === array(
    'total_discovered' => 4,
    'contract_required' => 1,
    'contract_draft' => 0,
    'contract_approved' => 3,
    'normalizer_required' => 0,
    'normalizer_ready' => 2,
    'read_only_ready' => 2,
    'blocked' => 1,
));

$sortingResult = $builder->build(cr_scope(), array(
    cr_discovery_row(20, 'Beta', 'Group', 1, 1),
    cr_discovery_row(10, 'Alpha', 'Group', 1, 1),
    cr_discovery_row(5, 'Alpha', 'Group', 1, 1),
), array());
cr_check_true('stable_sorting', $sortingResult['rows'][0]['attribute_id'] === 5 && $sortingResult['rows'][1]['attribute_id'] === 10 && $sortingResult['rows'][2]['attribute_id'] === 20);

cr_check_true('safety_markers', $summaryResult['safety'] === array(
    'read_only' => 1,
    'db_connected' => 0,
    'pipeline_executed' => 0,
    'normalization_performed' => 0,
    'sql_generated' => 0,
    'apply_plan_created' => 0,
    'apply_performed' => 0,
    'product_data_changed' => 0,
    'production_touched' => 0,
    'cache_rebuild_performed' => 0,
));

cr_check_true('result_has_no_sql_normalized_apply_or_product_fields', !cr_result_contains_forbidden_keys($summaryResult));

echo "characteristic_registry_builder_checks_completed: ok\n";
