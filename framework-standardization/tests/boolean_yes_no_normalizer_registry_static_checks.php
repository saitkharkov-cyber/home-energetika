<?php

require dirname(__DIR__) . '/src/Normalizer/SimpleMetersNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/VoltageNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/NormalizerRegistry.php';

use FrameworkStandardization\Normalizer\BooleanYesNoNormalizer;
use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use FrameworkStandardization\Normalizer\VoltageNormalizer;

function bynr_check($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }
}

function bynr_utf8($base64)
{
    return base64_decode($base64);
}

function bynr_check_unknown(NormalizerRegistry $registry)
{
    try {
        $registry->get('unknown');
        bynr_check('unknown_key_exception', false);
    } catch (InvalidArgumentException $e) {
        bynr_check('unknown_key_message', $e->getMessage() === 'pipeline_normalizer_unknown');
    }
}

function bynr_source_has_identifier($source, $identifier)
{
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_STRING && $token[1] === $identifier) {
            return true;
        }
    }

    return false;
}

$registry = NormalizerRegistry::createDefault();

bynr_check('has_simple_meters', $registry->has('simple_meters') === true);
bynr_check('has_voltage', $registry->has('voltage') === true);
bynr_check('has_boolean_yes_no', $registry->has('boolean_yes_no') === true);

bynr_check('simple_meters_class', $registry->get('simple_meters') instanceof SimpleMetersNormalizer);
bynr_check('voltage_class', $registry->get('voltage') instanceof VoltageNormalizer);

$booleanNormalizer = $registry->get('boolean_yes_no');
bynr_check('boolean_yes_no_class', $booleanNormalizer instanceof BooleanYesNoNormalizer);
bynr_check('boolean_yes_no_same_instance', $registry->get('boolean_yes_no') === $booleanNormalizer);
bynr_check('boolean_yes_no_trimmed_has', $registry->has(' boolean_yes_no ') === true);
bynr_check('boolean_yes_no_trimmed_get', $registry->get(' boolean_yes_no ') === $booleanNormalizer);
bynr_check_unknown($registry);

$yes = bynr_utf8('0JTQsA==');
$result = $booleanNormalizer->normalize($yes);
bynr_check('registered_normalize_status', $result['status'] === 'normalized');
bynr_check('registered_normalize_value', $result['canonical_value'] === $yes);
bynr_check('registered_normalize_type', $result['value_type'] === 'boolean_enum');
bynr_check('registered_normalize_deterministic', $registry->get('boolean_yes_no')->normalize($yes) === $result);

$registrySource = file_get_contents(dirname(__DIR__) . '/src/Normalizer/NormalizerRegistry.php');
$testSource = file_get_contents(__FILE__);

foreach (array(
    'PDO', 'mysqli', 'database', 'AttributeContractLoader', 'Pipeline', 'file_get_contents',
    'file_put_contents', 'fopen', 'fread', 'fwrite', 'glob', 'scandir', 'DirectoryIterator',
    'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE', 'CREATE', 'SELECT',
) as $forbiddenRegistryToken) {
    bynr_check('registry_source_omits_' . preg_replace('/[^A-Za-z0-9]+/', '_', $forbiddenRegistryToken), !bynr_source_has_identifier($registrySource, $forbiddenRegistryToken));
}

foreach (array(
    'PDO', 'mysqli', 'database', 'AttributeContractLoader', 'Pipeline', 'glob', 'scandir',
    'DirectoryIterator', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE',
    'CREATE', 'SELECT',
) as $forbiddenTestToken) {
    bynr_check('test_source_omits_' . preg_replace('/[^A-Za-z0-9]+/', '_', $forbiddenTestToken), !bynr_source_has_identifier($testSource, $forbiddenTestToken));
}

echo "boolean_yes_no_normalizer_registry_static_checks: ok\n";
