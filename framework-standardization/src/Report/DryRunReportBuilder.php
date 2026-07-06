<?php

namespace FrameworkStandardization\Report;

use FrameworkStandardization\Contract\ReportBuilderInterface;
use FrameworkStandardization\DTO\AttributeContext;

final class DryRunReportBuilder implements ReportBuilderInterface
{
    public function build(AttributeContext $context)
    {
        $rawJob = $context->getJob()->getRawJob();
        $canonical = $context->getCanonical();
        $scope = $context->getScope();
        $rawData = $context->getRawData();
        $attributeNameStructure = $context->getAttributeNameStructure();
        $synonymCandidates = $context->getSynonymCandidates();
        $attributeValueStructure = $context->getAttributeValueStructure();
        $sqlPreview = $context->getSqlPreview();
        $stageResults = $context->getStageResults();

        $products = isset($rawData['products']) && is_array($rawData['products']) ? $rawData['products'] : array();
        $attributes = isset($rawData['attributes']) && is_array($rawData['attributes']) ? $rawData['attributes'] : array();
        $productAttributes = isset($rawData['product_attributes']) && is_array($rawData['product_attributes']) ? $rawData['product_attributes'] : array();
        $foundAttributes = isset($attributeNameStructure['found_attributes']) && is_array($attributeNameStructure['found_attributes']) ? $attributeNameStructure['found_attributes'] : array();
        $exactMatches = isset($attributeNameStructure['exact_matches']) && is_array($attributeNameStructure['exact_matches']) ? $attributeNameStructure['exact_matches'] : array();
        $proposedSynonyms = isset($synonymCandidates['proposed']) && is_array($synonymCandidates['proposed']) ? $synonymCandidates['proposed'] : array();
        $ambiguousCandidates = isset($synonymCandidates['ambiguous']) && is_array($synonymCandidates['ambiguous']) ? $synonymCandidates['ambiguous'] : array();
        $valueDiagnostics = isset($attributeValueStructure['diagnostics']) && is_array($attributeValueStructure['diagnostics']) ? $attributeValueStructure['diagnostics'] : array();
        $rawProfile = isset($valueDiagnostics['raw_profile']) && is_array($valueDiagnostics['raw_profile']) ? $valueDiagnostics['raw_profile'] : array();
        $statements = isset($sqlPreview['statements']) && is_array($sqlPreview['statements']) ? $sqlPreview['statements'] : array();
        $blockedBy = isset($sqlPreview['blocked_by']) && is_array($sqlPreview['blocked_by']) ? $sqlPreview['blocked_by'] : array();
        $sqlPreviewDiagnostics = isset($sqlPreview['diagnostics']) && is_array($sqlPreview['diagnostics']) ? $sqlPreview['diagnostics'] : array();

        $report = array(
            'generated' => 1,
            'source' => 'dry_run_fixture',
            'mode' => 'dry_run_report',
            'title' => isset($rawJob['job_name']) ? $rawJob['job_name'] : '',
            'job_summary' => array(
                'job_id' => isset($rawJob['job_id']) ? $rawJob['job_id'] : '',
                'job_name' => isset($rawJob['job_name']) ? $rawJob['job_name'] : '',
            ),
            'canonical_summary' => array(
                'canonical_code' => isset($canonical['canonical_code']) ? $canonical['canonical_code'] : '',
                'target_attribute_id' => isset($canonical['target_attribute_id']) ? $canonical['target_attribute_id'] : '',
                'target_attribute_name' => isset($canonical['target_attribute_name']) ? $canonical['target_attribute_name'] : '',
            ),
            'scope_summary' => array(
                'type' => isset($scope['type']) ? $scope['type'] : '',
                'category_id' => isset($scope['category_id']) ? $scope['category_id'] : '',
                'product_count' => isset($scope['product_count']) ? $scope['product_count'] : count($products),
            ),
            'export_summary' => array(
                'product_count' => count($products),
                'attribute_count' => count($attributes),
                'product_attribute_count' => count($productAttributes),
            ),
            'name_analysis_summary' => array(
                'found_attribute_count' => count($foundAttributes),
                'exact_match_count' => count($exactMatches),
                'proposed_synonym_count' => count($proposedSynonyms),
                'ambiguous_candidate_count' => count($ambiguousCandidates),
            ),
            'value_analysis_summary' => array(
                'total_values' => isset($valueDiagnostics['total_values']) ? $valueDiagnostics['total_values'] : 0,
                'normalized_count' => isset($valueDiagnostics['normalized_count']) ? $valueDiagnostics['normalized_count'] : 0,
                'unknown_count' => isset($valueDiagnostics['unknown_count']) ? $valueDiagnostics['unknown_count'] : 0,
                'invalid_count' => isset($valueDiagnostics['invalid_count']) ? $valueDiagnostics['invalid_count'] : 0,
                'empty_count' => isset($valueDiagnostics['empty_count']) ? $valueDiagnostics['empty_count'] : 0,
            ),
            'sql_preview_summary' => array(
                'enabled' => isset($sqlPreview['enabled']) ? $sqlPreview['enabled'] : 0,
                'generated' => isset($sqlPreview['generated']) ? $sqlPreview['generated'] : 0,
                'safe_to_apply' => isset($sqlPreview['safe_to_apply']) ? $sqlPreview['safe_to_apply'] : 0,
                'statement_count' => count($statements),
                'blocked_by_count' => count($blockedBy),
            ),
            'stage_summary' => array(
                'stage_results' => $stageResults,
            ),
            'errors' => $context->errors,
            'warnings' => $context->warnings,
            'notes' => array(
                'dry-run fixture only',
                'no DB/OpenCart connection',
                'no SQL apply',
            ),
        );

        if ($rawProfile !== array()) {
            $report['raw_profile_summary'] = $this->buildRawProfileSummary($rawProfile);
            $report['notes'][] = 'raw profile is read-only diagnostics only';
            $report['notes'][] = 'raw profile is not normalization';
        }

        if ($this->isDbReadOnlySqlPreview($sqlPreview, $sqlPreviewDiagnostics)) {
            $report['sql_preview_safety_summary'] = $this->buildSqlPreviewSafetySummary($sqlPreview, $sqlPreviewDiagnostics, $statements, $blockedBy);
            $report['notes'][] = 'sql preview remains blocked and reporting-only';
        }

        return array(
            'built' => 1,
            'report' => $report,
            'errors' => array(),
            'warnings' => array(),
            'source' => 'dry_run_fixture',
        );
    }

