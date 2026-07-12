<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Review\CharacteristicRegistryGate1ReviewQueueBuilder;

function crg_check_true($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }

    echo $label . ": ok\n";
}

function crg_check_fail($label, $callback, $expectedMessage)
{
    try {
        call_user_func($callback);
        echo $label . ": failed_no_exception\n";
        exit(1);
    } catch (Exception $e) {
        crg_check_true($label, $e->getMessage() === $expectedMessage);
    }
}

function crg_row($attributeId, $name, $group, $usageCount, $distinctProducts, array $legacyMatch, array $statusMarkers, array $blockReasons)
{
    return array(
        'attribute_id' => $attributeId,
        'attribute_name' => $name,
        'attribute_group_name' => $group,
        'usage_count' => $usageCount,
        'distinct_products' => $distinctProducts,
        'legacy_match' => $legacyMatch,
        'status_markers' => $statusMarkers,
        'block_reasons' => $blockReasons,
    );
}

function crg_required_row($attributeId, $name, $group, $usageCount, $distinctProducts)
{
    return crg_row(
        $attributeId,
        $name,
        $group,
        $usageCount,
        $distinctProducts,
        array('matched' => false, 'role' => 'unmatched'),
        array(
            'discovery_contract' => array('discovered', 'contract_required'),
            'normalizer' => array(),
            'processing_review' => array(),
        ),
        array()
    );
}

function crg_approved_row($attributeId, $name, $group, $usageCount, $distinctProducts, $key)
{
    return crg_row(
        $attributeId,
        $name,
        $group,
        $usageCount,
        $distinctProducts,
        array(
            'matched' => true,
            'characteristic_key' => $key,
            'decision_status' => 'approved',
            'role' => 'canonical',
            'canonical_attribute_id' => $attributeId,
            'normalizer_key' => '',
            'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
        ),
        array(
            'discovery_contract' => array('discovered', 'contract_approved'),
            'normalizer' => array('normalizer_required'),
            'processing_review' => array(),
        ),
        array()
    );
}

function crg_voltage_ready_row()
{
    return crg_row(
        15,
        'Напряжение',
        'Параметры насоса',
        400,
        400,
        array(
            'matched' => true,
            'characteristic_key' => 'voltage',
            'decision_status' => 'approved',
            'role' => 'canonical',
            'canonical_attribute_id' => 15,
            'normalizer_key' => 'voltage',
            'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
        ),
        array(
            'discovery_contract' => array('discovered', 'contract_approved'),
            'normalizer' => array('normalizer_ready'),
            'processing_review' => array('read_only_ready'),
        ),
        array()
    );
}

function crg_excluded_voltage_row()
{
    return crg_row(
        73,
        'Напряжение сети, В',
        'Параметры котла',
        3,
        3,
        array(
            'matched' => true,
            'characteristic_key' => 'voltage',
            'decision_status' => 'approved',
            'role' => 'excluded',
            'canonical_attribute_id' => 15,
            'normalizer_key' => 'voltage',
            'provenance' => 'framework-standardization/docs/LEGACY_DECISIONS.md',
        ),
        array(
            'discovery_contract' => array('discovered', 'contract_approved'),
            'normalizer' => array(),
            'processing_review' => array('blocked'),
        ),
        array('legacy_role_excluded')
    );
}

