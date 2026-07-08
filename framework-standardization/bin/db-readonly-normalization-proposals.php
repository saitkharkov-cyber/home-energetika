<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Proposals\DbReadOnlyNormalizationProposals;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-normalization-proposals.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-normalization-proposals.php path/to/runtime.php --category-id=ID --attribute-ids=1,2 --canonical-attribute-id=ID --canonical-unit=m [--format=plain|markdown]');
    }

    $runtimeFile = $argv[1];
    $options = parseCliOptions($argv);

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    assertLocalRuntime($runtimeConfig);

    $db = new PdoReadOnlyDbConnection(createPdo($runtimeConfig));
    $generator = new DbReadOnlyNormalizationProposals($db, $runtimeConfig->getDbPrefix(), 1);
    $result = $generator->generate(
        $options['category_id'],
        $options['attribute_ids'],
        $options['canonical_attribute_id'],
        $options['canonical_unit']
    );

    if ($options['format'] === 'markdown') {
        printProposalsMarkdown($result, $options['format']);
    } else {
        printProposalsPlain($result, $options['format']);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'normalization_proposals_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $categoryId = null;
    $attributeIds = null;
    $canonicalAttributeId = null;
    $canonicalUnit = null;
    $format = 'plain';

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '') {
            continue;
        }

        if (strpos($arg, '--category-id=') === 0) {
            $categoryId = parsePositiveInt(substr($arg, strlen('--category-id=')), 'invalid_category_id');
            continue;
        }

        if (strpos($arg, '--attribute-ids=') === 0) {
            $attributeIds = parseAttributeIds(substr($arg, strlen('--attribute-ids=')));
            continue;
        }

        if (strpos($arg, '--canonical-attribute-id=') === 0) {
            $canonicalAttributeId = parsePositiveInt(substr($arg, strlen('--canonical-attribute-id=')), 'invalid_canonical_attribute_id');
            continue;
        }

        if (strpos($arg, '--canonical-unit=') === 0) {
            $canonicalUnit = substr($arg, strlen('--canonical-unit='));

            if ($canonicalUnit !== 'm') {
                throw new \InvalidArgumentException('unsupported_canonical_unit');
            }

            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $formatValue = substr($arg, strlen('--format='));

            if ($formatValue !== 'plain' && $formatValue !== 'markdown') {
                throw new \InvalidArgumentException('unsupported_format');
            }

            $format = $formatValue;
            continue;
        }

        throw new \InvalidArgumentException('unexpected_cli_argument');
    }

    if ($categoryId === null) {
        throw new \InvalidArgumentException('invalid_category_id');
    }

    if ($attributeIds === null) {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    if ($canonicalAttributeId === null) {
        throw new \InvalidArgumentException('invalid_canonical_attribute_id');
    }

    if (!in_array($canonicalAttributeId, $attributeIds, true)) {
        throw new \InvalidArgumentException('canonical_attribute_id_not_in_attribute_ids');
    }

    if ($canonicalUnit === null) {
        throw new \InvalidArgumentException('unsupported_canonical_unit');
    }

    return array(
        'category_id' => $categoryId,
        'attribute_ids' => $attributeIds,
        'canonical_attribute_id' => $canonicalAttributeId,
        'canonical_unit' => $canonicalUnit,
        'format' => $format,
    );
}

function parsePositiveInt($value, $error)
{
    if (!preg_match('/^[1-9][0-9]*$/', $value)) {
        throw new \InvalidArgumentException($error);
    }

    return (int) $value;
}

