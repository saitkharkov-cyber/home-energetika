<?php

return array(
    'job_version' => 1,
    'job_key' => 'submersible_pumps_voltage',
    'runtime_config' => 'framework-standardization/config/runtime/prod.snapshot.local.php',
    'scope' => array(
        'category_ids' => array(11900213),
    ),
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
