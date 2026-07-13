<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Normalizer\BooleanYesNoNormalizer;
use FrameworkStandardization\Normalizer\SimpleMetersNormalizer;
use FrameworkStandardization\Preview\DbReadOnlyContractNormalizationPreview;

final class PreviewNormalizationFakeDb implements ReadOnlyDbConnectionInterface
{
    public $requests = array();

    private $categoryRows;
    private $productRows;
    private $attributeRows;

    public function __construct(array $categoryRows, array $productRows, array $attributeRows)
    {
        $this->categoryRows = $categoryRows;
        $this->productRows = $productRows;
        $this->attributeRows = $attributeRows;
    }

    public function fetchOne($sql, array $params)
    {
        $rows = $this->fetchAll($sql, $params);

        return count($rows) > 0 ? $rows[0] : array();
    }

    public function fetchAll($sql, array $params)
    {
        $this->assertReadOnlySql($sql);
        $this->requests[] = array(
            'sql' => $sql,
            'params' => $params,
        );

        if (strpos($sql, 'FROM oc_category') !== false) {
            return $this->categoryRows;
        }

        if (strpos($sql, 'SELECT DISTINCT product_id') !== false) {
            return $this->productRows;
        }

        if (strpos($sql, 'FROM oc_product_attribute pa') !== false) {
            return $this->attributeRows;
        }

        throw new RuntimeException('fake_db_query_not_recognized');
    }

    private function assertReadOnlySql($sql)
    {
        if (!preg_match('/^SELECT\b/i', trim($sql))) {
            throw new RuntimeException('fake_db_sql_not_select');
        }

        if (strpos($sql, ';') !== false) {
            throw new RuntimeException('fake_db_multi_statement_not_allowed');
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $sql)) {
            throw new RuntimeException('fake_db_write_sql_not_allowed');
        }
    }
}

final class PreviewDomainViolationNormalizer
{
    public function normalize($rawValue)
    {
        return array(
            'status' => 'normalized',
            'canonical_value' => 'Maybe',
            'ambiguity_reason' => '',
            'warnings' => array(),
            'metadata' => array(),
        );
    }
}

final class PreviewScalarResultNormalizer
{
    public function normalize($rawValue)
    {
        return 'not-an-array';
    }
}

final class PreviewMysteryStatusNormalizer
{
    public function normalize($rawValue)
    {
        return array(
            'status' => 'mystery',
            'canonical_value' => 'Да',
            'ambiguity_reason' => '',
            'warnings' => array(),
            'metadata' => array(),
        );
    }
}

final class PreviewNormalizerWithoutNormalizeMethod
{
}

function previewFail($label, $details)
{
    fwrite(STDERR, $label . ': failed');

    if ($details !== '') {
        fwrite(STDERR, ' — ' . $details);
    }

    fwrite(STDERR, "\n");
    exit(1);
}

function previewAssertTrue($label, $condition)
{
    if (!$condition) {
        previewFail($label, 'condition is false');
    }
}

function previewAssertSame($label, $expected, $actual)
{
    if ($expected !== $actual) {
        previewFail(
            $label,
            'expected ' . var_export($expected, true) . ', actual ' . var_export($actual, true)
        );
    }
}

function previewAssertExceptionMessage($label, $expectedMessage, $callback)
{
    try {
        call_user_func($callback);
    } catch (Exception $exception) {
        previewAssertSame($label, $expectedMessage, $exception->getMessage());
        return;
    }

    previewFail($label, 'expected exception was not thrown');
}

function previewContainsRecursiveKey($value, array $forbiddenKeys)
{
    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, $forbiddenKeys, true)) {
            return true;
        }

        if (previewContainsRecursiveKey($item, $forbiddenKeys)) {
            return true;
        }
    }

    return false;
}

function previewFindSampleByRawValue(array $samples, $rawValue)
{
    foreach ($samples as $sample) {
        if ($sample['raw_value'] === $rawValue) {
            return $sample;
        }
    }

    return null;
}

function previewFindEvidenceCheck(array $checks, $name)
{
    foreach ($checks as $check) {
        if ($check['name'] === $name) {
            return $check;
        }
    }

    return null;
}

