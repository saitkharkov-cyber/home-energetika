<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyStandaloneReviewChainE2EChecker
{
    public function run($parserOutput)
    {
        $errors = array();
        $warnings = array();
        $source = 'standalone_review_chain_e2e_checker';
        $fixture = null;
        $fixtureFilename = '';
        $tempFixturePath = '';
        $tempFixtureCreated = 0;
        $tempFixtureRemoved = 0;
        $generatorResult = null;
        $writerResult = null;
        $loaderResult = null;
        $bridgeResult = null;
        $reporterResult = null;

        if (!is_array($parserOutput)) {
            $errors[] = 'parser_output_must_be_array';
            return $this->createResult(
                0,
                $this->buildE2eDiagnostics(
                    $tempFixtureCreated,
                    $tempFixtureRemoved,
                    $tempFixturePath,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0
                ),
                $this->buildComponentDiagnostics(
                    $generatorResult,
                    $writerResult,
                    $loaderResult,
                    $bridgeResult,
                    $reporterResult
                ),
                array(),
                $errors,
                $warnings,
                $source
            );
        }

        if (isset($parserOutput['source']) && $parserOutput['source'] !== '') {
            $source = (string)$parserOutput['source'];
        }

        $generator = new DbReadOnlyLocalReviewFixtureGenerator();
        $writer = new DbReadOnlyLocalReviewFixtureWriter();
        $loader = new DbReadOnlyLocalReviewFixtureLoader();
        $bridge = new DbReadOnlyLocalApprovalFixtureBridge();
        $reporter = new DbReadOnlyReviewChainResultReporter();

        $generatorResult = $generator->generate($parserOutput);
        $this->mergeMessages($generatorResult, $errors, $warnings);

        $generatorOk = $this->hasNoErrors($generatorResult)
            && isset($generatorResult['proposals'])
            && is_array($generatorResult['proposals'])
            && count($generatorResult['proposals']) > 0
            ? 1
            : 0;

        if ($generatorOk) {
            $fixture = $this->addSyntheticReviewBlocks($generatorResult);
            $fixtureFilename = $this->buildTempFilename();
            $writerResult = $writer->write($fixture, $fixtureFilename);
            $this->mergeMessages($writerResult, $errors, $warnings);

            if (isset($writerResult['target_file'])) {
                $tempFixturePath = $writerResult['target_file'];
            }

            if ($this->readInt($writerResult, 'wrote_file') === 1) {
                $tempFixtureCreated = 1;
            }
        }

        $writerOk = $this->hasNoErrors($writerResult)
            && $this->readInt($writerResult, 'wrote_file') === 1
            ? 1
            : 0;

        if ($writerOk) {
            $loaderResult = $loader->load($fixtureFilename);
            $this->mergeMessages($loaderResult, $errors, $warnings);
        }

        $loaderOk = $this->hasNoErrors($loaderResult)
            && $this->readInt($loaderResult, 'loaded') === 1
            && isset($loaderResult['fixture'])
            && is_array($loaderResult['fixture'])
            ? 1
            : 0;

        if ($loaderOk) {
            $bridgeResult = $bridge->applyFixture($loaderResult['fixture']);
            $this->mergeMessages($bridgeResult, $errors, $warnings);
        }

        $bridgeOk = $this->hasNoErrors($bridgeResult)
            && isset($bridgeResult['bridge_diagnostics'])
            && is_array($bridgeResult['bridge_diagnostics'])
            ? 1
            : 0;

        $approvalFlowOk = $this->hasNoErrors($bridgeResult)
            && isset($bridgeResult['approval_summary'])
            && is_array($bridgeResult['approval_summary'])
            && isset($bridgeResult['updated_proposals'])
            && is_array($bridgeResult['updated_proposals'])
            ? 1
            : 0;

        if ($approvalFlowOk) {
            $reporterResult = $reporter->summarize($bridgeResult);
            $this->mergeMessages($reporterResult, $errors, $warnings);
        }

        $reporterOk = $this->hasNoErrors($reporterResult)
            && $this->readInt($reporterResult, 'reported') === 1
            ? 1
            : 0;

        if ($tempFixtureCreated && $tempFixturePath !== '' && is_file($tempFixturePath)) {
            if (@unlink($tempFixturePath)) {
                $tempFixtureRemoved = 1;
            } else {
                $errors[] = 'temp_fixture_remove_failed';
            }
        } elseif ($tempFixtureCreated) {
            $tempFixtureRemoved = 1;
        }

        $diagnostics = $this->buildE2eDiagnostics(
            $tempFixtureCreated,
            $tempFixtureRemoved,
            $tempFixturePath,
            $generatorOk,
            $writerOk,
            $loaderOk,
            $bridgeOk,
            $approvalFlowOk,
            $reporterOk
        );

        $checked = $generatorOk
            && $writerOk
            && $loaderOk
            && $bridgeOk
            && $approvalFlowOk
            && $reporterOk
            && $tempFixtureCreated
            && $tempFixtureRemoved
            && count($errors) === 0
            ? 1
            : 0;

        return $this->createResult(
            $checked,
            $diagnostics,
            $this->buildComponentDiagnostics(
                $generatorResult,
                $writerResult,
                $loaderResult,
                $bridgeResult,
                $reporterResult
            ),
            $this->extractReporterSummary($reporterResult),
            $errors,
            $warnings,
            $source
        );
    }

    private function addSyntheticReviewBlocks($fixture)
    {
        $actions = array('approve', 'reject', 'mark_needs_review');

        foreach ($fixture['proposals'] as $index => $proposal) {
            if (!is_array($proposal)) {
                continue;
            }

            $action = isset($actions[$index]) ? $actions[$index] : '';

            $proposal['review'] = array(
                'action' => $action,
                'reviewer' => $action === '' ? '' : 'standalone_e2e_check',
                'review_note' => $action === '' ? '' : 'synthetic standalone E2E check',
                'source' => 'standalone_review_chain_e2e_checker',
            );

            $fixture['proposals'][$index] = $proposal;
        }

        return $fixture;
    }

    private function buildTempFilename()
    {
        return 'review_chain_e2e_' . gmdate('Ymd_His') . '_' . getmypid() . '.review.json';
    }

    private function buildE2eDiagnostics(
        $tempFixtureCreated,
        $tempFixtureRemoved,
        $localFixturePath,
        $generatorOk,
        $writerOk,
        $loaderOk,
        $bridgeOk,
        $approvalFlowOk,
        $reporterOk
    ) {
        return array(
            'checker_mode' => 'standalone_review_chain_e2e_checker',
            'chain_mode' => 'standalone_local_review_chain',
            'input_mode' => 'synthetic_in_memory_parser_output',
            'temp_fixture_created' => (int)$tempFixtureCreated,
            'temp_fixture_removed' => (int)$tempFixtureRemoved,
            'local_fixture_path' => (string)$localFixturePath,
            'generator_ok' => (int)$generatorOk,
            'writer_ok' => (int)$writerOk,
            'loader_ok' => (int)$loaderOk,
            'bridge_ok' => (int)$bridgeOk,
            'approval_flow_ok' => (int)$approvalFlowOk,
            'reporter_ok' => (int)$reporterOk,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );
    }

    private function buildComponentDiagnostics($generatorResult, $writerResult, $loaderResult, $bridgeResult, $reporterResult)
    {
        return array(
            'generator' => is_array($generatorResult) && isset($generatorResult['generator_diagnostics'])
                ? $generatorResult['generator_diagnostics']
                : array(),
            'writer' => is_array($writerResult) && isset($writerResult['writer_diagnostics'])
                ? $writerResult['writer_diagnostics']
                : array(),
            'loader' => is_array($loaderResult) && isset($loaderResult['loader_diagnostics'])
                ? $loaderResult['loader_diagnostics']
                : array(),
            'bridge' => is_array($bridgeResult) && isset($bridgeResult['bridge_diagnostics'])
                ? $bridgeResult['bridge_diagnostics']
                : array(),
            'approval_flow' => is_array($bridgeResult) && isset($bridgeResult['approval_summary'])
                ? $bridgeResult['approval_summary']
                : array(),
            'reporter' => is_array($reporterResult) && isset($reporterResult['reporter_diagnostics'])
                ? $reporterResult['reporter_diagnostics']
                : array(),
        );
    }

    private function extractReporterSummary($reporterResult)
    {
        if (is_array($reporterResult) && isset($reporterResult['review_chain_summary']) && is_array($reporterResult['review_chain_summary'])) {
            return $reporterResult['review_chain_summary'];
        }

        return array();
    }

    private function createResult($checked, $e2eDiagnostics, $componentDiagnostics, $reporterSummary, $errors, $warnings, $source)
    {
        return array(
            'checked' => (int)$checked,
            'e2e_diagnostics' => $e2eDiagnostics,
            'component_diagnostics' => $componentDiagnostics,
            'reporter_summary' => $reporterSummary,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $source,
        );
    }

    private function mergeMessages($result, &$errors, &$warnings)
    {
        if (!is_array($result)) {
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        if (isset($result['warnings']) && is_array($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }
    }

    private function hasNoErrors($result)
    {
        return is_array($result)
            && (!isset($result['errors']) || !is_array($result['errors']) || count($result['errors']) === 0);
    }

    private function readInt($array, $key)
    {
        if (!is_array($array) || !isset($array[$key])) {
            return 0;
        }

        return (int)$array[$key];
    }
}
