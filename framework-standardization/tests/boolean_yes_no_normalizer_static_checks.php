<?php

require dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php';

use FrameworkStandardization\Normalizer\BooleanYesNoNormalizer;

final class BooleanYesNoNormalizerTestObject
{
    public static $toStringCalls = 0;
    public static $methodCalls = 0;

    public function __toString()
    {
        self::$toStringCalls++;

        return 'unexpected';
    }

    public function marker()
    {
        self::$methodCalls++;
    }
}

function byn_check($label, $condition)
{
    if (!$condition) {
        echo $label . ": failed\n";
        exit(1);
    }
}

function byn_utf8($base64)
{
    return base64_decode($base64);
}

function byn_assert_result($label, array $result, $status, $canonicalValue, $reason)
{
    $expectedKeys = array(
        'status',
        'value_type',
        'canonical_value',
        'unit',
        'warnings',
        'ambiguity_reason',
        'metadata',
    );

    byn_check($label . '_keys', array_keys($result) === $expectedKeys);
    byn_check($label . '_no_reason_field', !array_key_exists('reason', $result));
    byn_check($label . '_value_type', $result['value_type'] === 'boolean_enum');
    byn_check($label . '_unit', $result['unit'] === '');
    byn_check($label . '_canonical_domain', $result['canonical_value'] === null || $result['canonical_value'] === byn_utf8('0JTQsA==') || $result['canonical_value'] === byn_utf8('0J3QtdGC'));
    byn_check($label . '_status', $result['status'] === $status);
    byn_check($label . '_canonical_value', $result['canonical_value'] === $canonicalValue);

    if ($reason === '') {
        byn_check($label . '_successful_warnings', $result['warnings'] === array());
        byn_check($label . '_successful_ambiguity', $result['ambiguity_reason'] === '');
    } else {
        byn_check($label . '_warnings_reason', $result['warnings'] === array($reason));
        byn_check($label . '_ambiguity_reason', $result['ambiguity_reason'] === $reason);
    }

    foreach (array('sql', 'apply', 'product', 'category', 'cache', 'runtime') as $forbiddenKey) {
        byn_check($label . '_no_' . $forbiddenKey, !array_key_exists($forbiddenKey, $result));
    }
}

function byn_assert_string_metadata($label, array $result, $original, $trimmed, $changed)
{
    byn_check($label . '_string_metadata_keys', array_keys($result['metadata']) === array(
        'original_value',
        'trimmed_value',
        'boundary_whitespace_changed',
        'input_type',
    ));
    byn_check($label . '_original', $result['metadata']['original_value'] === $original);
    byn_check($label . '_trimmed', $result['metadata']['trimmed_value'] === $trimmed);
    byn_check($label . '_changed', $result['metadata']['boundary_whitespace_changed'] === $changed);
    byn_check($label . '_input_type', $result['metadata']['input_type'] === 'string');
}

$yes = byn_utf8('0JTQsA==');
$no = byn_utf8('0J3QtdGC');
$nbsp = byn_utf8('wqA=');
$bom = byn_utf8('77u/');
$emSpace = byn_utf8('4oCA');
$thinSpace = byn_utf8('4oCJ');
$unapprovedWhitespace = byn_utf8('4oCL');
$normalizer = new BooleanYesNoNormalizer();

$exactYes = $normalizer->normalize($yes);
byn_assert_result('exact_yes', $exactYes, 'normalized', $yes, '');
byn_assert_string_metadata('exact_yes', $exactYes, $yes, $yes, false);
byn_check('repeated_call_deterministic', $normalizer->normalize($yes) === $exactYes);

$exactNo = $normalizer->normalize($no);
byn_assert_result('exact_no', $exactNo, 'normalized', $no, '');
byn_assert_string_metadata('exact_no', $exactNo, $no, $no, false);

foreach (array(
    'ascii_boundary_yes' => array(" \t" . $yes . "\r\n", $yes),
    'nbsp_boundary_no' => array($nbsp . $no . $nbsp, $no),
    'bom_boundary_yes' => array($bom . $yes . $bom, $yes),
    'multiple_unicode_boundary_yes' => array($emSpace . $nbsp . $bom . $yes . $thinSpace . $emSpace, $yes),
) as $label => $case) {
    $result = $normalizer->normalize($case[0]);
    byn_assert_result($label, $result, 'normalized', $case[1], '');
    byn_assert_string_metadata($label, $result, $case[0], $case[1], true);
}

foreach (array(
    'empty_string' => '',
    'whitespace_only' => " \t" . $nbsp . $bom . "\r\n",
) as $label => $value) {
    $result = $normalizer->normalize($value);
    byn_assert_result($label, $result, 'invalid', null, 'empty_value');
    byn_check($label . '_input_type', $result['metadata']['input_type'] === 'string');
}

