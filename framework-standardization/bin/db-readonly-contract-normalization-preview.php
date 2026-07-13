<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Contract\AttributeContractLoader;
use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Preview\DbReadOnlyContractNormalizationPreview;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-contract-normalization-preview.php must be executed from CLI.\n");
    exit(1);
}

try {
    if ($argc !== 3) {
        throw new \InvalidArgumentException(
            'usage: php bin/db-readonly-contract-normalization-preview.php path/to/runtime.php path/to/contract.php'
        );
    }

    $runtimeFile = $argv[1];
    $contractFile = $argv[2];

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    if (!is_file($contractFile)) {
        throw new \InvalidArgumentException('contract_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    $contractLoader = new AttributeContractLoader();
    $contract = $contractLoader->load($contractFile);

    $contractLoader->assertRuntimeAllowed($contract, $runtimeConfig);
    assertPreviewAllowed($runtimeConfig, $contract);

    $registry = NormalizerRegistry::createDefault();
    $normalizerKey = (string) $contract['normalizer_key'];

    if (!$registry->has($normalizerKey)) {
        throw new \RuntimeException('normalizer_not_registered');
    }

    $normalizer = $registry->get($normalizerKey);
    $db = PdoReadOnlyDbConnection::fromRuntimeConfig($runtimeConfig);

    $preview = new DbReadOnlyContractNormalizationPreview(
        $db,
        $runtimeConfig->getDbPrefix(),
        $contract,
        $normalizer
    );

    $result = $preview->generate($runtimeConfig->getRuntimeMode());

    printResult($result);
    exit(0);
} catch (\Exception $e) {
    fwrite(
        STDERR,
        'db_readonly_contract_normalization_preview_error: ' . $e->getMessage() . "\n"
    );
    exit(1);
}

function assertPreviewAllowed(OpenCartRuntimeConfig $runtimeConfig, array $contract)
{
    if ($runtimeConfig->getRuntimeMode() !== 'db_readonly') {
        throw new \RuntimeException('runtime_mode_not_db_readonly');
    }

    if (!array_key_exists('read_only_ready', $contract) || $contract['read_only_ready'] !== true) {
        throw new \RuntimeException('contract_not_read_only_ready');
    }

    if (array_key_exists('apply_ready', $contract) && $contract['apply_ready'] === true) {
        throw new \RuntimeException('contract_apply_ready');
    }

    if (!isset($contract['allowed_table']) || $contract['allowed_table'] !== 'oc_product_attribute') {
        throw new \RuntimeException('contract_allowed_table_not_supported');
    }

    if (!isset($contract['normalizer_key']) || trim((string) $contract['normalizer_key']) === '') {
        throw new \RuntimeException('contract_normalizer_key_required');
    }
}

function printResult(array $result)
{
    printField('runtime_mode', $result['runtime_mode']);
    printField('command', $result['command']);
    printField('target_key', $result['target_key']);
    printField('target_meaning', $result['target_meaning']);
    printField('category_scope', $result['category_scope']);
    printField('category_scope_ids_count', $result['category_scope_ids_count']);
    printField('scope_distinct_products', $result['scope_distinct_products']);
    printField('canonical_attribute_id', $result['canonical_attribute_id']);
    printField('alias_attribute_ids', implode(',', $result['alias_attribute_ids']));
    printField('normalizer_key', $result['normalizer_key']);
    printField('target_table', $result['target_table']);

    echo "\nrow_counts:\n";
    printListField('total_attribute_rows', $result['total_attribute_rows']);
    printListField('canonical_rows', $result['canonical_rows']);
    printListField('alias_rows', $result['alias_rows']);
    printListField('canonical_distinct_products', $result['canonical_distinct_products']);
    printListField('alias_distinct_products', $result['alias_distinct_products']);

    echo "\nboth_attribute_analysis:\n";
    printListField('products_with_both_attributes', $result['products_with_both_attributes']);
    printListField(
        'product_language_groups_with_both_attributes',
        $result['product_language_groups_with_both_attributes']
    );
    printListField(
        'products_with_both_same_normalized_value',
        $result['products_with_both_same_normalized_value']
    );
    printListField(
        'products_with_both_conflict_or_review',
        $result['products_with_both_conflict_or_review']
    );

    echo "\nnormalization_status_counts:\n";
    foreach (array('normalized', 'review_required', 'unsupported', 'invalid') as $status) {
        $count = isset($result['normalization_status_counts'][$status])
            ? $result['normalization_status_counts'][$status]
            : 0;
        printListField($status, $count);
    }

    echo "\nnormalization_reason_counts:\n";
    printMap($result['normalization_reason_counts']);

    echo "\ncanonical_value_counts:\n";
    foreach (array('canonical', 'alias', 'all') as $sourceRole) {
        echo $sourceRole . ":\n";
        $counts = isset($result['canonical_value_counts'][$sourceRole])
            ? $result['canonical_value_counts'][$sourceRole]
            : array();
        printMap($counts, '  ');
    }

    echo "\nbreakdown_by_attribute_id:\n";
    if (count($result['breakdown_by_attribute_id']) === 0) {
        echo "- none\n";
    } else {
        foreach ($result['breakdown_by_attribute_id'] as $item) {
            echo "- attribute_id: " . formatValue($item['attribute_id']) . "\n";
            printIndentedField('source_role', $item['source_role']);
            printIndentedField('rows', $item['rows']);
            printIndentedField('distinct_products', $item['distinct_products']);
            printIndentedField('normalized', $item['normalized']);
            printIndentedField('review_required', $item['review_required']);
            printIndentedField('unsupported', $item['unsupported']);
            printIndentedField('invalid', $item['invalid']);
            echo "  canonical_value_counts:\n";
            printMap($item['canonical_value_counts'], '    ');
        }
    }

    echo "\nevidence_checks:\n";
    if (count($result['evidence_checks']) === 0) {
        echo "- none\n";
    } else {
        foreach ($result['evidence_checks'] as $check) {
            echo "- name: " . formatValue($check['name']) . "\n";
            printIndentedField('expected', $check['expected']);
            printIndentedField('actual', $check['actual']);
            printIndentedField('match', $check['match']);
        }
    }

    printField('evidence_match', $result['evidence_match']);

    echo "\nsample_rows:\n";
    if (count($result['sample_rows']) === 0) {
        echo "- none\n";
    } else {
        foreach ($result['sample_rows'] as $row) {
            echo "- product_id: " . formatValue($row['product_id']) . "\n";
            printIndentedField('attribute_id', $row['attribute_id']);
            printIndentedField('source_role', $row['source_role']);
            printIndentedField('language_id', $row['language_id']);
            printIndentedField('raw_value', $row['raw_value']);
            printIndentedField('status', $row['status']);
            printIndentedField('canonical_value', $row['canonical_value']);
            printIndentedField('reason', $row['reason']);
            printIndentedField('raw_equals_canonical', $row['raw_equals_canonical']);
        }
    }

    echo "\nsafety_markers:\n";
    printMap($result['safety_markers']);
}

function printField($name, $value)
{
    echo $name . ': ' . formatValue($value) . "\n";
}

function printListField($name, $value)
{
    echo '- ' . $name . ': ' . formatValue($value) . "\n";
}

function printIndentedField($name, $value)
{
    echo '  ' . $name . ': ' . formatValue($value) . "\n";
}

function printMap(array $values, $indent = '')
{
    if (count($values) === 0) {
        echo $indent . "- none\n";
        return;
    }

    foreach ($values as $name => $value) {
        echo $indent . '- ' . formatValue($name) . ': ' . formatValue($value) . "\n";
    }
}

function formatValue($value)
{
    if ($value === null || $value === '') {
        return 'none';
    }

    if ($value === true) {
        return 'true';
    }

    if ($value === false) {
        return 'false';
    }

    if (is_array($value)) {
        if (count($value) === 0) {
            return 'none';
        }

        $parts = array();

        foreach ($value as $item) {
            $parts[] = formatValue($item);
        }

        return implode(',', $parts);
    }

    $text = (string) $value;
    $text = str_replace(
        array("\\", "\r\n", "\r", "\n", "\t"),
        array("\\\\", "\\n", "\\n", "\\n", "\\t"),
        $text
    );

    return $text === '' ? 'none' : $text;
}
