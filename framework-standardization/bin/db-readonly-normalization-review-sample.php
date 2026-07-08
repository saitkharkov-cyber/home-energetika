<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Proposals\DbReadOnlyNormalizationProposals;
use FrameworkStandardization\Review\DbReadOnlyNormalizationReviewChain;
use FrameworkStandardization\Review\DbReadOnlyNormalizationReviewSample;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-normalization-review-sample.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-normalization-review-sample.php path/to/runtime.php --category-id=ID --attribute-ids=1,2 --canonical-attribute-id=ID --canonical-unit=m [--limit=50] [--format=plain|markdown]');
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
    $proposalGenerator = new DbReadOnlyNormalizationProposals($db, $runtimeConfig->getDbPrefix(), 1);
    $reviewChain = new DbReadOnlyNormalizationReviewChain($proposalGenerator);
    $reviewSample = new DbReadOnlyNormalizationReviewSample($reviewChain);
    $result = $reviewSample->generate(
        $options['category_id'],
        $options['attribute_ids'],
        $options['canonical_attribute_id'],
        $options['canonical_unit'],
        $options['limit']
    );

    if ($options['format'] === 'markdown') {
        printReviewSampleMarkdown($result, $options['format']);
    } else {
        printReviewSamplePlain($result, $options['format']);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'normalization_review_sample_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $categoryId = null;
    $attributeIds = null;
    $canonicalAttributeId = null;
    $canonicalUnit = null;
    $limit = 50;
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

        if (strpos($arg, '--limit=') === 0) {
            $limit = parsePositiveInt(substr($arg, strlen('--limit=')), 'invalid_limit');
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
        'limit' => $limit,
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

function printReviewSamplePlain(array $result, $format)
{
    echo "runtime_mode: db_readonly\n";
    echo "command: normalization_review_sample\n";
    echo 'category_scope: ' . $result['category_id'] . "\n";
    echo 'attribute_ids: ' . implode(',', $result['attribute_ids']) . "\n";
    echo 'canonical_attribute_id: ' . $result['canonical_attribute_id'] . "\n";
    echo 'canonical_unit: ' . $result['canonical_unit'] . "\n";
    echo 'limit: ' . $result['limit'] . "\n";
    echo 'format: ' . $format . "\n";
    echo 'total_pending_review_count: ' . $result['total_pending_review_count'] . "\n";
    echo 'pending_review_sample_count: ' . $result['pending_review_sample_count'] . "\n";
    echo 'total_unresolved_count: ' . $result['total_unresolved_count'] . "\n";
    echo 'unresolved_sample_count: ' . $result['unresolved_sample_count'] . "\n";
    echo 'skipped_count: ' . $result['skipped_count'] . "\n";
    echo "\n";
    echo "pending_review_sample:\n";

    foreach ($result['pending_review_sample'] as $row) {
        echo '- review_id: ' . $row['review_id'] . "\n";
        echo '  product_id: ' . $row['product_id'] . "\n";
        echo '  attribute_id: ' . $row['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($row['attribute_name']) . "\n";
        echo '  raw_value: ' . valueOrNone($row['raw_value']) . "\n";
        echo '  proposed_normalized_value: ' . $row['proposed_normalized_value'] . "\n";
        echo '  canonical_unit: ' . $row['canonical_unit'] . "\n";
        echo '  review_status: ' . $row['review_status'] . "\n";
        echo '  reason: ' . $row['reason'] . "\n";
    }

    echo "\n";
    echo "unresolved_sample:\n";

    foreach ($result['unresolved_sample'] as $row) {
        echo '- review_id: ' . $row['review_id'] . "\n";
        echo '  product_id: ' . $row['product_id'] . "\n";
        echo '  attribute_id: ' . $row['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($row['attribute_name']) . "\n";
        echo '  raw_value: ' . valueOrNone($row['raw_value']) . "\n";
        echo '  review_status: ' . $row['review_status'] . "\n";
        echo '  reason: ' . $row['reason'] . "\n";
    }

    printSafetyMarkers($result);
}

function printReviewSampleMarkdown(array $result, $format)
{
    echo "# DB-readonly normalization review sample\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: normalization_review_sample\n";
    echo '- category_scope: ' . markdownCell($result['category_id']) . "\n";
    echo '- attribute_ids: ' . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo '- canonical_attribute_id: ' . markdownCell($result['canonical_attribute_id']) . "\n";
    echo '- canonical_unit: ' . markdownCell($result['canonical_unit']) . "\n";
    echo '- limit: ' . markdownCell($result['limit']) . "\n";
    echo '- format: ' . markdownCell($format) . "\n";
    echo "\n";
    echo "## Pending review sample\n";
    echo "\n";
    echo "| review_id | product_id | attribute_id | attribute_name | raw_value | proposed_normalized_value | canonical_unit | review_status | reason |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($result['pending_review_sample'] as $row) {
        echo '| ' . markdownCell($row['review_id']);
        echo ' | ' . markdownCell($row['product_id']);
        echo ' | ' . markdownCell($row['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($row['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($row['raw_value']));
        echo ' | ' . markdownCell($row['proposed_normalized_value']);
        echo ' | ' . markdownCell($row['canonical_unit']);
        echo ' | ' . markdownCell($row['review_status']);
        echo ' | ' . markdownCell($row['reason']) . " |\n";
    }

    echo "\n";
    echo "## Unresolved sample\n";
    echo "\n";
    echo "| review_id | product_id | attribute_id | attribute_name | raw_value | review_status | reason |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($result['unresolved_sample'] as $row) {
        echo '| ' . markdownCell($row['review_id']);
        echo ' | ' . markdownCell($row['product_id']);
        echo ' | ' . markdownCell($row['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($row['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($row['raw_value']));
        echo ' | ' . markdownCell($row['review_status']);
        echo ' | ' . markdownCell($row['reason']) . " |\n";
    }

    echo "\n";
    echo "## Summary\n";
    echo "\n";
    echo '- total_pending_review_count: ' . $result['total_pending_review_count'] . "\n";
    echo '- pending_review_sample_count: ' . $result['pending_review_sample_count'] . "\n";
    echo '- total_unresolved_count: ' . $result['total_unresolved_count'] . "\n";
    echo '- unresolved_sample_count: ' . $result['unresolved_sample_count'] . "\n";
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
    echo 'review_sample_generated: ' . (int) $result['review_sample_generated'] . "\n";
    echo 'review_sample_persisted: ' . (int) $result['review_sample_persisted'] . "\n";
    echo 'approved_auto_assigned: ' . (int) $result['approved_auto_assigned'] . "\n";
    echo 'review_chain_persisted: ' . (int) $result['review_chain_persisted'] . "\n";
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