$nullResult = $normalizer->normalize(null);
byn_assert_result('null', $nullResult, 'invalid', null, 'empty_value');
byn_check('null_metadata', $nullResult['metadata'] === array('input_type' => 'NULL'));

$unsupportedValues = array(
    'lower_yes' => byn_utf8('0LTQsA=='),
    'upper_yes' => byn_utf8('0JTQkA=='),
    'lower_no' => byn_utf8('0L3QtdGC'),
    'upper_no' => byn_utf8('0J3QldCi'),
    'mixed_case' => byn_utf8('0JRh'),
    'punctuation' => $yes . '.',
    'has' => byn_utf8('0JXRgdGC0Yw='),
    'absent' => byn_utf8('0J7RgtGB0YPRgtGB0YLQstGD0LXRgg=='),
    'string_one' => '1',
    'string_zero' => '0',
    'string_true' => 'true',
    'string_false' => 'false',
    'joined' => $yes . $no,
    'further' => byn_utf8('0JTQsNC70LXQtQ=='),
    'ko_no' => byn_utf8('0JrQvtCd0LXRgg=='),
    'upper_pair' => byn_utf8('0J3QldCiL9CU0JA='),
    'lower_pair' => byn_utf8('0LTQsC/QvdC10YI='),
    'one_token_only' => byn_utf8('0JXRgdGC0Ywv0J3QtdGC'),
    'internal_unicode_whitespace' => $yes . $emSpace . $yes,
    'unapproved_boundary_code_point' => $unapprovedWhitespace . $yes,
);

foreach ($unsupportedValues as $label => $value) {
    $result = $normalizer->normalize($value);
    byn_assert_result($label, $result, 'unsupported', null, 'unsupported_boolean_value');
}

foreach (array(
    'slash' => $yes . '/' . $no,
    'comma' => $yes . ', ' . $no,
    'or' => $yes . ' ' . byn_utf8('0LjQu9C4') . ' ' . $no,
    'either' => $no . ' ' . byn_utf8('0LvQuNCx0L4=') . ' ' . $yes,
    'parentheses' => '(' . $yes . ')  (' . $no . ')',
) as $label => $value) {
    $result = $normalizer->normalize($value);
    byn_assert_result('mixed_' . $label, $result, 'review_required', null, 'mixed_boolean_values');
}

foreach (array(
    'integer_one' => array(1, 'integer'),
    'integer_zero' => array(0, 'integer'),
    'float_one' => array(1.0, 'double'),
    'native_true' => array(true, 'boolean'),
    'native_false' => array(false, 'boolean'),
) as $label => $case) {
    $result = $normalizer->normalize($case[0]);
    byn_assert_result($label, $result, 'invalid', null, 'non_string_scalar_value');
    byn_check($label . '_metadata', $result['metadata'] === array('input_type' => $case[1]));
}

$arrayResult = $normalizer->normalize(array($yes));
byn_assert_result('array', $arrayResult, 'invalid', null, 'non_scalar_value');
byn_check('array_metadata', $arrayResult['metadata'] === array('input_type' => 'array'));

$object = new BooleanYesNoNormalizerTestObject();
$objectResult = $normalizer->normalize($object);
byn_assert_result('object', $objectResult, 'invalid', null, 'non_scalar_value');
byn_check('object_metadata', $objectResult['metadata'] === array('input_type' => 'object', 'class_name' => 'BooleanYesNoNormalizerTestObject'));
byn_check('object_no_to_string', BooleanYesNoNormalizerTestObject::$toStringCalls === 0);
byn_check('object_no_method_calls', BooleanYesNoNormalizerTestObject::$methodCalls === 0);

$resource = fopen('php://memory', 'r+');
$resourcePosition = ftell($resource);
$resourceResult = $normalizer->normalize($resource);
byn_assert_result('resource', $resourceResult, 'invalid', null, 'non_scalar_value');
byn_check('resource_metadata', $resourceResult['metadata'] === array('input_type' => 'resource'));
byn_check('resource_position_unchanged', ftell($resource) === $resourcePosition);
fclose($resource);

$source = file_get_contents(dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php');
foreach (array(
    'PDO', 'mysqli', 'database', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'DROP', 'TRUNCATE', 'CREATE', 'SELECT',
    'file_get_contents', 'file_put_contents', 'fopen', 'fread', 'fwrite',
    'serialize', 'getenv', '$_ENV', '$_SERVER', '$GLOBALS', 'time(', 'microtime(', 'rand(', 'mt_rand(',
    'NormalizerRegistry', 'AttributeContractLoader', 'Pipeline',
) as $forbiddenSourceToken) {
    byn_check('source_omits_' . preg_replace('/[^A-Za-z0-9]+/', '_', $forbiddenSourceToken), strpos($source, $forbiddenSourceToken) === false);
}

echo "boolean_yes_no_normalizer_static_checks: ok\n";
