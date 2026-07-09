<?php

return array(
    'target_key' => 'max_head_synthetic_fixture',
    'target_meaning' => 'максимальный напор synthetic fixture',
    'fixture_only' => true,
    'category_scope_id' => 11900213,
    'category_scope_ids' => array(11900213),
    'canonical_attribute_id' => 12,
    'alias_attribute_ids' => array(101, 119, 81),
    'canonical_unit' => 'm',
    'normalizer_key' => 'simple_meters',
    'confirm_apply_allowed' => false,
    'expected_counts' => array(
        'update_existing_canonical_row_count' => 1,
        'insert_missing_canonical_row_count' => 1,
        'already_applied_count' => 1,
        'unresolved_excluded_count' => 1,
        'duplicate_or_conflict_count' => 2,
        'out_of_scope_ignored_count' => 1,
    ),
    'safety' => array(
        'fixture_only' => true,
        'db_allowed' => false,
        'confirm_apply_allowed' => false,
        'production_ready' => false,
        'cache_rebuild_allowed' => false,
    ),
);