function crg_registry_fixture()
{
    return array(
        'builder' => 'read_only_characteristic_registry',
        'scope' => array(
            'root_category_id' => 11900213,
            'scope_mode' => 'hierarchical_category_path_exists',
        ),
        'rows' => array(
            crg_required_row(74, 'Вес, кг', 'Параметры насоса', 4, 4),
            crg_required_row(93, 'Вес, кг', 'Насосы Pedrollo', 87, 87),
            crg_required_row(127, 'Вес, кг', 'Параметры насоса', 1, 1),
            crg_excluded_voltage_row(),
            crg_required_row(79, 'Напряжение сети, В', 'Насосы Pedrollo', 88, 88),
            crg_required_row(118, 'Напряжение сети, В', 'Параметры насоса', 1, 1),
            crg_voltage_ready_row(),
            crg_required_row(99, 'Напряжение питания, В', 'Насосы Pedrollo', 5, 5),
            crg_approved_row(44, 'Диаметр насоса', 'Прочие', 400, 400, 'pump_diameter'),
            crg_required_row(106, 'Диаметр насоса, дюйм', 'Насосы Pedrollo', 4, 4),
            crg_required_row(17, 'Корпус насоса', 'Параметры насоса', 78, 78),
            crg_required_row(50, 'Тип насоса', 'Прочие', 118, 118),
        ),
        'summary' => array(
            'total_discovered' => 12,
            'contract_required' => 9,
            'contract_draft' => 0,
            'contract_approved' => 3,
            'normalizer_required' => 1,
            'normalizer_ready' => 1,
            'read_only_ready' => 1,
            'blocked' => 1,
        ),
        'safety' => array(
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
        ),
    );
}

function crg_small_registry(array $rows, $dbConnected)
{
    return array(
        'builder' => 'read_only_characteristic_registry',
        'scope' => array(
            'root_category_id' => 11900213,
            'scope_mode' => 'hierarchical_category_path_exists',
        ),
        'rows' => $rows,
        'summary' => array(
            'total_discovered' => count($rows),
            'contract_required' => count($rows),
            'contract_draft' => 0,
            'contract_approved' => 0,
            'normalizer_required' => 0,
            'normalizer_ready' => 0,
            'read_only_ready' => 0,
            'blocked' => 0,
        ),
        'safety' => array(
            'read_only' => 1,
            'db_connected' => $dbConnected,
            'pipeline_executed' => 0,
            'normalization_performed' => 0,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'apply_performed' => 0,
            'product_data_changed' => 0,
            'production_touched' => 0,
            'cache_rebuild_performed' => 0,
        ),
    );
}

function crg_ids(array $rows)
{
    $ids = array();
    foreach ($rows as $row) {
        $ids[] = $row['attribute_id'];
    }
    sort($ids, SORT_NUMERIC);

    return $ids;
}

function crg_find_group_by_ids(array $groups, array $ids)
{
    sort($ids, SORT_NUMERIC);
    foreach ($groups as $group) {
        $groupIds = $group['attribute_ids'];
        sort($groupIds, SORT_NUMERIC);
        if ($groupIds === $ids) {
            return $group;
        }
    }

    echo "find_group: failed\n";
    exit(1);
}

function crg_find_item(array $items, $attributeId)
{
    foreach ($items as $item) {
        if ($item['attribute_id'] === $attributeId) {
            return $item;
        }
    }

    echo "find_item_" . $attributeId . ": failed\n";
    exit(1);
}

function crg_has_item(array $items, $attributeId)
{
    foreach ($items as $item) {
        if ($item['attribute_id'] === $attributeId) {
            return true;
        }
    }

    return false;
}

function crg_find_link(array $links, $leftId, $rightId)
{
    if ($leftId > $rightId) {
        $tmp = $leftId;
        $leftId = $rightId;
        $rightId = $tmp;
    }

    foreach ($links as $link) {
        if ($link['left_attribute_id'] === $leftId && $link['right_attribute_id'] === $rightId) {
            return $link;
        }
    }

    return null;
}

function crg_result_contains_key($value, $needle)
{
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if ($key === $needle || crg_result_contains_key($child, $needle)) {
                return true;
            }
        }
    }

    return false;
}

$builder = new CharacteristicRegistryGate1ReviewQueueBuilder();
$fixture = crg_registry_fixture();
$before = serialize($fixture);
$result = $builder->build($fixture);

