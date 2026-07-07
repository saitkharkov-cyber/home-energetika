<?php

date_default_timezone_set('UTC');

require_once __DIR__ . '/../src/Approval/DbReadOnlyLocalReviewFixtureGenerator.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyLocalReviewFixtureWriter.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyLocalReviewFixtureLoader.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyNormalizationApprovalFlow.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyLocalApprovalFixtureBridge.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyReviewChainResultReporter.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyStandaloneReviewChainE2EChecker.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyRealDataReviewChainUsageChecker.php';
require_once __DIR__ . '/../src/Approval/DbReadOnlyFirstRealDataUsageInputFixture.php';

use FrameworkStandardization\Approval\DbReadOnlyFirstRealDataUsageInputFixture;
use FrameworkStandardization\Approval\DbReadOnlyRealDataReviewChainUsageChecker;

if (isset($argc) && $argc > 1) {
    echo "used = 0" . PHP_EOL;
    echo "review_ready = 0" . PHP_EOL;
    echo "input_rows_count = 0" . PHP_EOL;
    echo "e2e_checked = 0" . PHP_EOL;
    echo "sql_generated = 0" . PHP_EOL;
    echo "apply_plan_created = 0" . PHP_EOL;
    echo "safe_to_apply = 0" . PHP_EOL;
    echo "sql_apply_allowed = 0" . PHP_EOL;
    echo "production_ready = 0" . PHP_EOL;
    echo "errors_count = 1" . PHP_EOL;
    echo "warnings_count = 0" . PHP_EOL;
    echo "error = cli_arguments_not_supported" . PHP_EOL;
    exit(2);
}

$fixtureProvider = new DbReadOnlyFirstRealDataUsageInputFixture();
$preparedFixture = $fixtureProvider->getPreparedFixture();
$preparedRows = isset($preparedFixture['rows']) && is_array($preparedFixture['rows'])
    ? $preparedFixture['rows']
    : array();

$inputRowsCount = count($preparedRows);
$errors = array();
$warningsCount = 0;
$used = 0;
$reviewReady = 0;
$e2eChecked = 0;
$sqlGenerated = 0;
$applyPlanCreated = 0;
$safeToApply = 0;
$sqlApplyAllowed = 0;
$productionReady = 0;

if ($inputRowsCount !== 8) {
    $errors[] = 'prepared_fixture_full_batch_expected_8_rows';
}

if ($inputRowsCount <= 4) {
    $errors[] = 'prepared_fixture_full_batch_requires_more_than_4_rows';
}

if ($inputRowsCount > 12) {
    $errors[] = 'prepared_fixture_full_batch_exceeds_12_rows';
}

if (count($errors) === 0) {
    $checker = new DbReadOnlyRealDataReviewChainUsageChecker();
    $chunks = array_chunk($preparedRows, 5);
    $allChunksUsed = 1;
    $allChunksReviewReady = 1;
    $allChunksE2eChecked = 1;

    foreach ($chunks as $chunkIndex => $chunkRows) {
        $readonlyInput = array(
            'context' => isset($preparedFixture['context']) ? $preparedFixture['context'] : 'pump_diameter',
            'source' => isset($preparedFixture['source_mode']) ? $preparedFixture['source_mode'] : 'local_readonly_dump_derived_test_fixture',
            'source_mode' => isset($preparedFixture['source_mode']) ? $preparedFixture['source_mode'] : 'local_readonly_dump_derived_test_fixture',
            'readonly' => 1,
            'rows' => $chunkRows,
            'diagnostics' => array(
                'checker_mode' => 'standalone_prepared_fixture_full_batch_check',
                'chunk_index' => $chunkIndex + 1,
                'chunks_count' => count($chunks),
                'prepared_rows_count' => $inputRowsCount,
                'chunk_rows_count' => count($chunkRows),
                'documented_prepared_fixture_max_rows' => 12,
                'sql_generated' => 0,
                'apply_plan_created' => 0,
                'safe_to_apply' => 0,
                'sql_apply_allowed' => 0,
                'production_ready' => 0,
            ),
        );

        $result = $checker->run($readonlyInput);
        $diagnostics = isset($result['usage_diagnostics']) && is_array($result['usage_diagnostics'])
            ? $result['usage_diagnostics']
            : array();

        $chunkUsed = isset($result['used']) ? (int) $result['used'] : 0;
        $chunkReviewReady = isset($diagnostics['review_ready']) ? (int) $diagnostics['review_ready'] : 0;
        $chunkE2eChecked = isset($diagnostics['e2e_checked']) ? (int) $diagnostics['e2e_checked'] : 0;

        if ($chunkUsed !== 1) {
            $allChunksUsed = 0;
        }

        if ($chunkReviewReady !== 1) {
            $allChunksReviewReady = 0;
        }

        if ($chunkE2eChecked !== 1) {
            $allChunksE2eChecked = 0;
        }

        if (isset($diagnostics['sql_generated']) && (int) $diagnostics['sql_generated'] !== 0) {
            $sqlGenerated = 1;
        }

        if (isset($diagnostics['apply_plan_created']) && (int) $diagnostics['apply_plan_created'] !== 0) {
            $applyPlanCreated = 1;
        }

        if (isset($diagnostics['safe_to_apply']) && (int) $diagnostics['safe_to_apply'] !== 0) {
            $safeToApply = 1;
        }

        if (isset($diagnostics['sql_apply_allowed']) && (int) $diagnostics['sql_apply_allowed'] !== 0) {
            $sqlApplyAllowed = 1;
        }

        if (isset($diagnostics['production_ready']) && (int) $diagnostics['production_ready'] !== 0) {
            $productionReady = 1;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        if (isset($result['warnings']) && is_array($result['warnings'])) {
            $warningsCount += count($result['warnings']);
        }
    }

    $used = $allChunksUsed === 1 && count($errors) === 0 ? 1 : 0;
    $reviewReady = $allChunksReviewReady === 1 && count($errors) === 0 ? 1 : 0;
    $e2eChecked = $allChunksE2eChecked === 1 ? 1 : 0;
}

$errorsCount = count($errors);

echo "used = " . $used . PHP_EOL;
echo "review_ready = " . $reviewReady . PHP_EOL;
echo "input_rows_count = " . $inputRowsCount . PHP_EOL;
echo "e2e_checked = " . $e2eChecked . PHP_EOL;
echo "sql_generated = " . $sqlGenerated . PHP_EOL;
echo "apply_plan_created = " . $applyPlanCreated . PHP_EOL;
echo "safe_to_apply = " . $safeToApply . PHP_EOL;
echo "sql_apply_allowed = " . $sqlApplyAllowed . PHP_EOL;
echo "production_ready = " . $productionReady . PHP_EOL;
echo "errors_count = " . $errorsCount . PHP_EOL;
echo "warnings_count = " . $warningsCount . PHP_EOL;

if ($errorsCount > 0) {
    foreach ($errors as $error) {
        echo "error = " . $error . PHP_EOL;
    }
}

$ok = $used === 1
    && $reviewReady === 1
    && $e2eChecked === 1
    && $inputRowsCount === 8
    && $inputRowsCount > 4
    && $inputRowsCount <= 12
    && $sqlGenerated === 0
    && $applyPlanCreated === 0
    && $safeToApply === 0
    && $sqlApplyAllowed === 0
    && $productionReady === 0
    && $errorsCount === 0;

exit($ok ? 0 : 1);
