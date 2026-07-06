<?php

namespace FrameworkStandardization\Approval;

final class DbReadOnlyLocalApprovalFixtureBridge
{
    private $approvalFlow;

    public function __construct($approvalFlow = null)
    {
        if ($approvalFlow === null) {
            $approvalFlow = new DbReadOnlyNormalizationApprovalFlow();
        }

        $this->approvalFlow = $approvalFlow;
    }

    public function applyFixture($fixture)
    {
        if (!is_array($fixture)) {
            return $this->failed(array('fixture_must_be_array'), $this->emptyDiagnostics('', ''));
        }

        $source = $this->readString($fixture, 'source');

        if ($source === '') {
            $source = 'local_dump_db_readonly';
        }

        $fixtureType = $this->readString($fixture, 'fixture_type');

        if (!isset($fixture['proposals']) || !is_array($fixture['proposals'])) {
            return $this->failed(
                array('fixture_proposals_must_be_array'),
                $this->emptyDiagnostics($source, $fixtureType)
            );
        }

        $errors = array();
        $warnings = array();
        $proposals = array();
        $reviewActions = array();
        $skippedEmptyActionsCount = 0;
        $missingReviewBlockCount = 0;
        $missingProposalIdCount = 0;

        foreach ($fixture['proposals'] as $row) {
            if (!is_array($row)) {
                $warnings[] = 'proposal_row_must_be_array';
                continue;
            }

            $proposal = $row;
            unset($proposal['review']);
            $proposals[] = $proposal;

            $proposalId = $this->readString($row, 'proposal_id');
            $review = isset($row['review']) && is_array($row['review']) ? $row['review'] : null;

            if ($proposalId === '') {
                $missingProposalIdCount++;
                $warnings[] = 'proposal_id_missing';
            }

            if ($review === null) {
                $missingReviewBlockCount++;
                $skippedEmptyActionsCount++;
                continue;
            }

            $action = $this->readString($review, 'action');

            if ($action === '') {
                $skippedEmptyActionsCount++;
                continue;
            }

            if ($proposalId === '') {
                continue;
            }

            $reviewActions[] = array(
                'proposal_id' => $proposalId,
                'action' => $action,
                'reviewer' => $this->readString($review, 'reviewer'),
                'review_note' => $this->readString($review, 'review_note'),
                'source' => $this->resolveActionSource($review, $row, $source),
            );
        }

        $diagnostics = array(
            'source' => $source,
            'fixture_type' => $fixtureType,
            'proposals_count' => count($proposals),
            'review_actions_count' => count($reviewActions),
            'skipped_empty_actions_count' => $skippedEmptyActionsCount,
            'missing_review_block_count' => $missingReviewBlockCount,
            'missing_proposal_id_count' => $missingProposalIdCount,
            'bridge_mode' => 'standalone_local_fixture_bridge',
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
        );

        $result = $this->approvalFlow->apply($proposals, $reviewActions);

        if (!isset($result['errors']) || !is_array($result['errors'])) {
            $result['errors'] = array();
        }

        if (!isset($result['warnings']) || !is_array($result['warnings'])) {
            $result['warnings'] = array();
        }

        $result['errors'] = array_merge($errors, $result['errors']);
        $result['warnings'] = array_merge($warnings, $result['warnings']);
        $result['bridge_diagnostics'] = $diagnostics;

        return $result;
    }

    private function resolveActionSource($review, $proposal, $fixtureSource)
    {
        $reviewSource = $this->readString($review, 'source');

        if ($reviewSource !== '') {
            return $reviewSource;
        }

        $proposalSource = $this->readString($proposal, 'source');

        if ($proposalSource !== '') {
            return $proposalSource;
        }

        return $fixtureSource;
    }

    private function emptyDiagnostics($source, $fixtureType)
    {
        if ($source === '') {
            $source = 'local_dump_db_readonly';
        }

        return array(
            'source' => $source,
            'fixture_type' => $fixtureType,
            'proposals_count' => 0,
            'review_actions_count' => 0,
            'skipped_empty_actions_count' => 0,
            'missing_review_block_count' => 0,
            'missing_proposal_id_count' => 0,
            'bridge_mode' => 'standalone_local_fixture_bridge',
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
        );
    }

    private function failed($errors, $diagnostics)
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
            'bridge_diagnostics' => $diagnostics,
        );
    }

    private function readString($array, $key)
    {
        return isset($array[$key]) ? (string)$array[$key] : '';
    }
}
