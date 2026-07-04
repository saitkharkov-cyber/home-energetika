<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Runner\FrameworkRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "dry-run.php must be executed from CLI.\n");
    exit(1);
}

if (!isset($argv[1]) || $argv[1] === '') {
    fwrite(STDERR, "Usage: php bin/dry-run.php path/to/job.php\n");
    exit(1);
}

$jobFile = $argv[1];

if (!is_file($jobFile)) {
    fwrite(STDERR, "Job config not found: " . $jobFile . "\n");
    exit(1);
}

$rawJob = require $jobFile;

if (!is_array($rawJob)) {
    fwrite(STDERR, "Job config must return array.\n");
    exit(1);
}

$runner = new FrameworkRunner();
$result = $runner->run($rawJob);
$stageSummary = $result->getStageSummary();
$stageResults = isset($stageSummary['stage_results']) ? $stageSummary['stage_results'] : array();

echo 'result_status: ' . $result->getResultStatus() . "\n";
echo "\n";
echo "stage_results:\n";

foreach ($stageResults as $stageName => $stageResult) {
    $status = isset($stageResult['status']) ? $stageResult['status'] : 'unknown';
    echo '- ' . $stageName . ': ' . $status . "\n";
}
