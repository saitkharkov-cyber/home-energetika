<?php

return array(
    'job_id' => 'pump_diameter_borehole_pumps_db_readonly',
    'job_name' => 'DB-readonly проверка диаметра насоса в категории Скважинные насосы',

    'canonical' => array(
        'canonical_code' => 'pump_diameter',
    ),

    'scope' => array(
        'type' => 'category',
        'category_id' => 11900213,
        'category_name' => 'Скважинные насосы',
        'include_subcategories' => 1,
    ),

    'source' => array(
        'type' => 'opencart_db',
        'database' => 'local_dump',
        'language_id' => 1,
    ),

    'value_rules' => array(
        'value_parser' => 'diameter_mm',
        'value_type' => 'decimal',
        'unit' => 'mm',
        'allow_empty' => 0,
        'normalize_spaces' => 1,
        'unknown_value_policy' => 'block_sql',
    ),

    'analysis_rules' => array(
        'collect_usage_count' => 1,
        'collect_sample_values' => 1,
        'max_sample_values' => 20,
        'propose_synonyms' => 1,
        'frequency_is_diagnostic_only' => 1,
    ),

    'output' => array(
        'generate_report' => 1,
        'generate_sql_preview' => 1,
        'generate_value_report' => 1,
        'generate_unknown_values_report' => 1,
        'apply_changes' => 0,
    ),
);