function previewParamValues(array $params)
{
    $values = array_values($params);
    sort($values, SORT_NUMERIC);

    return $values;
}

function previewBooleanContract()
{
    return array(
        'target_key' => 'fixture_boolean',
        'target_meaning' => 'fixture boolean',
        'category_scope_id' => 11900213,
        'scope_mode' => 'hierarchical_category_path_exists',
        'canonical_attribute_id' => 47,
        'alias_attribute_ids' => array(82),
        'normalizer_key' => 'boolean_yes_no',
        'read_only_ready' => true,
        'apply_ready' => false,
        'allowed_table' => 'oc_product_attribute',
        'allowed_canonical_values' => array('Да', 'Нет'),
    );
}

function previewCategoryRows()
{
    return array(
        array('category_id' => 11900213, 'parent_id' => 0),
        array('category_id' => 11900321, 'parent_id' => 11900213),
        array('category_id' => 11900400, 'parent_id' => 11900321),
        array('category_id' => 999, 'parent_id' => 0),
    );
}

function previewBooleanProductRows()
{
    $rows = array();

    foreach (range(101, 108) as $productId) {
        $rows[] = array('product_id' => $productId);
    }

    return $rows;
}

function previewBooleanAttributeRows()
{
    return array(
        array('product_id' => 101, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'Да'),
        array('product_id' => 102, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'Нет'),
        array('product_id' => 103, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'Да'),
        array('product_id' => 104, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'Да'),
        array('product_id' => 103, 'attribute_id' => 82, 'language_id' => 1, 'text' => 'Да'),
        array('product_id' => 104, 'attribute_id' => 82, 'language_id' => 1, 'text' => 'Нет'),
        array('product_id' => 105, 'attribute_id' => 82, 'language_id' => 1, 'text' => 'Да / Нет'),
        array('product_id' => 106, 'attribute_id' => 82, 'language_id' => 1, 'text' => 'maybe'),
        array('product_id' => 107, 'attribute_id' => 82, 'language_id' => 1, 'text' => ''),
        array('product_id' => 108, 'attribute_id' => 82, 'language_id' => 1, 'text' => 'Да'),
    );
}

function previewBuildBooleanFakeDb()
{
    return new PreviewNormalizationFakeDb(
        previewCategoryRows(),
        previewBooleanProductRows(),
        previewBooleanAttributeRows()
    );
}

function previewRunBoolean(array $contract)
{
    $db = previewBuildBooleanFakeDb();
    $preview = new DbReadOnlyContractNormalizationPreview(
        $db,
        'oc_',
        $contract,
        new BooleanYesNoNormalizer()
    );

    return array($preview->generate('db_readonly'), $db);
}

$baseContract = previewBooleanContract();
$baseContractBefore = $baseContract;
list($result, $db) = previewRunBoolean($baseContract);

previewAssertSame('identity runtime_mode', 'db_readonly', $result['runtime_mode']);
previewAssertSame('identity command', 'db_readonly_contract_normalization_preview', $result['command']);
previewAssertSame('identity target_key', 'fixture_boolean', $result['target_key']);
previewAssertSame('identity category_scope', 11900213, $result['category_scope']);
previewAssertSame('identity category_scope_ids_count', 3, $result['category_scope_ids_count']);
previewAssertSame('identity scope_distinct_products', 8, $result['scope_distinct_products']);
previewAssertSame('identity canonical_attribute_id', 47, $result['canonical_attribute_id']);
previewAssertSame('identity alias_attribute_ids', array(82), $result['alias_attribute_ids']);
previewAssertSame('identity normalizer_key', 'boolean_yes_no', $result['normalizer_key']);
previewAssertSame('identity target_table', 'oc_product_attribute', $result['target_table']);

previewAssertSame('totals total_attribute_rows', 10, $result['total_attribute_rows']);
previewAssertSame('totals canonical_rows', 4, $result['canonical_rows']);
previewAssertSame('totals alias_rows', 6, $result['alias_rows']);
previewAssertSame('totals canonical_distinct_products', 4, $result['canonical_distinct_products']);
previewAssertSame('totals alias_distinct_products', 6, $result['alias_distinct_products']);

