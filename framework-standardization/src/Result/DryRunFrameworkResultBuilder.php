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

        return array(
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
            'sql_preview' => $context->getSqlPreview(),
            'report' => $context->getReport(),
            'warnings' => $context->warnings,
            'errors' => $context->errors,
            'source' => 'dry_run_fixture',
            'mode' => 'dry_run_framework_result',
        );
    }
}
