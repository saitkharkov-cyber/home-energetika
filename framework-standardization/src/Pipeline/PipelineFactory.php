<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Analyzer\DryRunAttributeNameAnalyzer;
use FrameworkStandardization\Analyzer\DryRunAttributeValueAnalyzer;
use FrameworkStandardization\Canonical\DryRunCanonicalAttributeResolver;
use FrameworkStandardization\Exporter\DryRunAttributeExporter;
use FrameworkStandardization\Report\DryRunReportBuilder;
use FrameworkStandardization\Scope\DryRunScopeResolver;
use FrameworkStandardization\SqlPreview\DryRunSqlPreviewBuilder;
use FrameworkStandardization\Stage\AnalyzeNamesStage;
use FrameworkStandardization\Stage\AnalyzeValuesStage;
use FrameworkStandardization\Stage\BuildFrameworkResultStage;
use FrameworkStandardization\Stage\BuildReportStage;
use FrameworkStandardization\Stage\BuildSqlPreviewStage;
use FrameworkStandardization\Stage\ExportAttributesStage;
use FrameworkStandardization\Stage\ResolveCanonicalStage;
use FrameworkStandardization\Stage\ResolveScopeStage;
use FrameworkStandardization\Stage\ValidateJobStage;

final class PipelineFactory
{
    public function createDefault()
    {
        $canonicalResolver = new DryRunCanonicalAttributeResolver();
        $scopeResolver = new DryRunScopeResolver();
        $attributeExporter = new DryRunAttributeExporter();
        $attributeNameAnalyzer = new DryRunAttributeNameAnalyzer();
        $attributeValueAnalyzer = new DryRunAttributeValueAnalyzer();
        $sqlPreviewBuilder = new DryRunSqlPreviewBuilder();
        $reportBuilder = new DryRunReportBuilder();

        return new PipelineEngine([
            new ValidateJobStage(),
            new ResolveCanonicalStage($canonicalResolver),
            new ResolveScopeStage($scopeResolver),
            new ExportAttributesStage($attributeExporter),
            new AnalyzeNamesStage($attributeNameAnalyzer),
            new AnalyzeValuesStage($attributeValueAnalyzer),
            new BuildSqlPreviewStage($sqlPreviewBuilder),
            new BuildReportStage($reportBuilder),
            new BuildFrameworkResultStage(),
        ]);
    }
}