previewAssertSame('both products', 2, $result['products_with_both_attributes']);
previewAssertSame('both groups', 2, $result['product_language_groups_with_both_attributes']);
previewAssertSame('both same', 1, $result['products_with_both_same_normalized_value']);
previewAssertSame('both conflict', 1, $result['products_with_both_conflict_or_review']);

previewAssertSame(
    'status counts',
    array(
        'normalized' => 7,
        'review_required' => 1,
        'unsupported' => 1,
        'invalid' => 1,
    ),
    $result['normalization_status_counts']
);
previewAssertSame(
    'reason counts',
    array(
        'empty_value' => 1,
        'mixed_boolean_values' => 1,
        'unsupported_boolean_value' => 1,
    ),
    $result['normalization_reason_counts']
);
previewAssertSame(
    'canonical value counts canonical',
    array('Да' => 3, 'Нет' => 1),
    $result['canonical_value_counts']['canonical']
);
previewAssertSame(
    'canonical value counts alias',
    array('Да' => 2, 'Нет' => 1),
    $result['canonical_value_counts']['alias']
);
previewAssertSame(
    'canonical value counts all',
    array('Да' => 5, 'Нет' => 2),
    $result['canonical_value_counts']['all']
);
previewAssertSame('canonical value key order', array('Да', 'Нет'), array_keys($result['canonical_value_counts']['all']));

previewAssertSame('breakdown count', 2, count($result['breakdown_by_attribute_id']));
$canonicalBreakdown = $result['breakdown_by_attribute_id'][0];
$aliasBreakdown = $result['breakdown_by_attribute_id'][1];
previewAssertSame('canonical breakdown attribute_id', 47, $canonicalBreakdown['attribute_id']);
previewAssertSame('canonical breakdown source_role', 'canonical', $canonicalBreakdown['source_role']);
previewAssertSame('canonical breakdown rows', 4, $canonicalBreakdown['rows']);
previewAssertSame('canonical breakdown distinct_products', 4, $canonicalBreakdown['distinct_products']);
previewAssertSame('canonical breakdown normalized', 4, $canonicalBreakdown['normalized']);
previewAssertSame('canonical breakdown review_required', 0, $canonicalBreakdown['review_required']);
previewAssertSame('canonical breakdown unsupported', 0, $canonicalBreakdown['unsupported']);
previewAssertSame('canonical breakdown invalid', 0, $canonicalBreakdown['invalid']);
previewAssertSame('canonical breakdown value counts', array('Да' => 3, 'Нет' => 1), $canonicalBreakdown['canonical_value_counts']);
previewAssertSame('alias breakdown attribute_id', 82, $aliasBreakdown['attribute_id']);
previewAssertSame('alias breakdown source_role', 'alias', $aliasBreakdown['source_role']);
previewAssertSame('alias breakdown rows', 6, $aliasBreakdown['rows']);
previewAssertSame('alias breakdown distinct_products', 6, $aliasBreakdown['distinct_products']);
previewAssertSame('alias breakdown normalized', 3, $aliasBreakdown['normalized']);
previewAssertSame('alias breakdown review_required', 1, $aliasBreakdown['review_required']);
previewAssertSame('alias breakdown unsupported', 1, $aliasBreakdown['unsupported']);
previewAssertSame('alias breakdown invalid', 1, $aliasBreakdown['invalid']);
previewAssertSame('alias breakdown value counts', array('Да' => 2, 'Нет' => 1), $aliasBreakdown['canonical_value_counts']);

previewAssertSame('sample count', 10, count($result['sample_rows']));
$firstSample = $result['sample_rows'][0];
previewAssertSame('sample first product', 101, $firstSample['product_id']);
previewAssertSame('sample first attribute', 47, $firstSample['attribute_id']);
previewAssertSame('sample first role', 'canonical', $firstSample['source_role']);
previewAssertSame('sample first raw', 'Да', $firstSample['raw_value']);
previewAssertSame('sample first status', 'normalized', $firstSample['status']);
previewAssertSame('sample first canonical', 'Да', $firstSample['canonical_value']);
previewAssertSame('sample first raw_equals_canonical', 1, $firstSample['raw_equals_canonical']);

