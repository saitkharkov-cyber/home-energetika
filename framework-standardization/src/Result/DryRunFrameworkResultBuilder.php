<?php

namespace FrameworkStandardization\Result;

use FrameworkStandardization\Contract\FrameworkResultBuilderInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\FrameworkResult;

final class DryRunFrameworkResultBuilder implements FrameworkResultBuilderInterface
{
    public function build(AttributeContext $context)
    {
        $frameworkResult = new FrameworkResult(
            $this->resolveStatus($context),
            $this->buildStageSummary($context),
            $this->buildPayload($context)
        );

        return array(
            'framework_result' => $frameworkResult,
            'errors' => array(),
            'source' => 'dry_run_fixture',
        );
    }

    private function resolveStatus(AttributeContext $context)
    {
        if ($context->hasErrors()) {
            return 'failed';
        }

        if ($context->warnings !== array()) {
            return 'ok_with_warnings';
        }

        return 'ok';
    }

    private function buildStageSummary(AttributeContext $context)
    {
        return array(
            'completed_stages' => $context->completedStages,
            'stage_results' => $context->stageResults,
            'warnings' => $context->warnings,
            'errors' => $context->errors,
        );
    }

    private function buildPayload(AttributeContext $context)
    {
        $rawJob = $context->getJob()->getRawJob();
        $canonical = $context->getCanonical();
        $scope = $context->getScope();
        $attributeNameStructure = $context->getAttributeNameStructure();
        $synonymCandidates = $context->getSynonymCandidates();
        $attributeValueStructure = $context->getAttributeValueStructure();
        $sqlPreview = $context->getSqlPreview();
        $report = $context->getReport();

        $payload = array(
            'job_summary' => array(
                'job_id' => isset($rawJob['job_id']) ? $rawJob['job_id'] : '',
                'job_name' => isset($rawJob['job_name']) ? $rawJob['job_name'] : '',
            ),
            'canonical_attribute' => $canonical,
            'scope_summary' => $scope,
            'found_attributes' => isset($attributeNameStructure['found_attributes']) && is_array($attributeNameStructure['found_attributes']) ? $attributeNameStructure['found_attributes'] : array(),
            'proposed_synonym_candidates' => isset($synonymCandidates['proposed']) && is_array($synonymCandidates['proposed']) ? $synonymCandidates['proposed'] : array(),
            'rejected_candidates' => isset($synonymCandidates['rejected']) && is_array($synonymCandidates['rejected']) ? $synonymCandidates['rejected'] : array(),
            'ambiguous_candidates' => isset($synonymCandidates['ambiguous']) && is_array($synonymCandidates['ambiguous']) ? $synonymCandidates['ambiguous'] : array(),
            'value_report' => $context->getValueReport(),
            'unknown_values' => isset($attributeValueStructure['unknown_values']) && is_array($attributeValueStructure['unknown_values']) ? $attributeValueStructure['unknown_values'] : array(),
            'sql_preview' => $sqlPreview,
            'report' => $report,
            'warnings' => $context->warnings,
            'errors' => $context->errors,
            'source' => 'dry_run_fixture',
            'mode' => 'dry_run_framework_result',
        );

        if ($this->hasDbReadOnlyDiagnostics($report, $sqlPreview)) {
            $payload['diagnostics_summary'] = $this->buildDiagnosticsSummary($report, $sqlPreview);
            $payload['safety_summary'] = $this->buildSafetySummary($sqlPreview);
        }

        return $payload;
    }

    private function hasDbReadOnlyDiagnostics(array $report, array $sqlPreview)
    {
        if (isset($report['raw_profile_summary']) && is_array($report['raw_profile_summary'])) {
            return true;
        }

        if (isset($report['sql_preview_safety_summary']) && is_array($report['sql_preview_safety_summary'])) {
            return true;
        }

        if (isset($sqlPreview['source']) && $sqlPreview['source'] === 'local_dump_db_readonly') {
            return true;
        }

        return false;
    }

