<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Canonical\DryRunCanonicalAttributeResolver;
use FrameworkStandardization\Exporter\DryRunAttributeExporter;
use FrameworkStandardization\Scope\DryRunScopeResolver;
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

        return new PipelineEngine([
            new ValidateJobStage(),
            new ResolveCanonicalStage($canonicalResolver),
            new ResolveScopeStage($scopeResolver),
            new ExportAttributesStage($attributeExporter),
            new AnalyzeNamesStage(),
            new AnalyzeValuesStage(),
            new BuildSqlPreviewStage(),
            new BuildReportStage(),
            new BuildFrameworkResultStage(),
        ]);
    }
}
