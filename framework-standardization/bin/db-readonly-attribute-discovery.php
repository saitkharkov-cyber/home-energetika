<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Discovery\DbReadOnlyAttributeDiscovery;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-attribute-discovery.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim($argv[1]) === '' || !isset($argv[2]) || trim($argv[2]) === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-attribute-discovery.php "target meaning" path/to/runtime.php [limit]');
    }

    $targetText = normalizeCliText(trim($argv[1]));
    $runtimeFile = $argv[2];
    $cliOptions = parseCliOptions($argv);
    $limit = $cliOptions['limit'];
    $format = $cliOptions['format'];
    $categoryId = $cliOptions['category_id'];

    assertSafeTargetText($targetText);

    if ($limit < 1 || $limit > 50) {
        throw new \InvalidArgumentException('limit_out_of_allowed_range');
    }

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    assertReadOnlyRuntime($runtimeConfig);

    $db = PdoReadOnlyDbConnection::fromRuntimeConfig($runtimeConfig);
    $discovery = new DbReadOnlyAttributeDiscovery($db, $runtimeConfig->getDbPrefix(), 1);
    $result = $discovery->discover($targetText, $limit, $categoryId);

    if ($format === 'markdown') {
        printDiscoveryResultMarkdown($targetText, $result);
    } else {
        printDiscoveryResult($targetText, $result);
    }
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'attribute_discovery_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCliOptions(array $argv)
{
    $limit = 20;
    $format = 'plain';
    $categoryId = null;
    $limitSeen = false;

    for ($i = 3; $i < count($argv); $i++) {
        $arg = trim($argv[$i]);

        if ($arg === '') {
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

        if (strpos($arg, '--category-id=') === 0) {
            $categoryIdValue = substr($arg, strlen('--category-id='));

            if (!preg_match('/^[1-9][0-9]*$/', $categoryIdValue)) {
                throw new \InvalidArgumentException('invalid_category_id');
            }

            $categoryId = (int) $categoryIdValue;
            continue;
        }

        if ($limitSeen) {
            throw new \InvalidArgumentException('unexpected_cli_argument');
        }

        if (!preg_match('/^\d+$/', $arg)) {
            throw new \InvalidArgumentException('unexpected_cli_argument');
        }

        $limit = (int) $arg;
        $limitSeen = true;
    }

    return array(
        'limit' => $limit,
        'format' => $format,
        'category_id' => $categoryId,
    );
}

function assertSafeTargetText($targetText)
{
    if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $targetText)) {
        throw new \InvalidArgumentException('target_text_must_not_be_path');
    }

    if (strpos($targetText, '://') !== false) {
        throw new \InvalidArgumentException('target_text_must_not_be_url');
    }

    if (is_file($targetText) || is_dir($targetText)) {
        throw new \InvalidArgumentException('target_text_must_not_be_existing_path');
    }
}

function normalizeCliText($value)
{
    if (function_exists('iconv')) {
        if (!preg_match('//u', $value)) {
            $converted = @iconv('Windows-1251', 'UTF-8//IGNORE', $value);

            if ($converted !== false && $converted !== '') {
                return $converted;
            }

            $converted = @iconv('CP866', 'UTF-8//IGNORE', $value);

            if ($converted !== false && $converted !== '') {
                return $converted;
            }
        }

        $converted = @iconv('Windows-1251', 'UTF-8//IGNORE', $value);

        if ($converted !== false && $converted !== '' && strpos($converted, 'Р') === false) {
            return $converted;
        }
    }

    return $value;
}

function assertReadOnlyRuntime(OpenCartRuntimeConfig $runtimeConfig)
{
    $runtimeMode = $runtimeConfig->getRuntimeMode();

    if ($runtimeMode !== 'db_readonly' && $runtimeMode !== 'live_db_readonly') {
        throw new \InvalidArgumentException('runtime_mode_not_readonly');
    }
}