    private function buildDiagnosticsSummary(array $report, array $sqlPreview)
    {
        $rawProfileSummary = isset($report['raw_profile_summary']) && is_array($report['raw_profile_summary']) ? $report['raw_profile_summary'] : array();
        $sqlPreviewSafetySummary = isset($report['sql_preview_safety_summary']) && is_array($report['sql_preview_safety_summary']) ? $report['sql_preview_safety_summary'] : array();
        $sqlPreviewDiagnostics = isset($sqlPreview['diagnostics']) && is_array($sqlPreview['diagnostics']) ? $sqlPreview['diagnostics'] : array();
        $statements = isset($sqlPreview['statements']) && is_array($sqlPreview['statements']) ? $sqlPreview['statements'] : array();

        return array(
            'raw_profile_present' => $rawProfileSummary !== array() ? 1 : (isset($sqlPreviewDiagnostics['raw_profile_present']) ? (int)$sqlPreviewDiagnostics['raw_profile_present'] : 0),
            'raw_profile_total_values' => isset($rawProfileSummary['total_values']) ? (int)$rawProfileSummary['total_values'] : (isset($sqlPreviewDiagnostics['raw_profile_total_values']) ? (int)$sqlPreviewDiagnostics['raw_profile_total_values'] : 0),
            'unique_raw_values_count' => isset($rawProfileSummary['unique_raw_values_count']) ? (int)$rawProfileSummary['unique_raw_values_count'] : (isset($sqlPreviewDiagnostics['unique_raw_values_count']) ? (int)$sqlPreviewDiagnostics['unique_raw_values_count'] : 0),
            'suspicious_no_digits_count' => isset($rawProfileSummary['suspicious_no_digits_count']) ? (int)$rawProfileSummary['suspicious_no_digits_count'] : (isset($sqlPreviewDiagnostics['suspicious_no_digits_count']) ? (int)$sqlPreviewDiagnostics['suspicious_no_digits_count'] : 0),
            'suspicious_long_value_count' => isset($rawProfileSummary['suspicious_long_value_count']) ? (int)$rawProfileSummary['suspicious_long_value_count'] : (isset($sqlPreviewDiagnostics['suspicious_long_value_count']) ? (int)$sqlPreviewDiagnostics['suspicious_long_value_count'] : 0),
            'suspicious_multiple_numbers_count' => isset($rawProfileSummary['suspicious_multiple_numbers_count']) ? (int)$rawProfileSummary['suspicious_multiple_numbers_count'] : (isset($sqlPreviewDiagnostics['suspicious_multiple_numbers_count']) ? (int)$sqlPreviewDiagnostics['suspicious_multiple_numbers_count'] : 0),
            'report_has_raw_profile_summary' => $rawProfileSummary !== array() ? 1 : 0,
            'report_has_sql_preview_safety_summary' => $sqlPreviewSafetySummary !== array() ? 1 : 0,
            'sql_preview_safe_to_apply' => isset($sqlPreview['safe_to_apply']) ? (int)$sqlPreview['safe_to_apply'] : 0,
            'sql_preview_statement_count' => count($statements),
            'blocked_preview_expected' => $this->hasBlockedPreviewMarker($sqlPreview, $sqlPreviewSafetySummary) ? 1 : 0,
        );
    }

    private function buildSafetySummary(array $sqlPreview)
    {
        $statements = isset($sqlPreview['statements']) && is_array($sqlPreview['statements']) ? $sqlPreview['statements'] : array();

        return array(
            'generated' => 0,
            'safe_to_apply' => isset($sqlPreview['safe_to_apply']) ? (int)$sqlPreview['safe_to_apply'] : 0,
            'statements_count' => count($statements),
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );
    }

    private function hasBlockedPreviewMarker(array $sqlPreview, array $sqlPreviewSafetySummary)
    {
        $blockedBy = isset($sqlPreview['blocked_by']) && is_array($sqlPreview['blocked_by']) ? $sqlPreview['blocked_by'] : array();

        if (in_array('db_readonly_sql_preview_not_implemented', $blockedBy, true)) {
            return true;
        }

        if (
            isset($sqlPreviewSafetySummary['blocked_by_contains_db_readonly_sql_preview_not_implemented']) &&
            (int)$sqlPreviewSafetySummary['blocked_by_contains_db_readonly_sql_preview_not_implemented'] === 1
        ) {
            return true;
        }

        return false;
    }
}
