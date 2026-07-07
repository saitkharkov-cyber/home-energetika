<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyRealDataReviewChainUsageChecker
{
    const MAX_ROWS = 5;

    public function run($readonlyInput)
    {
        $errors = array();
        $warnings = array();
        $source = 'local_dump_db_readonly';
        $parserOutput = null;
        $e2eResult = null;

        if (!is_array($readonlyInput)) {
            $errors[] = 'readonly_input_must_be_array';
            return $this->createResult(0, $this->buildDiagnostics(0, 0, 0, 0, 0), array(), $errors, $warnings, $source);
        }

        if (isset($readonlyInput['source']) && $readonlyInput['source'] !== '') {
            $source = (string)$readonlyInput['source'];
        }

        if ($this->containsForbiddenInputReference($readonlyInput)) {
            $errors[] = 'filenames_paths_urls_not_allowed';
            return $this->createResult(0, $this->buildDiagnostics(0, 0, 0, 0, 0), array(), $errors, $warnings, $source);
        }

        if (!isset($readonlyInput['rows']) || !is_array($readonlyInput['rows']) || count($readonlyInput['rows']) === 0) {
            $errors[] = 'readonly_rows_required';
            return $this->createResult(0, $this->buildDiagnostics(0, 0, 0, 0, 0), array(), $errors, $warnings, $source);
        }

        $inputRowsCount = count($readonlyInput['rows']);
        if ($inputRowsCount > self::MAX_ROWS) {
            $errors[] = 'readonly_rows_limit_exceeded';
            return $this->createResult(0, $this->buildDiagnostics($inputRowsCount, 0, 0, 0, 0), array(), $errors, $warnings, $source);
        }

        foreach ($readonlyInput['rows'] as $row) {
            if (!is_array($row)) {
                $errors[] = 'readonly_row_must_be_array';
            }
        }

        if (count($errors) > 0) {
            return $this->createResult(0, $this->buildDiagnostics($inputRowsCount, 0, 0, 0, 0), array(), $errors, $warnings, $source);
        }

        $parserOutput = $this->buildParserLikeOutput($readonlyInput['rows'], $source);
        $e2eChecker = new DbReadOnlyStandaloneReviewChainE2EChecker();
        $e2eResult = $e2eChecker->run($parserOutput);
        $this->mergeMessages($e2eResult, $errors, $warnings);

        $e2eChecked = $this->readInt($e2eResult, 'checked');
        $reviewReady = $e2eChecked === 1 && count($errors) === 0 ? 1 : 0;
        $used = $reviewReady === 1 ? 1 : 0;

        return $this->createResult(
            $used,
            $this->buildDiagnostics($inputRowsCount, 1, 1, $e2eChecked, $reviewReady),
            $e2eResult,
            $errors,
            $warnings,
            $source
        );
    }

    private function buildParserLikeOutput($rows, $source)
    {
        $proposals = array();
        $index = 0;

        foreach ($rows as $row) {
            $index++;
            $rawValue = $this->readStringWithFallback($row, 'raw_value', 'value', '');
            $normalizedValue = $this->readStringWithFallback($row, 'normalized_value', 'proposed_normalized_value', $rawValue);
            $attributeId = $this->readValue($row, 'attribute_id', null);
            $targetAttributeId = $this->readValue($row, 'target_attribute_id', $attributeId);
            $approvalStatus = $this->safeApprovalStatus($row, $rawValue);
            $rowSource = $this->readString($row, 'source');

            if ($rowSource === '') {
                $rowSource = $source;
            }

            $proposals[] = array(
                'proposal_id' => $this->readStringWithFallback($row, 'proposal_id', 'id', 'real_data_row_' . $index),
                'product_id' => $this->readValue($row, 'product_id', null),
                'attribute_id' => $attributeId,
                'target_attribute_id' => $targetAttributeId,
                'original_raw_value' => $rawValue,
                'parsed_value' => $this->readParsedValue($row, $rawValue),
                'proposed_normalized_value' => $normalizedValue,
                'proposed_unit' => $this->readStringWithFallback($row, 'proposed_unit', 'unit', ''),
                'parser_confidence' => $this->readStringWithFallback($row, 'confidence', 'parser_confidence', 'unknown'),
                'parser_warnings' => $this->readWarnings($row, $rawValue),
                'approval_status' => $approvalStatus,
                'source' => $rowSource,
            );
        }

        return array(
            'source' => $source,
            'normalization_value_proposals' => $proposals,
        );
    }

    private function safeApprovalStatus($row, $rawValue)
    {
        $status = $this->readString($row, 'approval_status');
        $allowed = array('proposed', 'needs_review', 'unknown');

        if (in_array($status, $allowed, true)) {
            return $status;
        }

        if ((string)$rawValue === '') {
            return 'unknown';
        }

        return 'proposed';
    }

    private function readParsedValue($row, $rawValue)
    {
        if (isset($row['parsed_value']) && is_array($row['parsed_value'])) {
            return $row['parsed_value'];
        }

        return array(
            'raw_value' => (string)$rawValue,
        );
    }

    private function readWarnings($row, $rawValue)
    {
        if (isset($row['parser_warnings']) && is_array($row['parser_warnings'])) {
            return $row['parser_warnings'];
        }

        if (isset($row['warnings']) && is_array($row['warnings'])) {
            return $row['warnings'];
        }

        if ((string)$rawValue === '') {
            return array('raw_value_missing');
        }

        return array();
    }

    private function containsForbiddenInputReference($input)
    {
        $forbiddenKeys = array('filename', 'file_name', 'filepath', 'file_path', 'path', 'url', 'uri');

        foreach ($forbiddenKeys as $key) {
            if (array_key_exists($key, $input)) {
                return true;
            }
        }

        if (isset($input['rows']) && is_array($input['rows'])) {
            foreach ($input['rows'] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                foreach ($forbiddenKeys as $key) {
                    if (array_key_exists($key, $row)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function buildDiagnostics($inputRowsCount, $parserLikeOutputCreated, $e2eCheckerCalled, $e2eChecked, $reviewReady)
    {
        return array(
            'checker_mode' => 'standalone_real_data_review_chain_usage_checker',
            'usage_mode' => 'controlled_readonly_real_data_like_scenario',
            'input_mode' => 'local_readonly_snapshot_or_fixture_array',
            'input_rows_count' => (int)$inputRowsCount,
            'parser_like_output_created' => (int)$parserLikeOutputCreated,
            'e2e_checker_called' => (int)$e2eCheckerCalled,
            'e2e_checked' => (int)$e2eChecked,
            'review_ready' => (int)$reviewReady,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
        );
    }

    private function createResult($used, $usageDiagnostics, $e2eResult, $errors, $warnings, $source)
    {
        return array(
            'used' => (int)$used,
            'usage_diagnostics' => $usageDiagnostics,
            'e2e_result' => $e2eResult,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $source,
        );
    }

    private function mergeMessages($result, &$errors, &$warnings)
    {
        if (!is_array($result)) {
            return;
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $errors[] = $error;
            }
        }

        if (isset($result['warnings']) && is_array($result['warnings'])) {
            foreach ($result['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }
    }

    private function readString($array, $key)
    {
        return isset($array[$key]) ? (string)$array[$key] : '';
    }

    private function readStringWithFallback($array, $primaryKey, $fallbackKey, $default)
    {
        if (isset($array[$primaryKey])) {
            return (string)$array[$primaryKey];
        }

        if (isset($array[$fallbackKey])) {
            return (string)$array[$fallbackKey];
        }

        return (string)$default;
    }

    private function readValue($array, $key, $default)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    private function readInt($array, $key)
    {
        if (!is_array($array) || !isset($array[$key])) {
            return 0;
        }

        return (int)$array[$key];
    }
}