$mixedSample = previewFindSampleByRawValue($result['sample_rows'], 'Да / Нет');
previewAssertTrue('sample mixed exists', is_array($mixedSample));
previewAssertSame('sample mixed status', 'review_required', $mixedSample['status']);
previewAssertSame('sample mixed reason', 'mixed_boolean_values', $mixedSample['reason']);
$unsupportedSample = previewFindSampleByRawValue($result['sample_rows'], 'maybe');
previewAssertTrue('sample unsupported exists', is_array($unsupportedSample));
previewAssertSame('sample unsupported status', 'unsupported', $unsupportedSample['status']);
previewAssertSame('sample unsupported reason', 'unsupported_boolean_value', $unsupportedSample['reason']);
$emptySample = previewFindSampleByRawValue($result['sample_rows'], '');
previewAssertTrue('sample empty exists', is_array($emptySample));
previewAssertSame('sample empty status', 'invalid', $emptySample['status']);
previewAssertSame('sample empty reason', 'empty_value', $emptySample['reason']);

$expectedSafety = array(
    'db_readonly' => 1,
    'select_only' => 1,
    'contract_driven' => 1,
    'normalization_preview_only' => 1,
    'pipeline_executed' => 0,
    'sql_generated' => 0,
    'apply_plan_created' => 0,
    'insert_executed' => 0,
    'update_executed' => 0,
    'delete_executed' => 0,
    'transaction_started' => 0,
    'product_data_changed' => 0,
    'contracts_changed' => 0,
    'production_touched' => 0,
    'cache_rebuild_performed' => 0,
);
previewAssertSame('safety markers', $expectedSafety, $result['safety_markers']);

previewAssertSame('request count', 3, count($db->requests));
foreach ($db->requests as $requestIndex => $request) {
    previewAssertTrue('request ' . $requestIndex . ' starts SELECT', preg_match('/^SELECT\b/i', trim($request['sql'])) === 1);
    previewAssertTrue('request ' . $requestIndex . ' no semicolon', strpos($request['sql'], ';') === false);
    previewAssertTrue('request ' . $requestIndex . ' no write keyword', preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $request['sql']) === 0);
}

$categoryRequest = $db->requests[0];
$productRequest = $db->requests[1];
$attributeRequest = $db->requests[2];
previewAssertTrue('category request order', strpos($categoryRequest['sql'], 'ORDER BY category_id ASC') !== false);
previewAssertSame('category request params', array(), $categoryRequest['params']);
previewAssertTrue('product request distinct', strpos($productRequest['sql'], 'SELECT DISTINCT product_id') !== false);
previewAssertTrue('product request placeholders', strpos($productRequest['sql'], ':category_0') !== false);
previewAssertSame('product request param values', array(11900213, 11900321, 11900400), previewParamValues($productRequest['params']));
previewAssertTrue('product request excludes outside category', !in_array(999, $productRequest['params'], true));
previewAssertTrue('attribute request EXISTS', strpos($attributeRequest['sql'], 'EXISTS') !== false);
previewAssertTrue('attribute request attribute placeholders', strpos($attributeRequest['sql'], ':attribute_0') !== false && strpos($attributeRequest['sql'], ':attribute_1') !== false);
previewAssertTrue('attribute request category placeholders', strpos($attributeRequest['sql'], ':category_0') !== false && strpos($attributeRequest['sql'], ':category_2') !== false);
previewAssertSame('attribute request param values', array(47, 82, 11900213, 11900321, 11900400), previewParamValues($attributeRequest['params']));
previewAssertTrue('attribute request excludes outside category', !in_array(999, $attributeRequest['params'], true));
previewAssertTrue('attribute request raw text selected', strpos($attributeRequest['sql'], 'pa.text') !== false);
previewAssertTrue('attribute request no trim', strpos($attributeRequest['sql'], 'TRIM(pa.text)') === false);
previewAssertTrue('attribute request no multiplying join', stripos($attributeRequest['sql'], 'INNER JOIN oc_product_to_category') === false);

