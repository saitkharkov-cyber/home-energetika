<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Inventory\DbReadOnlyRawValuesInventory;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-raw-values-inventory.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-raw-values-inventory.php path/to/runtime.php --category-id=ID --attribute-ids=1,2 [--format=plain|markdown]');
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
    $inventory = new DbReadOnlyRawValuesInventory($db, $runtimeConfig->getDbPrefix(), 1);
    $result = $inventory->inventory($options['category_id'], $options['attribute_ids']);

    if ($options['format'] === 'markdown') {
        printInventoryMarkdown($result);
    } else {
        printInventoryPlain($result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'raw_values_inventory_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $categoryId = null;
    $attributeIds = null;
    $format = 'plain';

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '') {
            continue;
        }

        if (strpos($arg, '--category-id=') === 0) {
            $value = substr($arg, strlen('--category-id='));

            if (!preg_match('/^[1-9][0-9]*$/', $value)) {
                throw new \InvalidArgumentException('invalid_category_id');
            }

            $categoryId = (int) $value;
            continue;
        }

        if (strpos($arg, '--attribute-ids=') === 0) {
            $value = substr($arg, strlen('--attribute-ids='));

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

            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $value = substr($arg, strlen('--format='));

            if ($value !== 'plain' && $value !== 'markdown') {
                throw new \InvalidArgumentException('unsupported_format');
            }

            $format = $value;
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

    return array(
        'category_id' => $categoryId,
        'attribute_ids' => $attributeIds,
        'format' => $format,
    );
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

function printInventoryPlain(array $result)
{
    echo "runtime_mode: db_readonly\n";
    echo "command: raw_values_inventory\n";
    echo 'category_scope: ' . $result['category_id'] . "\n";
    echo 'attribute_ids: ' . implode(',', $result['attribute_ids']) . "\n";
    echo "\n";
    echo "attributes:\n";

    foreach ($result['attributes'] as $attribute) {
        echo '- attribute_id: ' . $attribute['attribute_id'] . "\n";
        echo '  attribute_name: ' . valueOrNone($attribute['attribute_name']) . "\n";
        echo '  group: ' . valueOrNone($attribute['attribute_group_name']) . "\n";
        echo '  products_with_attribute_count: ' . $attribute['products_with_attribute_count'] . "\n";
        echo '  distinct_raw_values_count: ' . $attribute['distinct_raw_values_count'] . "\n";
        echo "  raw_values:\n";

        foreach ($attribute['raw_values'] as $rawValue) {
            echo '  - raw_value: ' . valueOrNone($rawValue['raw_value']) . "\n";
            echo '    count: ' . $rawValue['count'] . "\n";
            echo '    sample_product_ids: ' . listOrNone($rawValue['sample_product_ids']) . "\n";
            echo '    sample_product_names: ' . listOrNone($rawValue['sample_product_names']) . "\n";
            echo '    warnings: ' . listOrNone($rawValue['warnings']) . "\n";
        }
    }

    printSafetyMarkers($result['raw_values_inventory_completed']);
}

function printInventoryMarkdown(array $result)
{
    echo "# DB-readonly raw values inventory\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: raw_values_inventory\n";
    echo '- category_scope: ' . markdownCell($result['category_id']) . "\n";
    echo '- attribute_ids: ' . markdownCell(implode(',', $result['attribute_ids'])) . "\n";
    echo "\n";
    echo "## Attributes summary\n";
    echo "\n";
    echo "| attribute_id | attribute_name | group | products_with_attribute_count | distinct_raw_values_count |\n";
    echo "| --- | --- | --- | --- | --- |\n";

    foreach ($result['attributes'] as $attribute) {
        echo '| ' . markdownCell($attribute['attribute_id']);
        echo ' | ' . markdownCell(valueOrNone($attribute['attribute_name']));
        echo ' | ' . markdownCell(valueOrNone($attribute['attribute_group_name']));
        echo ' | ' . markdownCell($attribute['products_with_attribute_count']);
        echo ' | ' . markdownCell($attribute['distinct_raw_values_count']) . " |\n";
    }

    foreach ($result['attributes'] as $attribute) {
        echo "\n";
        echo '## Raw values for attribute ' . markdownCell($attribute['attribute_id']) . ' - ' . markdownCell(valueOrNone($attribute['attribute_name'])) . "\n";
        echo "\n";
        echo "| raw_value | count | sample_product_ids | sample_product_names | warnings |\n";
        echo "| --- | --- | --- | --- | --- |\n";

        foreach ($attribute['raw_values'] as $rawValue) {
            echo '| ' . markdownCell(valueOrNone($rawValue['raw_value']));
            echo ' | ' . markdownCell($rawValue['count']);
            echo ' | ' . markdownCell(listOrNone($rawValue['sample_product_ids']));
            echo ' | ' . markdownCell(listOrNone($rawValue['sample_product_names']));
            echo ' | ' . markdownCell(listOrNone($rawValue['warnings'])) . " |\n";
        }
    }

    echo "\n";
    echo "## Safety markers\n";
    echo "\n";
    echo "```text\n";
    printSafetyMarkers($result['raw_values_inventory_completed']);
    echo "```\n";
}

function printSafetyMarkers($rawValuesInventoryCompleted)
{
    echo 'raw_values_inventory_completed: ' . (int) $rawValuesInventoryCompleted . "\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
    echo "unit_contract_created: 0\n";
    echo "normalization_proposals_created: 0\n";
    echo "sql_generated: 0\n";
    echo "apply_plan_created: 0\n";
    echo "safe_to_apply: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "production_ready: 0\n";
}

function valueOrNone($value)
{
    if ((string) $value === '') {
        return 'none';
    }

    return (string) $value;
}

function listOrNone(array $values)
{
    if (count($values) === 0) {
        return 'none';
    }

    return implode(' | ', $values);
}

function markdownCell($value)
{
    $value = valueOrNone($value);
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return $value;
}