function parseAttributeIds($value)
{
    if ($value === '') {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    $parts = explode(',', $value);
    $attributeIds = array();

    foreach ($parts as $part) {
        $part = trim($part);

        if (!preg_match('/^[1-9][0-9]*$/', $part)) {
            throw new \InvalidArgumentException('invalid_attribute_ids');
        }

        $id = (int) $part;

        if (!in_array($id, $attributeIds, true)) {
            $attributeIds[] = $id;
        }
    }

    if (count($attributeIds) === 0) {
        throw new \InvalidArgumentException('attribute_ids_required');
    }

    return $attributeIds;
}

function assertLocalRuntime(OpenCartRuntimeConfig $runtimeConfig)
{
    $database = $runtimeConfig->getDatabase();

    if ($runtimeConfig->getRuntimeMode() !== 'db_readonly') {
        throw new \InvalidArgumentException('runtime_mode_not_db_readonly');
    }

    if (!isset($database['driver']) || $database['driver'] !== 'pdo_mysql') {
        throw new \InvalidArgumentException('runtime_driver_not_supported');
    }

    if (!isset($database['host']) || $database['host'] !== '127.0.1.19') {
        throw new \InvalidArgumentException('runtime_host_not_allowed');
    }

    if (!isset($database['dbname']) || $database['dbname'] !== 'he_framework_local_dump') {
        throw new \InvalidArgumentException('runtime_dbname_not_allowed');
    }

    if ($runtimeConfig->getDbPrefix() !== 'oc_') {
        throw new \InvalidArgumentException('runtime_db_prefix_not_allowed');
    }
}

function createPdo(OpenCartRuntimeConfig $runtimeConfig)
{
    $database = $runtimeConfig->getDatabase();
    $dsn = 'mysql:host=' . $database['host'];
    $dsn .= ';port=' . $database['port'];
    $dsn .= ';dbname=' . $database['dbname'];
    $dsn .= ';charset=' . $database['charset'];

    $pdo = new \PDO($dsn, $database['username'], $database['password']);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function printProposalsPlain(array $result, $format)
{
    echo "runtime_mode: db_readonly\n";
    echo "command: normalization_proposals\n";
    echo 'category_scope: ' . $result['category_id'] . "\n";
    echo 'attribute_ids: ' . implode(',', $result['attribute_ids']) . "\n";
    echo 'canonical_attribute_id: ' . $result['canonical_attribute_id'] . "\n";
    echo 'canonical_unit: ' . $result['canonical_unit'] . "\n";
    echo 'format: ' . $format . "\n";
    echo 'proposals_count: ' . count($result['proposals']) . "\n";
    echo 'unresolved_count: ' . count($result['unresolved']) . "\n";
    echo 'skipped_count: ' . $result['skipped_count'] . "\n";
    echo "\n";
    echo "proposals:\n";

    foreach ($result['proposals'] as $proposal) {
        echo '- product_id: ' . $proposal['product_id'] . "\n";
        echo '  attribute_id: ' . $proposal['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($proposal['attribute_name']) . "\n";
        echo '  raw_value: ' . valueOrNone($proposal['raw_value']) . "\n";
        echo '  normalized_value: ' . $proposal['normalized_value'] . "\n";
        echo '  canonical_unit: ' . $proposal['canonical_unit'] . "\n";
        echo '  action: ' . $proposal['action'] . "\n";
        echo '  reason: ' . $proposal['reason'] . "\n";
    }

    echo "\n";
    echo "unresolved:\n";

    foreach ($result['unresolved'] as $unresolved) {
        echo '- product_id: ' . $unresolved['product_id'] . "\n";
        echo '  attribute_id: ' . $unresolved['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($unresolved['attribute_name']) . "\n";
        echo '  raw_value: ' . valueOrNone($unresolved['raw_value']) . "\n";
        echo '  reason: ' . $unresolved['reason'] . "\n";
    }

    printSafetyMarkers($result);
}

function printProposalsMarkdown(array $result, $format)
{
    echo "# DB-readonly normalization proposals\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: normalization_proposals\n";
    echo '- category_scope: ' . markdownCell($result['category_id']) . "\n";
    echo '- attribute_ids: ' . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo '- canonical_attribute_id: ' . markdownCell($result['canonical_attribute_id']) . "\n";
    echo '- canonical_unit: ' . markdownCell($result['canonical_unit']) . "\n";
    echo '- format: ' . markdownCell($format) . "\n";
    echo "\n";
    echo "## Proposals\n";
    echo "\n";
    echo "| product_id | attribute_id | attribute_name | raw_value | normalized_value | canonical_unit | action | reason |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($result['proposals'] as $proposal) {
        echo '| ' . markdownCell($proposal['product_id']);
        echo ' | ' . markdownCell($proposal['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($proposal['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($proposal['raw_value']));
        echo ' | ' . markdownCell($proposal['normalized_value']);
        echo ' | ' . markdownCell($proposal['canonical_unit']);
        echo ' | ' . markdownCell($proposal['action']);
        echo ' | ' . markdownCell($proposal['reason']) . " |\n";
    }

    echo "\n";
    echo "## Unresolved\n";
    echo "\n";
    echo "| product_id | attribute_id | attribute_name | raw_value | reason |\n";
    echo "| --- | --- | --- | --- | --- |\n";

    foreach ($result['unresolved'] as $unresolved) {
        echo '| ' . markdownCell($unresolved['product_id']);
        echo ' | ' . markdownCell($unresolved['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($unresolved['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($unresolved['raw_value']));
        echo ' | ' . markdownCell($unresolved['reason']) . " |\n";
    }

    echo "\n";
    echo "## Summary\n";
    echo "\n";
    echo '- proposals_count: ' . count($result['proposals']) . "\n";
    echo '- unresolved_count: ' . count($result['unresolved']) . "\n";
    echo '- skipped_count: ' . $result['skipped_count'] . "\n";
    echo "\n";
    echo "## Safety markers\n";
    echo "\n";
    echo "```text\n";
    printSafetyMarkers($result);
    echo "```\n";
}

function printSafetyMarkers(array $result)
{
    echo 'normalization_proposals_generated: ' . (int) $result['normalization_proposals_generated'] . "\n";
    echo 'unresolved_values_reported: ' . (int) $result['unresolved_values_reported'] . "\n";
    echo "sql_generated: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "apply_plan_created: 0\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
    echo "production_ready: 0\n";
}

function valueOrNone($value)
{
    if ((string) $value === '') {
        return 'none';
    }

    return (string) $value;
}

function markdownCell($value)
{
    $value = valueOrNone($value);
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return $value;
}