crg_check_true('output_builder', $result['builder'] === 'characteristic_registry_gate1_review_queue');
crg_check_true('scope_preserved', $result['scope'] === $fixture['scope']);
crg_check_true('input_registry_not_mutated', serialize($fixture) === $before);
crg_check_true('review_items_only_contract_required', crg_ids($result['review_items']) === array(17, 50, 74, 79, 93, 99, 106, 118, 127));
crg_check_true('approved_context_not_review_item', !crg_has_item($result['review_items'], 15) && !crg_has_item($result['review_items'], 44) && !crg_has_item($result['review_items'], 73));

$weightGroup = crg_find_group_by_ids($result['exact_duplicate_groups'], array(74, 93, 127));
crg_check_true('exact_weight_group_ids', $weightGroup['contract_required_ids'] === array(74, 93, 127) && $weightGroup['context_ids'] === array());
crg_check_true('exact_weight_group_candidate_only', $weightGroup['candidate_only'] === 1 && $weightGroup['review_required'] === 1);
crg_check_true('exact_weight_group_sums', $weightGroup['usage_count_sum'] === 92 && $weightGroup['distinct_products_sum'] === 92);

$voltageGroup = crg_find_group_by_ids($result['exact_duplicate_groups'], array(73, 79, 118));
crg_check_true('exact_group_with_blocked_context_ids', $voltageGroup['contract_required_ids'] === array(79, 118) && $voltageGroup['context_ids'] === array(73));
crg_check_true('blocked_context_not_review_item', !crg_has_item($result['review_items'], 73));

$link1599 = crg_find_link($result['related_candidate_links'], 15, 99);
crg_check_true('related_approved_context_token_subset', is_array($link1599) && in_array('token_subset', $link1599['reason_codes'], true));
crg_check_true('related_voltage_similarity', $link1599['shared_tokens'] === array('напряжение') && $link1599['similarity_percent'] === 67);
crg_check_true('approved_context_not_review_item_for_related', !crg_has_item($result['review_items'], 15));

$link44106 = crg_find_link($result['related_candidate_links'], 44, 106);
crg_check_true('related_two_token_evidence', is_array($link44106) && in_array('shared_tokens_2_plus', $link44106['reason_codes'], true) && in_array('диаметр', $link44106['shared_tokens'], true) && in_array('насоса', $link44106['shared_tokens'], true));
crg_check_true('related_diameter_similarity', $link44106['shared_tokens'] === array('диаметр', 'насоса') && $link44106['similarity_percent'] === 80);
crg_check_true('diameter_context_not_review_item', !crg_has_item($result['review_items'], 44));

crg_check_true('negative_single_generic_token_no_link', crg_find_link($result['related_candidate_links'], 17, 50) === null);
crg_check_true('links_candidate_only', $link1599['candidate_only'] === 1 && $link1599['review_required'] === 1 && $link44106['candidate_only'] === 1 && $link44106['review_required'] === 1);

$item99 = crg_find_item($result['review_items'], 99);
$item106 = crg_find_item($result['review_items'], 106);
crg_check_true('review_item_related_ids_sorted', $item99['related_candidate_ids'] === array(15) && $item106['related_candidate_ids'] === array(44));
crg_check_true('review_item_exact_group_id', crg_find_item($result['review_items'], 79)['exact_duplicate_group_id'] === $voltageGroup['group_id']);
crg_check_true('review_item_candidate_only', $item99['review_status'] === 'review_required' && $item99['candidate_only'] === 1);

