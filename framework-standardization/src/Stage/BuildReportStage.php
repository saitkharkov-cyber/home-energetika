<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\ReportBuilderInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class BuildReportStage implements StageInterface
{
    private $builder;

    public function __construct(ReportBuilderInterface $builder)
    {
        $this->builder = $builder;
    }

    public function getName()
    {
        return 'build_report';
    }

    public function run(AttributeContext $context)
    {
        $result = $this->builder->build($context);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $report = isset($result['report']) && is_array($result['report']) ? $result['report'] : array();
        $summary = $this->buildSummary($report);

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->setReport($report);
            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setReport($report);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }

    private function buildSummary(array $report)
    {
        $sections = array(
            'job_summary',
            'canonical_summary',
            'scope_summary',
            'export_summary',
            'name_analysis_summary',
            'value_analysis_summary',
            'sql_preview_summary',
            'stage_summary',
        );
        $errors = isset($report['errors']) && is_array($report['errors']) ? $report['errors'] : array();
        $warnings = isset($report['warnings']) && is_array($report['warnings']) ? $report['warnings'] : array();
        $stageSummary = isset($report['stage_summary']) && is_array($report['stage_summary']) ? $report['stage_summary'] : array();
        $stageResults = isset($stageSummary['stage_results']) && is_array($stageSummary['stage_results']) ? $stageSummary['stage_results'] : array();

        return array(
            'generated' => isset($report['generated']) ? $report['generated'] : 0,
            'section_count' => count($sections),
            'error_count' => count($errors),
            'warning_count' => count($warnings),
            'stage_count' => count($stageResults),
            'source' => isset($report['source']) ? $report['source'] : 'unknown',
        );
    }
}
