<?php

namespace FrameworkStandardization\Approval;

final class DbReadOnlyNormalizationApprovalFlow
{
    public function apply($proposals, $reviewActions)
    {
        if (!is_array($proposals)) {
            return $this->failed(array('proposals_must_be_array'));
        }

        if (!is_array($reviewActions)) {
            return $this->failed(array('review_actions_must_be_array'));
        }

        $errors = array();
        $warnings = array();
        $updatedProposals = array();
        $approvalAudit = array();
        $reviewActionsByProposalId = $this->indexReviewActions($reviewActions, $warnings);

        foreach ($proposals as $proposal) {
            if (!is_array($proposal)) {
                $errors[] = 'proposal_must_be_array';
                continue;
            }

            $proposalId = $this->readString($proposal, 'proposal_id');

            if ($proposalId === '') {
                $errors[] = 'proposal_id_missing';
                $updatedProposals[] = $proposal;
                continue;
            }

            if (!isset($reviewActionsByProposalId[$proposalId])) {
                $updatedProposals[] = $proposal;
                continue;
            }

            $reviewAction = $reviewActionsByProposalId[$proposalId];
            $action = $this->readString($reviewAction, 'action');
            $newStatus = $this->statusForAction($action);

            if ($newStatus === '') {
                $errors[] = 'review_action_not_allowed:' . $proposalId;
                $updatedProposals[] = $proposal;
                continue;
            }

            $previousStatus = $this->readString($proposal, 'approval_status');

            if ($previousStatus === '') {
                $previousStatus = 'proposed';
            }

            $proposal['approval_status'] = $newStatus;
            $updatedProposals[] = $proposal;
            $approvalAudit[] = $this->buildAuditEntry($proposalId, $action, $reviewAction, $previousStatus, $newStatus);
        }

        $summary = $this->buildSummary($updatedProposals, count($approvalAudit), count($errors));

        return array(
            'updated_proposals' => $updatedProposals,
            'approval_audit' => $approvalAudit,
            'approval_summary' => $summary,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => 'local_dump_db_readonly',
        );
    }

    private function indexReviewActions(array $reviewActions, array &$warnings)
    {
        $indexed = array();

        foreach ($reviewActions as $reviewAction) {
            if (!is_array($reviewAction)) {
                $warnings[] = 'review_action_must_be_array';
                continue;
            }

            $proposalId = $this->readString($reviewAction, 'proposal_id');

            if ($proposalId === '') {
                $warnings[] = 'review_action_proposal_id_missing';
                continue;
            }

            $indexed[$proposalId] = $reviewAction;
        }

        return $indexed;
    }

    private function statusForAction($action)
    {
        $map = array(
            'approve' => 'approved',
            'reject' => 'rejected',
            'mark_needs_review' => 'needs_review',
            'mark_unknown' => 'unknown',
            'reset_to_proposed' => 'proposed',
        );

        return isset($map[$action]) ? $map[$action] : '';
    }

    private function buildAuditEntry($proposalId, $action, array $reviewAction, $previousStatus, $newStatus)
    {
        return array(
            'proposal_id' => (string)$proposalId,
            'review_action' => (string)$action,
            'reviewer' => $this->readString($reviewAction, 'reviewer'),
            'reviewed_at' => date('c'),
            'review_note' => $this->readString($reviewAction, 'review_note'),
            'previous_status' => (string)$previousStatus,
            'new_status' => (string)$newStatus,
            'source' => $this->readString($reviewAction, 'source') === '' ? 'local_dump_db_readonly' : $this->readString($reviewAction, 'source'),
        );
    }

    private function buildSummary(array $updatedProposals, $changedCount, $errorCount)
    {
        $summary = array(
            'total_proposals' => count($updatedProposals),
            'approved_count' => 0,
            'rejected_count' => 0,
            'needs_review_count' => 0,
            'unknown_count' => 0,
            'proposed_count' => 0,
            'changed_count' => (int)$changedCount,
            'error_count' => (int)$errorCount,
            'source' => 'local_dump_db_readonly',
        );

        foreach ($updatedProposals as $proposal) {
            if (!is_array($proposal)) {
                continue;
            }

            $status = $this->readString($proposal, 'approval_status');

            if ($status === 'approved') {
                $summary['approved_count']++;
            } elseif ($status === 'rejected') {
                $summary['rejected_count']++;
            } elseif ($status === 'needs_review') {
                $summary['needs_review_count']++;
            } elseif ($status === 'unknown') {
                $summary['unknown_count']++;
            } else {
                $summary['proposed_count']++;
            }
        }

        return $summary;
    }

    private function readString(array $array, $key)
    {
        return isset($array[$key]) ? (string)$array[$key] : '';
    }

    private function failed(array $errors)
    {
        return array(
            'updated_proposals' => array(),
            'approval_audit' => array(),
            'approval_summary' => array(
                'total_proposals' => 0,
                'approved_count' => 0,
                'rejected_count' => 0,
                'needs_review_count' => 0,
                'unknown_count' => 0,
                'proposed_count' => 0,
                'changed_count' => 0,
                'error_count' => count($errors),
                'source' => 'local_dump_db_readonly',
            ),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }
}
