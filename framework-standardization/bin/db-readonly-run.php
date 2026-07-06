<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\AttributeJob;
use FrameworkStandardization\DTO\FrameworkResult;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;
use FrameworkStandardization\Pipeline\DbReadOnlyPipelineFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "db-readonly-run.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || $argv[1] === '' || !isset($argv[2]) || $argv[2] === '') {
        throw new \InvalidArgumentException('usage: php bin/db-readonly-run.php path/to/job.php path/to/runtime.php');
    }

    $jobFile = $argv[1];
    $runtimeFile = $argv[2];

    if (!is_file($jobFile)) {
        throw new \InvalidArgumentException('job_config_not_found');
    }

    if (!is_file($runtimeFile)) {
        throw new \InvalidArgumentException('runtime_config_not_found');
    }

    $rawJob = require $jobFile;

    if (!is_array($rawJob)) {
        throw new \InvalidArgumentException('job_config_must_return_array');
    }

    $rawRuntime = require $runtimeFile;

    if (!is_array($rawRuntime)) {
        throw new \InvalidArgumentException('runtime_config_must_return_array');
    }

    $runtimeConfig = OpenCartRuntimeConfig::fromArray($rawRuntime);
    assertLocalRuntime($runtimeConfig);

    $db = new PdoReadOnlyDbConnection(createPdo($runtimeConfig));
    $pipeline = (new DbReadOnlyPipelineFactory())->createForDbReadonlyJob($rawJob, $runtimeConfig, $db);

    $job = AttributeJob::fromArray($rawJob);
    $context = new AttributeContext($job);
    $context = $pipeline->run($context);
    $result = $context->frameworkResult !== null ? $context->frameworkResult : FrameworkResult::fromContext($context);

    printResult($result);
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'db_readonly_error: ' . $e->getMessage() . "\n");
    exit(1);
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

function printResult($result)
{
    $stageSummary = $result->getStageSummary();
    $stageResults = isset($stageSummary['stage_results']) ? $stageSummary['stage_results'] : array();
    $warnings = isset($stageSummary['warnings']) ? $stageSummary['warnings'] : array();
    $errors = isset($stageSummary['errors']) ? $stageSummary['errors'] : array();

    echo "runtime_mode: db_readonly\n";
    echo "db_backed_stages: resolve_canonical, resolve_scope, export_attributes\n";
    echo "db_readonly_compatible_stages: analyze_names, analyze_values, build_sql_preview\n";
    echo "dry_run_stages: build_report, build_framework_result\n";
    echo 'result_status: ' . $result->getResultStatus() . "\n";
    echo 'warnings_count: ' . count($warnings) . "\n";
    echo 'errors_count: ' . count($errors) . "\n";
    echo "\n";
    echo "stage_results:\n";

    foreach ($stageResults as $stageName => $stageResult) {
        $status = isset($stageResult['status']) ? $stageResult['status'] : 'unknown';
        echo '- ' . $stageName . ': ' . $status . "\n";

        if (isset($stageResult['warnings']) && is_array($stageResult['warnings']) && count($stageResult['warnings']) > 0) {
            echo '  warnings: ' . implode(', ', $stageResult['warnings']) . "\n";
        }

        if (isset($stageResult['errors']) && is_array($stageResult['errors']) && count($stageResult['errors']) > 0) {
            echo '  errors: ' . implode(', ', $stageResult['errors']) . "\n";
        }
    }
}
