<?php

namespace FrameworkStandardization\Approval;

final class DbReadOnlyLocalReviewFixtureGenerator
{
    public function generate($parserOutput)
    {
        if (!is_array($parserOutput)) {
            return $this->failed(array('parser_output_must_be_array'), array(), 'local_dump_db_readonly');
        }

        $source = $this->readString($parserOutput, 'source');

        if ($source === '') {
            $source = 'local_dump_db_readonly';
        }

        if (!isset($parserOutput['normalization_value_proposals']) || !is_array($parserOutput['normalization_value_proposals'])) {
            return $this->failed(
                array('normalization_value_proposals_must_be_array'),
                array(),
                $source
            );
        }

        $errors = array();
        $warnings = array();
        $proposalRows = array();
        $reviewBlocksCreatedCount = 0;
        $unsafeInputApprovalStatusCount = 0;

        foreach ($parserOutput['normalization_value_proposals'] as $proposal) {
            if (!is_array($proposal)) {
                $warnings[] = 'proposal_must_be_array';
                continue;
            }

            $approvalStatus = $this->readString($proposal, 'approval_status');

            if ($approvalStatus === '') {
                $approvalStatus = 'unknown';
            }

            if ($approvalStatus === 'approved' || $approvalStatus === 'rejected') {
                $unsafeInputApprovalStatusCount++;
                $warnings[] = 'unsafe_input_approval_status:' . $approvalStatus;
            }

            $proposalSource = $this->readString($proposal, 'source');

            if ($proposalSource === '') {
                $proposalSource = $source;
            }

            $proposalRows[] = array(
                'proposal_id' => $this->readString($proposal, 'proposal_id'),
                'product_id' => $this->readValue($proposal, 'product_id'),
                'attribute_id' => $this->readValue($proposal, 'attribute_id'),
                'target_attribute_id' => $this->readValue($proposal, 'target_attribute_id'),
                'original_raw_value' => $this->readString($proposal, 'original_raw_value'),
                'parsed_value' => $this->readArray($proposal, 'parsed_value'),
                'proposed_normalized_value' => $this->readString($proposal, 'proposed_normalized_value'),
                'proposed_unit' => $this->readString($proposal, 'proposed_unit'),
                'parser_confidence' => $this->readString($proposal, 'parser_confidence'),
                'parser_warnings' => $this->readArray($proposal, 'parser_warnings'),
                'approval_status' => $approvalStatus,
                'source' => $proposalSource,
                'review' => array(
                    'action' => '',
                    'reviewer' => '',
                    'review_note' => '',
                ),
            );

            $reviewBlocksCreatedCount++;
        }

        return array(
            'source' => $source,
            'fixture_type' => 'db_readonly_normalization_review',
            'generated_at' => gmdate('c'),
            'generator_mode' => 'standalone_local_review_fixture_generation',
            'proposals' => $proposalRows,
            'generator_diagnostics' => $this->buildDiagnostics(
                $source,
                count($proposalRows),
                $reviewBlocksCreatedCount,
                $unsafeInputApprovalStatusCount
            ),
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }

    private function failed($errors, $warnings, $source)
    {
        return array(
            'source' => $source,
            'fixture_type' => 'db_readonly_normalization_review',
            'generated_at' => gmdate('c'),
            'generator_mode' => 'standalone_local_review_fixture_generation',
            'proposals' => array(),
            'generator_diagnostics' => $this->buildDiagnostics($source, 0, 0, 0),
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }

    private function buildDiagnostics($source, $proposalsCount, $reviewBlocksCreatedCount, $unsafeInputApprovalStatusCount)
    {
        return array(
            'source' => $source,
            'proposals_count' => (int)$proposalsCount,
            'review_blocks_created_count' => (int)$reviewBlocksCreatedCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'unsafe_input_approval_status_count' => (int)$unsafeInputApprovalStatusCount,
            'generator_mode' => 'standalone_local_review_fixture_generation',
            'writes_files' => 0,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
        );
    }

    private function readString($array, $key)
    {
        return isset($array[$key]) ? (string)$array[$key] : '';
    }

    private function readArray($array, $key)
    {
        return isset($array[$key]) && is_array($array[$key]) ? $array[$key] : array();
    }

    private function readValue($array, $key)
    {
        return isset($array[$key]) ? $array[$key] : null;
    }
}