crg_check_true('deterministic_exact_group_sorting', $result['exact_duplicate_groups'][0]['normalized_name'] === 'вес кг' && $result['exact_duplicate_groups'][1]['normalized_name'] === 'напряжение сети в');
crg_check_true('deterministic_link_sorting', $result['related_candidate_links'][0]['left_attribute_id'] === 15 && $result['related_candidate_links'][0]['right_attribute_id'] === 79);
crg_check_true('summary_counts', $result['summary'] === array(
    'registry_rows_total' => 12,
    'contract_required_total' => 9,
    'exact_duplicate_groups_total' => 2,
    'contract_required_in_exact_groups' => 5,
    'contract_required_singletons' => 4,
    'related_candidate_links_total' => 4,
    'auto_decisions_total' => 0,
));
crg_check_true('summary_arithmetic', $result['summary']['contract_required_in_exact_groups'] + $result['summary']['contract_required_singletons'] === $result['summary']['contract_required_total']);
crg_check_true('no_auto_decision_fields', $result['summary']['auto_decisions_total'] === 0 && !crg_result_contains_key($result, 'canonical_attribute_id') && !crg_result_contains_key($result, 'normalizer_key'));
crg_check_true('safety_markers', $result['safety'] === array(
    'read_only' => 1,
    'db_connected' => 0,
    'source_registry_db_connected' => 1,
    'semantic_decisions_applied' => 0,
    'contracts_created' => 0,
    'aliases_approved' => 0,
    'exclusions_approved' => 0,
    'normalizers_assigned' => 0,
    'pipeline_executed' => 0,
    'normalization_performed' => 0,
    'sql_generated' => 0,
    'apply_performed' => 0,
    'product_data_changed' => 0,
    'production_touched' => 0,
    'cache_rebuild_performed' => 0,
));

$normalizationResult = $builder->build(crg_small_registry(array(
    crg_required_row(201, 'ЁМКОСТЬ / БАКА', 'Fixture', 1, 1),
    crg_required_row(202, 'емкость бака', 'Fixture', 1, 1),
    crg_required_row(203, 'ҐРУНТ', 'Fixture', 1, 1),
    crg_required_row(204, 'ґрунт', 'Fixture', 1, 1),
), 0));
$capacityGroup = crg_find_group_by_ids($normalizationResult['exact_duplicate_groups'], array(201, 202));
$soilGroup = crg_find_group_by_ids($normalizationResult['exact_duplicate_groups'], array(203, 204));
crg_check_true('normalization_cyrillic_uppercase_yo_punctuation_spaces', $capacityGroup['normalized_name'] === 'емкость бака');
crg_check_true('normalization_ukrainian_uppercase', $soilGroup['normalized_name'] === 'ґрунт');
crg_check_true('source_db_connected_zero_preserved', $normalizationResult['safety']['db_connected'] === 0 && $normalizationResult['safety']['source_registry_db_connected'] === 0);

crg_check_fail('malformed_registry_rejected', function () use ($builder) {
    $builder->build(array('builder' => 'other'));
}, 'characteristic_registry_gate1_registry_builder_invalid');

crg_check_fail('malformed_row_rejected', function () use ($builder, $fixture) {
    unset($fixture['rows'][0]['status_markers']['normalizer']);
    $builder->build($fixture);
}, 'characteristic_registry_gate1_status_markers_invalid');

crg_check_fail('duplicate_attribute_id_rejected', function () use ($builder, $fixture) {
    $fixture['rows'][] = $fixture['rows'][0];
    $builder->build($fixture);
}, 'characteristic_registry_gate1_duplicate_attribute_id');

crg_check_fail('missing_source_db_connected_rejected', function () use ($builder, $fixture) {
    unset($fixture['safety']['db_connected']);
    $builder->build($fixture);
}, 'characteristic_registry_gate1_source_db_connected_invalid');

crg_check_fail('string_source_db_connected_rejected', function () use ($builder, $fixture) {
    $fixture['safety']['db_connected'] = '1';
    $builder->build($fixture);
}, 'characteristic_registry_gate1_source_db_connected_invalid');

crg_check_fail('boolean_source_db_connected_rejected', function () use ($builder, $fixture) {
    $fixture['safety']['db_connected'] = true;
    $builder->build($fixture);
}, 'characteristic_registry_gate1_source_db_connected_invalid');

echo "characteristic_registry_gate1_review_queue_checks_completed: ok\n";