function printDiscoveryResult($targetText, array $result)
{
    $candidates = isset($result['candidates']) && is_array($result['candidates']) ? $result['candidates'] : array();

    echo "runtime_mode: db_readonly\n";
    echo "command: attribute_discovery\n";
    echo 'target: ' . $targetText . "\n";
    echo 'category_scope: ' . categoryScopeValue($result) . "\n";
    echo 'candidates_count: ' . count($candidates) . "\n";
    echo "\n";
    echo "candidates:\n";

    foreach ($candidates as $candidate) {
        echo '- attribute_id: ' . valueOrEmpty($candidate, 'attribute_id') . "\n";
        echo '  attribute_name: ' . valueOrEmpty($candidate, 'attribute_name') . "\n";
        echo '  attribute_group_name: ' . valueOrEmpty($candidate, 'attribute_group_name') . "\n";
        echo '  usage_count: ' . valueOrEmpty($candidate, 'usage_count') . "\n";
        echo '  reason_found: ' . valueOrEmpty($candidate, 'reason_found') . "\n";
        echo '  possible_role: ' . valueOrEmpty($candidate, 'possible_role') . "\n";
        echo '  warnings: ' . listValueOrNone($candidate, 'warnings') . "\n";
        echo '  raw_samples: ' . listValueOrNone($candidate, 'raw_samples') . "\n";
    }

    echo "\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
    echo "raw_values_inventory_completed: 0\n";
    echo "unit_contract_created: 0\n";
    echo "normalization_proposals_created: 0\n";
    echo "sql_generated: 0\n";
    echo "apply_plan_created: 0\n";
    echo "safe_to_apply: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "production_ready: 0\n";
}

function printDiscoveryResultMarkdown($targetText, array $result)
{
    $candidates = isset($result['candidates']) && is_array($result['candidates']) ? $result['candidates'] : array();

    echo "# DB-readonly attribute discovery\n";
    echo "\n";
    echo "- runtime_mode: db_readonly\n";
    echo "- command: attribute_discovery\n";
    echo '- target: ' . markdownCell($targetText) . "\n";
    echo '- category_scope: ' . markdownCell(categoryScopeValue($result)) . "\n";
    echo '- candidates_count: ' . count($candidates) . "\n";
    echo "\n";
    echo "## Candidates\n";
    echo "\n";
    echo "| attribute_id | attribute_name | group | usage_count | reason_found | possible_role | warnings | raw_samples |\n";
    echo "| --- | --- | --- | --- | --- | --- | --- | --- |\n";

    foreach ($candidates as $candidate) {
        echo '| ' . markdownCell(valueOrNone($candidate, 'attribute_id'));
        echo ' | ' . markdownCell(valueOrNone($candidate, 'attribute_name'));
        echo ' | ' . markdownCell(valueOrNone($candidate, 'attribute_group_name'));
        echo ' | ' . markdownCell(valueOrNone($candidate, 'usage_count'));
        echo ' | ' . markdownCell(valueOrNone($candidate, 'reason_found'));
        echo ' | ' . markdownCell(valueOrNone($candidate, 'possible_role'));
        echo ' | ' . markdownCell(listValueOrNone($candidate, 'warnings'));
        echo ' | ' . markdownCell(listValueOrNone($candidate, 'raw_samples')) . " |\n";
    }

    echo "\n";
    echo "## Safety markers\n";
    echo "\n";
    echo "```text\n";
    echo "auto_canonical_selected: 0\n";
    echo "auto_merge_performed: 0\n";
    echo "raw_values_inventory_completed: 0\n";
    echo "unit_contract_created: 0\n";
    echo "normalization_proposals_created: 0\n";
    echo "sql_generated: 0\n";
    echo "apply_plan_created: 0\n";
    echo "safe_to_apply: 0\n";
    echo "sql_apply_allowed: 0\n";
    echo "production_ready: 0\n";
    echo "```\n";
}

function valueOrEmpty(array $row, $key)
{
    if (!isset($row[$key])) {
        return '';
    }

    return (string) $row[$key];
}

function listValueOrNone(array $row, $key)
{
    if (!isset($row[$key]) || !is_array($row[$key]) || count($row[$key]) === 0) {
        return 'none';
    }

    return implode(' | ', $row[$key]);
}

function valueOrNone(array $row, $key)
{
    if (!isset($row[$key]) || (string) $row[$key] === '') {
        return 'none';
    }

    return (string) $row[$key];
}

function markdownCell($value)
{
    $value = (string) $value;

    if ($value === '') {
        $value = 'none';
    }

    $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return $value;
}

function categoryScopeValue(array $result)
{
    if (!isset($result['category_id']) || $result['category_id'] === null) {
        return 'none';
    }

    return (string) $result['category_id'];
}
