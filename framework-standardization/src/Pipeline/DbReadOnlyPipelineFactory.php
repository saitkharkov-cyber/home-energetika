<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Analyzer\DbReadOnlyAttributeNameAnalyzer;
use FrameworkStandardization\Analyzer\DbReadOnlyAttributeValueAnalyzer;
use FrameworkStandardization\Canonical\DbReadOnlyCanonicalAttributeResolver;
use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use FrameworkStandardization\Exporter\DbReadOnlyAttributeExporter;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\OpenCartTableName;
use FrameworkStandardization\Report\DryRunReportBuilder;
use FrameworkStandardization\Result\DryRunFrameworkResultBuilder;
use FrameworkStandardization\Scope\DbReadOnlyScopeResolver;
use FrameworkStandardization\SqlPreview\DbReadOnlySqlPreviewBuilder;
use FrameworkStandardization\Stage\AnalyzeNamesStage;
use FrameworkStandardization\Stage\AnalyzeValuesStage;
use FrameworkStandardization\Stage\BuildFrameworkResultStage;
use FrameworkStandardization\Stage\BuildReportStage;
use FrameworkStandardization\Stage\BuildSqlPreviewStage;
use FrameworkStandardization\Stage\ExportAttributesStage;
use FrameworkStandardization\Stage\ResolveCanonicalStage;
use FrameworkStandardization\Stage\ResolveScopeStage;
use FrameworkStandardization\Stage\ValidateJobStage;

final class DbReadOnlyPipelineFactory
{
    public function createForDbReadonlyJob(array $rawJob, OpenCartRuntimeConfig $runtimeConfig, ReadOnlyDbConnectionInterface $db)
    {
        $this->assertSupportedRuntime($runtimeConfig);
        $this->assertSupportedJob($rawJob);

        $tableName = new OpenCartTableName($runtimeConfig->getDbPrefix());
        $canonicalResolver = new DbReadOnlyCanonicalAttributeResolver($db, $tableName, $this->buildMapping($rawJob));

        $scopeResolver = new DbReadOnlyScopeResolver($db, $tableName, $this->buildScopeRuntimeContext($rawJob));
        $attributeExporter = new DbReadOnlyAttributeExporter($db, $tableName, $this->buildExportRuntimeContext($rawJob));
        $attributeNameAnalyzer = new DbReadOnlyAttributeNameAnalyzer();
        $attributeValueAnalyzer = new DbReadOnlyAttributeValueAnalyzer();
        $sqlPreviewBuilder = new DbReadOnlySqlPreviewBuilder();
        $reportBuilder = new DryRunReportBuilder();
        $frameworkResultBuilder = new DryRunFrameworkResultBuilder();

        return new PipelineEngine(array(
            new ValidateJobStage(),
            new ResolveCanonicalStage($canonicalResolver),
            new ResolveScopeStage($scopeResolver),
            new ExportAttributesStage($attributeExporter),
            new AnalyzeNamesStage($attributeNameAnalyzer),
            new AnalyzeValuesStage($attributeValueAnalyzer),
            new BuildSqlPreviewStage($sqlPreviewBuilder),
            new BuildReportStage($reportBuilder),
            new BuildFrameworkResultStage($frameworkResultBuilder),
        ));
    }

    private function assertSupportedRuntime(OpenCartRuntimeConfig $runtimeConfig)
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

    private function assertSupportedJob(array $rawJob)
    {
        if (!isset($rawJob['job_id']) || $rawJob['job_id'] !== 'pump_diameter_borehole_pumps_db_readonly') {
            throw new \InvalidArgumentException('job_not_db_readonly');
        }

        if (!isset($rawJob['source']['database']) || $rawJob['source']['database'] !== 'local_dump') {
            throw new \InvalidArgumentException('job_database_not_local_dump');
        }

        if (!isset($rawJob['source']['language_id']) || (int)$rawJob['source']['language_id'] !== 1) {
            throw new \InvalidArgumentException('job_language_id_not_supported');
        }

        if (!isset($rawJob['scope']['category_id']) || (int)$rawJob['scope']['category_id'] !== 11900213) {
            throw new \InvalidArgumentException('job_scope_not_supported');
        }

        if (!isset($rawJob['canonical']['canonical_code']) || $rawJob['canonical']['canonical_code'] !== 'pump_diameter') {
            throw new \InvalidArgumentException('job_canonical_not_supported');
        }

        if (!isset($rawJob['output']['apply_changes']) || (int)$rawJob['output']['apply_changes'] !== 0) {
            throw new \InvalidArgumentException('apply_changes_not_allowed');
        }
    }

    private function buildMapping(array $rawJob)
    {
        return array(
            'canonical_code' => (string)$rawJob['canonical']['canonical_code'],
            'category_id' => (int)$rawJob['scope']['category_id'],
            'category_name' => isset($rawJob['scope']['category_name']) ? (string)$rawJob['scope']['category_name'] : '',
            'language_id' => (int)$rawJob['source']['language_id'],
            'target_attribute_id' => 44,
            'target_attribute_name' => 'Диаметр насоса',
            'target_attribute_group_id' => 8,
            'target_attribute_group_name' => 'Прочие',
            'expected_usage_count' => 385,
        );
    }

    private function buildScopeRuntimeContext(array $rawJob)
    {
        return array(
            'language_id' => (int)$rawJob['source']['language_id'],
            'expected_category_id' => (int)$rawJob['scope']['category_id'],
            'expected_category_name' => isset($rawJob['scope']['category_name']) ? (string)$rawJob['scope']['category_name'] : '',
            'source' => 'local_dump_db_readonly',
        );
    }

    private function buildExportRuntimeContext(array $rawJob)
    {
        return array(
            'language_id' => (int)$rawJob['source']['language_id'],
            'source' => 'local_dump_db_readonly',
            'max_sample_values' => isset($rawJob['analysis_rules']['max_sample_values']) ? (int)$rawJob['analysis_rules']['max_sample_values'] : 20,
        );
    }
}
