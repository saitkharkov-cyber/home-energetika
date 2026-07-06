<?php

namespace FrameworkStandardization\Normalizer;

final class DbReadOnlyNormalizationProposalParser
{
    public function parse($rawValues)
    {
        if (!is_array($rawValues)) {
            return $this->failed(array('raw_values_must_be_array'));
        }

        $proposals = array();
        $diagnostics = $this->emptyDiagnostics(count($rawValues));

        foreach ($rawValues as $rawValue) {
            if (!is_array($rawValue)) {
                $rawValue = array();
            }

            $proposal = $this->buildProposal($rawValue);
            $proposals[] = $proposal;
            $this->applyProposalDiagnostics($diagnostics, $proposal);
        }

        $diagnostics['proposal_count'] = count($proposals);

        return array(
            'normalization_value_proposals' => $proposals,
            'parser_diagnostics' => $diagnostics,
            'errors' => array(),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }

    private function buildProposal(array $rawValue)
    {
        $rawText = $this->resolveRawText($rawValue);
        $trimmedRawText = trim($rawText);
        $numberFragments = $this->findNumberFragments($rawText);
        $rangeDetected = $this->isRange($rawText, $numberFragments);
        $unitMarker = $this->resolveUnitMarker($rawText);
        $parserWarnings = array();
        $approvalStatus = 'unknown';
        $parserConfidence = 'low';
        $proposedNormalizedValue = '';
        $proposedUnit = '';

        if ($trimmedRawText === '') {
            $parserWarnings[] = 'empty_raw_value';
        } elseif ($rangeDetected) {
            $approvalStatus = 'needs_review';
            $parserWarnings[] = 'range_detected';
        } elseif (count($numberFragments) > 1) {
            $approvalStatus = 'needs_review';
            $parserWarnings[] = 'multiple_numbers_detected';
        } elseif (count($numberFragments) === 1) {
            $approvalStatus = 'proposed';
            $parserConfidence = $unitMarker === '' ? 'medium' : 'high';
            $proposedNormalizedValue = $this->normalizeNumberText($numberFragments[0]);
            $proposedUnit = $unitMarker === '' ? '' : 'mm';

            if ($unitMarker === '') {
                $parserWarnings[] = 'unit_missing';
            }
        } else {
            $parserWarnings[] = 'no_number_detected';
        }

        if ($approvalStatus === 'needs_review') {
            $parserConfidence = 'low';

            if ($unitMarker === '' && count($numberFragments) > 0) {
                $parserWarnings[] = 'unit_missing';
            }
        }

        return array(
            'proposal_id' => $this->buildProposalId($rawValue, $rawText),
            'product_id' => $this->readInt($rawValue, 'product_id'),
            'attribute_id' => $this->readInt($rawValue, 'attribute_id'),
            'language_id' => $this->readInt($rawValue, 'language_id'),
            'target_attribute_id' => $this->readInt($rawValue, 'target_attribute_id'),
            'original_raw_value' => $rawText,
            'parsed_value' => array(
                'number_fragments' => $numberFragments,
                'number_fragment_count' => count($numberFragments),
                'unit_marker' => $unitMarker,
                'range_detected' => $rangeDetected ? 1 : 0,
                'decimal_separator' => $this->resolveDecimalSeparator($numberFragments),
            ),
            'proposed_normalized_value' => $proposedNormalizedValue,
            'proposed_unit' => $proposedUnit,
            'parser_confidence' => $parserConfidence,
            'parser_warnings' => $this->uniqueValues($parserWarnings),
            'approval_status' => $approvalStatus,
            'source' => 'local_dump_db_readonly',
        );
    }

    private function resolveRawText(array $rawValue)
    {
        if (isset($rawValue['raw_text'])) {
            return (string)$rawValue['raw_text'];
        }

        if (isset($rawValue['value'])) {
            return (string)$rawValue['value'];
        }

        return '';
    }

    private function findNumberFragments($rawText)
    {
        $matches = array();
        preg_match_all('/[0-9]+(?:[.,][0-9]+)?/', (string)$rawText, $matches);

        return isset($matches[0]) && is_array($matches[0]) ? $matches[0] : array();
    }

    private function isRange($rawText, array $numberFragments)
    {
        if (count($numberFragments) < 2) {
            return false;
        }

        $text = (string)$rawText;

        if (preg_match('/[0-9]+(?:[.,][0-9]+)?\s*[-\/]\s*[0-9]+(?:[.,][0-9]+)?/', $text) === 1) {
            return true;
        }

        if (strpos($text, '–') !== false || strpos($text, '—') !== false) {
            return true;
        }

        if (preg_match('/от\s+[0-9]+(?:[.,][0-9]+)?\s+до\s+[0-9]+(?:[.,][0-9]+)?/ui', $text) === 1) {
            return true;
        }

        return false;
    }

    private function resolveUnitMarker($rawText)
    {
        $text = (string)$rawText;

        if (stripos($text, 'mm') !== false) {
            return 'mm';
        }

        if (
            strpos($text, 'мм') !== false ||
            strpos($text, 'ММ') !== false ||
            strpos($text, 'Мм') !== false ||
            strpos($text, 'мМ') !== false
        ) {
            return 'мм';
        }

        return '';
    }

    private function normalizeNumberText($numberText)
    {
        return str_replace(',', '.', (string)$numberText);
    }

    private function resolveDecimalSeparator(array $numberFragments)
    {
        foreach ($numberFragments as $numberFragment) {
            if (strpos($numberFragment, ',') !== false) {
                return 'comma';
            }

            if (strpos($numberFragment, '.') !== false) {
                return 'dot';
            }
        }

        return '';
    }

    private function buildProposalId(array $rawValue, $rawText)
    {
        $parts = array(
            $this->readInt($rawValue, 'product_id'),
            $this->readInt($rawValue, 'attribute_id'),
            $this->readInt($rawValue, 'language_id'),
            $this->readInt($rawValue, 'target_attribute_id'),
            (string)$rawText,
        );

        return 'proposal_' . md5(implode('|', $parts));
    }

    private function readInt(array $array, $key)
    {
        return isset($array[$key]) ? (int)$array[$key] : 0;
    }

    private function emptyDiagnostics($totalRawValues)
    {
        return array(
            'total_raw_values' => (int)$totalRawValues,
            'proposal_count' => 0,
            'proposed_count' => 0,
            'needs_review_count' => 0,
            'unknown_count' => 0,
            'rejected_count' => 0,
            'approved_count' => 0,
            'range_detected_count' => 0,
            'multiple_numbers_count' => 0,
            'unit_missing_count' => 0,
            'low_confidence_count' => 0,
            'source' => 'local_dump_db_readonly',
        );
    }

    private function applyProposalDiagnostics(array &$diagnostics, array $proposal)
    {
        $status = isset($proposal['approval_status']) ? $proposal['approval_status'] : 'unknown';
        $warnings = isset($proposal['parser_warnings']) && is_array($proposal['parser_warnings']) ? $proposal['parser_warnings'] : array();
        $parsedValue = isset($proposal['parsed_value']) && is_array($proposal['parsed_value']) ? $proposal['parsed_value'] : array();

        if ($status === 'proposed') {
            $diagnostics['proposed_count']++;
        } elseif ($status === 'needs_review') {
            $diagnostics['needs_review_count']++;
        } else {
            $diagnostics['unknown_count']++;
        }

        if (isset($parsedValue['range_detected']) && (int)$parsedValue['range_detected'] === 1) {
            $diagnostics['range_detected_count']++;
        }

        if (
            in_array('multiple_numbers_detected', $warnings, true) ||
            (isset($parsedValue['number_fragment_count']) && (int)$parsedValue['number_fragment_count'] > 1)
        ) {
            $diagnostics['multiple_numbers_count']++;
        }

        if (in_array('unit_missing', $warnings, true)) {
            $diagnostics['unit_missing_count']++;
        }

        if (isset($proposal['parser_confidence']) && $proposal['parser_confidence'] === 'low') {
            $diagnostics['low_confidence_count']++;
        }
    }

    private function uniqueValues(array $values)
    {
        $unique = array();

        foreach ($values as $value) {
            if (!in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }

        return $unique;
    }

    private function failed(array $errors)
    {
        return array(
            'normalization_value_proposals' => array(),
            'parser_diagnostics' => $this->emptyDiagnostics(0),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
        );
    }
}
