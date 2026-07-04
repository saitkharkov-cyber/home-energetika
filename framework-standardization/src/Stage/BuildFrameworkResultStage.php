<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\FrameworkResultBuilderInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class BuildFrameworkResultStage implements StageInterface
{
    private $builder;

    public function __construct(FrameworkResultBuilderInterface $builder)
    {
        $this->builder = $builder;
    }

    public function getName()
    {
        return 'build_framework_result';
    }

    public function run(AttributeContext $context)
    {
        $result = $this->builder->build($context);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $frameworkResult = isset($result['framework_result']) ? $result['framework_result'] : null;
        $summary = $this->buildSummary($context, $frameworkResult, isset($result['source']) ? $result['source'] : 'unknown');

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        if ($frameworkResult === null) {
            $errors = array('framework_result_build_failed');

            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->addStageResult($this->getName(), StageResult::ok($summary));
        $finalResult = $this->builder->build($context);
        $finalFrameworkResult = isset($finalResult['framework_result']) ? $finalResult['framework_result'] : $frameworkResult;
        $context->setFrameworkResult($finalFrameworkResult);

        return $context;
    }

    private function buildSummary(AttributeContext $context, $frameworkResult, $source)
    {
        $report = $context->getReport();
        $sqlPreview = $context->getSqlPreview();

        return array(
            'result_status' => $frameworkResult === null ? '' : $frameworkResult->getResultStatus(),
            'completed_stage_count' => count($context->completedStages),
            'stage_count' => count($context->stageResults),
            'error_count' => count($context->errors),
            'warning_count' => count($context->warnings),
            'has_report' => $report === array() ? 0 : 1,
            'has_sql_preview' => $sqlPreview === array() ? 0 : 1,
            'source' => $source,
        );
    }
}