    private function buildRawProfileSummary(array $rawProfile)
    {
        $topRawValues = isset($rawProfile['top_raw_values']) && is_array($rawProfile['top_raw_values']) ? $rawProfile['top_raw_values'] : array();

        return array(
            'total_values' => isset($rawProfile['total_values']) ? (int)$rawProfile['total_values'] : 0,
            'unique_raw_values_count' => isset($rawProfile['unique_raw_values_count']) ? (int)$rawProfile['unique_raw_values_count'] : 0,
            'empty_values_count' => isset($rawProfile['empty_values_count']) ? (int)$rawProfile['empty_values_count'] : 0,
            'suspicious_no_digits_count' => isset($rawProfile['suspicious_no_digits_count']) ? (int)$rawProfile['suspicious_no_digits_count'] : 0,
            'suspicious_long_value_count' => isset($rawProfile['suspicious_long_value_count']) ? (int)$rawProfile['suspicious_long_value_count'] : 0,
            'suspicious_multiple_numbers_count' => isset($rawProfile['suspicious_multiple_numbers_count']) ? (int)$rawProfile['suspicious_multiple_numbers_count'] : 0,
            'top_raw_values_count' => count($topRawValues),
            'source' => isset($rawProfile['source']) ? (string)$rawProfile['source'] : '',
            'note' => 'read_only_diagnostics_only',
        );
    }

    private function buildSqlPreviewSafetySummary(array $sqlPreview, array $sqlPreviewDiagnostics, array $statements, array $blockedBy)
    {
        return array(
            'generated' => isset($sqlPreview['generated']) ? (int)$sqlPreview['generated'] : 0,
            'safe_to_apply' => isset($sqlPreview['safe_to_apply']) ? (int)$sqlPreview['safe_to_apply'] : 0,
            'apply_changes' => isset($sqlPreview['apply_changes']) ? (int)$sqlPreview['apply_changes'] : 0,
            'statement_count' => count($statements),
            'blocked_by' => $blockedBy,
            'blocked_by_contains_db_readonly_sql_preview_not_implemented' => in_array('db_readonly_sql_preview_not_implemented', $blockedBy, true) ? 1 : 0,
            'raw_profile_present' => isset($sqlPreviewDiagnostics['raw_profile_present']) ? (int)$sqlPreviewDiagnostics['raw_profile_present'] : 0,
            'raw_profile_total_values' => isset($sqlPreviewDiagnostics['raw_profile_total_values']) ? (int)$sqlPreviewDiagnostics['raw_profile_total_values'] : 0,
            'unique_raw_values_count' => isset($sqlPreviewDiagnostics['unique_raw_values_count']) ? (int)$sqlPreviewDiagnostics['unique_raw_values_count'] : 0,
            'empty_values_count' => isset($sqlPreviewDiagnostics['empty_values_count']) ? (int)$sqlPreviewDiagnostics['empty_values_count'] : 0,
            'suspicious_no_digits_count' => isset($sqlPreviewDiagnostics['suspicious_no_digits_count']) ? (int)$sqlPreviewDiagnostics['suspicious_no_digits_count'] : 0,
            'suspicious_long_value_count' => isset($sqlPreviewDiagnostics['suspicious_long_value_count']) ? (int)$sqlPreviewDiagnostics['suspicious_long_value_count'] : 0,
            'suspicious_multiple_numbers_count' => isset($sqlPreviewDiagnostics['suspicious_multiple_numbers_count']) ? (int)$sqlPreviewDiagnostics['suspicious_multiple_numbers_count'] : 0,
            'top_raw_values_count' => isset($sqlPreviewDiagnostics['top_raw_values_count']) ? (int)$sqlPreviewDiagnostics['top_raw_values_count'] : 0,
            'raw_profile_source' => isset($sqlPreviewDiagnostics['raw_profile_source']) ? (string)$sqlPreviewDiagnostics['raw_profile_source'] : '',
            'note' => 'blocked_preview_diagnostics_only',
        );
    }

    private function isDbReadOnlySqlPreview(array $sqlPreview, array $sqlPreviewDiagnostics)
    {
        if (isset($sqlPreview['source']) && $sqlPreview['source'] === 'local_dump_db_readonly') {
            return true;
        }

        if (isset($sqlPreviewDiagnostics['source']) && $sqlPreviewDiagnostics['source'] === 'local_dump_db_readonly') {
            return true;
        }

        if (array_key_exists('raw_profile_present', $sqlPreviewDiagnostics)) {
            return true;
        }

        return false;
    }
}