list($resultTwo, $dbTwo) = previewRunBoolean(previewBooleanContract());
previewAssertSame('deterministic result', $result, $resultTwo);
previewAssertSame('deterministic request history', $db->requests, $dbTwo->requests);
previewAssertSame('base contract unchanged', $baseContractBefore, $baseContract);
previewAssertSame('no evidence checks', array(), $result['evidence_checks']);
previewAssertSame('no evidence match', 1, $result['evidence_match']);

$evidenceContract = previewBooleanContract();
$evidenceContract['evidence'] = array(
    'scope_distinct_products' => 8,
    'canonical_attribute_47' => array(
        'distinct_products' => 4,
        'values' => array('Да' => 3, 'Нет' => 1),
    ),
    'alias_attribute_82' => array(
        'distinct_products' => 6,
        'values' => array('Да' => 2, 'Нет' => 1),
    ),
    'products_with_both_attributes' => 2,
    'observed_alias_rows_before_migration' => 6,
);
$evidenceContractBefore = $evidenceContract;
list($evidenceResult, $unusedEvidenceDb) = previewRunBoolean($evidenceContract);
previewAssertSame('evidence match', 1, $evidenceResult['evidence_match']);
previewAssertSame('evidence check count', 9, count($evidenceResult['evidence_checks']));
$expectedEvidenceNames = array(
    'scope_distinct_products',
    'canonical_attribute_47_distinct_products',
    'canonical_attribute_47_value_Да',
    'canonical_attribute_47_value_Нет',
    'alias_attribute_82_distinct_products',
    'alias_attribute_82_value_Да',
    'alias_attribute_82_value_Нет',
    'products_with_both_attributes',
    'observed_alias_rows_before_migration',
);
previewAssertSame('evidence check names', $expectedEvidenceNames, array_column($evidenceResult['evidence_checks'], 'name'));
foreach ($evidenceResult['evidence_checks'] as $check) {
    previewAssertSame('evidence check ' . $check['name'], 1, $check['match']);
}
previewAssertSame('evidence contract unchanged', $evidenceContractBefore, $evidenceContract);

$mismatchContract = $evidenceContract;
$mismatchContract['evidence']['scope_distinct_products'] = 999;
$mismatchContractBefore = $mismatchContract;
list($mismatchResult, $unusedMismatchDb) = previewRunBoolean($mismatchContract);
previewAssertSame('evidence mismatch flag', 0, $mismatchResult['evidence_match']);
$scopeMismatch = previewFindEvidenceCheck($mismatchResult['evidence_checks'], 'scope_distinct_products');
previewAssertTrue('scope mismatch exists', is_array($scopeMismatch));
previewAssertSame('scope mismatch expected', 999, $scopeMismatch['expected']);
previewAssertSame('scope mismatch actual', 8, $scopeMismatch['actual']);
previewAssertSame('scope mismatch match', 0, $scopeMismatch['match']);
previewAssertSame('mismatch contract unchanged', $mismatchContractBefore, $mismatchContract);

$forbiddenResultKeys = array('sql', 'queries', 'password', 'dsn', 'runtime_config', 'apply_plan');
previewAssertTrue('result sensitive key scan', !previewContainsRecursiveKey($result, $forbiddenResultKeys));

$domainDb = new PreviewNormalizationFakeDb(
    array(array('category_id' => 11900213, 'parent_id' => 0)),
    array(array('product_id' => 301)),
    array(array('product_id' => 301, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'anything'))
);
$domainPreview = new DbReadOnlyContractNormalizationPreview(
    $domainDb,
    'oc_',
    previewBooleanContract(),
    new PreviewDomainViolationNormalizer()
);
$domainResult = $domainPreview->generate('db_readonly');
previewAssertSame('domain status normalized', 0, $domainResult['normalization_status_counts']['normalized']);
previewAssertSame('domain status review_required', 1, $domainResult['normalization_status_counts']['review_required']);
previewAssertSame('domain sample canonical_value', 'Maybe', $domainResult['sample_rows'][0]['canonical_value']);
previewAssertSame('domain sample reason', 'canonical_value_outside_contract_domain', $domainResult['sample_rows'][0]['reason']);

