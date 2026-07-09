<?php

return array(
    'target_key' => 'max_head',
    'target_meaning' => 'максимальный напор',
    'category_scope_id' => 11900213,
    'canonical_attribute_id' => 12,
    'alias_attribute_ids' => array(101, 119, 81),
    'canonical_unit' => 'm',
    'normalizer_key' => 'simple_meters',

    'source_alias_policy' => 'preserve_until_cleanup',
    'unresolved_policy' => 'exclude',
    'canonical_apply_policy' => 'update_or_insert_canonical_only',
    'alias_cleanup_policy' => 'delete_only_safely_removable_alias_rows',
    'confirmation_required' => true,

    'expected_canonical_already_applied_count' => 481,
    'expected_canonical_update_count_after_cleanup' => 0,
    'expected_canonical_insert_count_after_cleanup' => 0,
    'expected_unresolved_excluded_count' => 14,
    'expected_alias_total_rows_after_cleanup' => 14,
    'expected_alias_safely_removable_after_cleanup' => 0,
    'expected_alias_not_removable_after_cleanup' => 14,
    'expected_alias_unresolved_or_excluded_after_cleanup' => 14,

    'historical_canonical_update_count' => 400,
    'historical_canonical_insert_count' => 81,
    'historical_alias_delete_count' => 81,
    'historical_alias_remaining_count' => 14,

    'allowed_table' => 'oc_product_attribute',
    'allowed_columns' => array(
        'product_id',
        'attribute_id',
        'language_id',
        'text',
    ),

    'canonical_apply_allowed_operations' => array(
        'SELECT',
        'UPDATE canonical_attribute_id only',
        'INSERT canonical_attribute_id only',
    ),
    'alias_cleanup_allowed_operations' => array(
        'SELECT',
        'DELETE alias_attribute_ids only',
    ),

    'forbidden_operations' => array(
        'DELETE canonical_attribute_id',
        'DELETE oc_attribute',
        'DELETE oc_attribute_description',
        'UPDATE alias rows during cleanup',
        'UPDATE oc_attribute',
        'UPDATE oc_attribute_description',
        'cache rebuild',
        'production/cache actions',
        'auto-canonical selection',
        'auto-merge',
    ),

    'runtime_allowlist' => array(
        'controlled_local_dump' => array(
            'runtime_key' => 'controlled_local_dump',
            'runtime_mode' => 'db_readonly',
            'host' => '127.0.1.19',
            'dbname' => 'he_framework_local_dump',
            'db_prefix' => 'oc_',
            'allow_confirm_apply' => true,
            'production_ready' => false,
            'cache_rebuild_allowed' => false,
        ),
    ),

    'transport_allowed' => array(
        'cli' => true,
        'web_admin' => false,
    ),
    'web_admin_enable_requires_separate_gate' => true,

    'references' => array(
        'generic_engine_spec' => 'framework-standardization/docs/GENERIC_ATTRIBUTE_WORKFLOW_ENGINE_IMPLEMENTATION_SPEC.md',
        'runtime_checks' => 'framework-standardization/docs/RUNTIME_CHECKS.md',
        'alias_cleanup_spec' => 'framework-standardization/docs/ALIAS_CLEANUP_MAX_HEAD_SCOPE_11900213.md',
    ),
);
