<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyReviewChainResultReporter
{
    public function summarize($approvalResult)
    {
        $errors = array();
        $warnings = array();
        $source = 'standalone_review_chain_result_reporter';

        if (!is_array($approvalResult)) {
            $errors[] = 'approval_result_must_be_array';
            return $this->createResult(0, $this->emptyCounts(), array(), $source, $errors, $warnings);
        }

        if (isset($approvalResult['source'])) {
            $source = $approvalResult['source'];
        }

        $counts = $this->countStatuses($approvalResult);
        $unsupportedStatuses = $counts['unsupported_statuses'];
        unset($counts['unsupported_statuses']);

        if (count($unsupportedStatuses) > 0) {
            $warnings[] = 'unsupported_statuses_detected';
        }

        return $this->createResult(1, $counts, $unsupportedStatuses, $source, $errors, $warnings);
    }

    private function countStatuses($approvalResult)
    {
        $counts = $this->emptyCounts();
        $rows = $this->extractProposalRows($approvalResult);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = $this->extractStatus($row);
            if ($status === null || $status === '') {
                $this->addUnsupportedStatus($counts, 'missing_status');
                continue;
            }

            if (isset($counts[$status . '_count'])) {
                $counts[$status . '_count']++;
                $counts['proposals_count']++;
                continue;
            }

            $this->addUnsupportedStatus($counts, $status);
            $counts['proposals_count']++;
        }

        if (count($rows) === 0 && isset($approvalResult['approval_summary']) && is_array($approvalResult['approval_summary'])) {
            $counts = $this->countsFromApprovalSummary($approvalResult['approval_summary'], $counts);
        }

        return $counts;
    }

    private function extractProposalRows($approvalResult)
    {
        if (isset($approvalResult['updated_proposals']) && is_array($approvalResult['updated_proposals'])) {
            return $approvalResult['updated_proposals'];
        }

        if (isset($approvalResult['proposals']) && is_array($approvalResult['proposals'])) {
            return $approvalResult['proposals'];
        }

        return array();
    }

    private function extractStatus($row)
    {
        if (isset($row['approval_status'])) {
            return $row['approval_status'];
        }

        if (isset($row['status'])) {
            return $row['status'];
        }

        if (isset($row['new_status'])) {
            return $row['new_status'];
        }

        return null;
    }

    private function countsFromApprovalSummary($summary, $counts)
    {
        $mapping = array(
            'total_proposals' => 'proposals_count',
            'approved_count' => 'approved_count',
            'rejected_count' => 'rejected_count',
            'needs_review_count' => 'needs_review_count',
            'unknown_count' => 'unknown_count',
            'proposed_count' => 'proposed_count',
        );

        foreach ($mapping as $summaryKey => $countKey) {
            if (isset($summary[$summaryKey]) && is_numeric($summary[$summaryKey])) {
                $counts[$countKey] = (int) $summary[$summaryKey];
            }
        }

        if ($counts['proposals_count'] === 0) {
            $counts['proposals_count'] =
                $counts['approved_count']
                + $counts['rejected_count']
                + $counts['needs_review_count']
                + $counts['unknown_count']
                + $counts['proposed_count'];
        }

        return $counts;
    }

    private function emptyCounts()
    {
        return array(
            'proposals_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'needs_review_count' => 0,
            'unknown_count' => 0,
            'proposed_count' => 0,
            'unsupported_statuses' => array(),
        );
    }

    private function addUnsupportedStatus(&$counts, $status)
    {
        if (!isset($counts['unsupported_statuses'][$status])) {
            $counts['unsupported_statuses'][$status] = 0;
        }

        $counts['unsupported_statuses'][$status]++;
    }

    private function createResult($reported, $counts, $unsupportedStatuses, $source, $errors, $warnings)
    {
        $unsupportedStatusesCount = 0;
        foreach ($unsupportedStatuses as $status => $count) {
            $unsupportedStatusesCount += $count;
        }

        $statusCounts = array(
            'approved' => $counts['approved_count'],
            'rejected' => $counts['rejected_count'],
            'needs_review' => $counts['needs_review_count'],
            'unknown' => $counts['unknown_count'],
            'proposed' => $counts['proposed_count'],
        );

        $summary = array(
            'total_proposals' => $counts['proposals_count'],
            'approved_count' => $counts['approved_count'],
            'rejected_count' => $counts['rejected_count'],
            'needs_review_count' => $counts['needs_review_count'],
            'unknown_count' => $counts['unknown_count'],
            'proposed_count' => $counts['proposed_count'],
            'unsupported_statuses_count' => $unsupportedStatusesCount,
        );

        $diagnostics = array(
            'reporter_mode' => 'standalone_review_chain_result_reporter',
            'proposals_count' => $counts['proposals_count'],
            'approved_count' => $counts['approved_count'],
            'rejected_count' => $counts['rejected_count'],
            'needs_review_count' => $counts['needs_review_count'],
            'unknown_count' => $counts['unknown_count'],
            'proposed_count' => $counts['proposed_count'],
            'unsupported_statuses' => $unsupportedStatuses,
            'unsupported_statuses_count' => $unsupportedStatusesCount,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );

        return array(
            'reported' => $reported,
            'review_chain_summary' => $summary,
            'status_counts' => $statusCounts,
            'reporter_diagnostics' => $diagnostics,
            'diagnostics' => $diagnostics,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $source,
        );
    }
}