$legacyContract = array(
    'target_key' => 'fixture_meters',
    'target_meaning' => 'fixture meters',
    'category_scope_id' => 11900213,
    'scope_mode' => 'hierarchical_category_path_exists',
    'canonical_attribute_id' => 12,
    'alias_attribute_ids' => array(81),
    'normalizer_key' => 'simple_meters',
    'read_only_ready' => true,
    'apply_ready' => false,
    'allowed_table' => 'oc_product_attribute',
);
$legacyDb = new PreviewNormalizationFakeDb(
    array(array('category_id' => 11900213, 'parent_id' => 0)),
    array(array('product_id' => 401), array('product_id' => 402)),
    array(
        array('product_id' => 401, 'attribute_id' => 12, 'language_id' => 1, 'text' => '50 м'),
        array('product_id' => 402, 'attribute_id' => 81, 'language_id' => 1, 'text' => 'до 40 м'),
    )
);
$legacyPreview = new DbReadOnlyContractNormalizationPreview(
    $legacyDb,
    'oc_',
    $legacyContract,
    new SimpleMetersNormalizer()
);
$legacyResult = $legacyPreview->generate('db_readonly');
previewAssertSame('legacy normalized count', 1, $legacyResult['normalization_status_counts']['normalized']);
previewAssertSame('legacy unsupported count', 1, $legacyResult['normalization_status_counts']['unsupported']);
previewAssertSame('legacy canonical value', '50', $legacyResult['sample_rows'][0]['canonical_value']);
previewAssertSame('legacy unsupported reason', 'unresolved_or_excluded_value', $legacyResult['sample_rows'][1]['reason']);

$errorCategoryRows = array(array('category_id' => 11900213, 'parent_id' => 0));
$errorProductRows = array(array('product_id' => 501));
$errorAttributeRows = array(array('product_id' => 501, 'attribute_id' => 47, 'language_id' => 1, 'text' => 'Да'));
previewAssertExceptionMessage(
    'scalar normalizer result error',
    'normalizer_result_schema_not_supported',
    function () use ($errorCategoryRows, $errorProductRows, $errorAttributeRows) {
        $preview = new DbReadOnlyContractNormalizationPreview(
            new PreviewNormalizationFakeDb($errorCategoryRows, $errorProductRows, $errorAttributeRows),
            'oc_',
            previewBooleanContract(),
            new PreviewScalarResultNormalizer()
        );
        $preview->generate('db_readonly');
    }
);
previewAssertExceptionMessage(
    'mystery status error',
    'normalizer_result_status_not_supported',
    function () use ($errorCategoryRows, $errorProductRows, $errorAttributeRows) {
        $preview = new DbReadOnlyContractNormalizationPreview(
            new PreviewNormalizationFakeDb($errorCategoryRows, $errorProductRows, $errorAttributeRows),
            'oc_',
            previewBooleanContract(),
            new PreviewMysteryStatusNormalizer()
        );
        $preview->generate('db_readonly');
    }
);
previewAssertExceptionMessage(
    'normalizer constructor guard',
    'normalizer_invalid',
    function () use ($errorCategoryRows, $errorProductRows, $errorAttributeRows) {
        new DbReadOnlyContractNormalizationPreview(
            new PreviewNormalizationFakeDb($errorCategoryRows, $errorProductRows, $errorAttributeRows),
            'oc_',
            previewBooleanContract(),
            new PreviewNormalizerWithoutNormalizeMethod()
        );
    }
);

$realContract = require dirname(__DIR__) . '/config/attribute-contracts/dry_run_protection_11900213.php';
previewAssertSame('real contract target_key', 'dry_run_protection', $realContract['target_key']);
previewAssertSame('real contract canonical_attribute_id', 47, $realContract['canonical_attribute_id']);
previewAssertSame('real contract alias_attribute_ids', array(82), $realContract['alias_attribute_ids']);
previewAssertSame('real contract normalizer_key', 'boolean_yes_no', $realContract['normalizer_key']);
previewAssertSame('real contract read_only_ready', true, $realContract['read_only_ready']);
previewAssertSame('real contract apply_ready', false, $realContract['apply_ready']);

echo "db_readonly_contract_normalization_preview_static_checks: ok\n";
