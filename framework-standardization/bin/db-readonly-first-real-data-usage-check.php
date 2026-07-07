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
$readonlyInput = $fixtureProvider->getFirstRunSlice(2);

$checker = new DbReadOnlyRealDataReviewChainUsageChecker();
$result = $checker->run($readonlyInput);

$diagnostics = isset($result['usage_diagnostics']) && is_array($result['usage_diagnostics'])
    ? $result['usage_diagnostics']
    : array();

$used = isset($result['used']) ? (int) $result['used'] : 0;
$reviewReady = isset($diagnostics['review_ready']) ? (int) $diagnostics['review_ready'] : 0;
$inputRowsCount = isset($diagnostics['input_rows_count']) ? (int) $diagnostics['input_rows_count'] : 0;
$e2eChecked = isset($diagnostics['e2e_checked']) ? (int) $diagnostics['e2e_checked'] : 0;
$sqlGenerated = isset($diagnostics['sql_generated']) ? (int) $diagnostics['sql_generated'] : 0;
$applyPlanCreated = isset($diagnostics['apply_plan_created']) ? (int) $diagnostics['apply_plan_created'] : 0;
$safeToApply = isset($diagnostics['safe_to_apply']) ? (int) $diagnostics['safe_to_apply'] : 0;
$sqlApplyAllowed = isset($diagnostics['sql_apply_allowed']) ? (int) $diagnostics['sql_apply_allowed'] : 0;
$productionReady = isset($diagnostics['production_ready']) ? (int) $diagnostics['production_ready'] : 0;
$errorsCount = isset($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0;
$warningsCount = isset($result['warnings']) && is_array($result['warnings']) ? count($result['warnings']) : 0;

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

$ok = $used === 1
    && $reviewReady === 1
    && $e2eChecked === 1
    && $inputRowsCount <= 2
    && $sqlGenerated === 0
    && $applyPlanCreated === 0
    && $safeToApply === 0
    && $sqlApplyAllowed === 0
    && $productionReady === 0
    && $errorsCount === 0;

exit($ok ? 0 : 1);
