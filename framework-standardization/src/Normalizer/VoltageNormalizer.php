<?php

namespace FrameworkStandardization\Normalizer;

final class VoltageNormalizer
{
    public function normalize($rawValue)
    {
        $original = trim((string) $rawValue);

        if ($original === '') {
            return array(
                'status' => 'invalid',
                'value_type' => 'invalid',
                'canonical_value' => null,
                'unit' => 'V',
                'warnings' => array('empty_value'),
                'ambiguity_reason' => 'empty_value',
                'metadata' => array('raw_value' => $original),
            );
        }

        $value = $this->normalizeText($original);
        $compact = preg_replace('/\s+/u', '', $value);
        $warnings = array();

        if ($this->isCompound($value, $compact)) {
            return $this->normalizeCompound($original, $value, $compact);
        }

        $range = $this->extractRange($value, $compact);

        if ($range !== null) {
            return array(
                'status' => 'normalized',
                'value_type' => 'range',
                'range_min' => $range[0],
                'range_max' => $range[1],
                'canonical_value' => $range[0] . '-' . $range[1],
                'unit' => 'V',
                'warnings' => $warnings,
                'ambiguity_reason' => '',
                'metadata' => array('raw_value' => $original),
            );
        }

        if (preg_match('/^([0-9]{2,4})(?:v)?$/iu', $compact, $matches)) {
            return array(
                'status' => 'normalized',
                'value_type' => 'single',
                'canonical_value' => $this->normalizeInteger($matches[1]),
                'unit' => 'V',
                'warnings' => $warnings,
                'ambiguity_reason' => '',
                'metadata' => array('raw_value' => $original),
            );
        }

        return array(
            'status' => 'unsupported',
            'value_type' => 'unsupported',
            'canonical_value' => null,
            'unit' => 'V',
            'warnings' => array('unsupported_voltage_value'),
            'ambiguity_reason' => 'unsupported_voltage_value',
            'metadata' => array('raw_value' => $original),
        );
    }

    private function normalizeText($value)
    {
        $value = trim((string) $value);
        $value = str_replace(array("\xC2\xA0", 'В', 'в'), array(' ', 'V', 'v'), $value);
        $value = str_replace(array('–', '—', '−'), '-', $value);
        $value = str_replace(array('×', 'х', 'Х'), 'x', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function isCompound($value, $compact)
    {
        if (preg_match('/\b[123]\s*x\s*/iu', $value)) {
            return true;
        }

        if (preg_match('/однофаз/ui', $value)) {
            return true;
        }

        if (preg_match('/\b[123]\s*фаз/ui', $value)) {
            return true;
        }

        if (preg_match('/\b[123]\s*~/u', $value)) {
            return true;
        }

        if (preg_match('/^[123](?:[0-9]{3}|[0-9]{6})v?$/iu', $compact)) {
            return true;
        }

        return false;
    }

    private function normalizeCompound($original, $value, $compact)
    {
        $phaseCount = null;
        $phaseLabel = null;

        if (preg_match('/\b([123])\s*x\s*/iu', $value, $phaseMatches)) {
            $phaseCount = (int) $phaseMatches[1];
        } elseif (preg_match('/\b([123])\s*фаз/ui', $value, $phaseMatches)) {
            $phaseCount = (int) $phaseMatches[1];
        } elseif (preg_match('/\b([123])\s*~/u', $value, $phaseMatches)) {
            $phaseCount = (int) $phaseMatches[1];
        } elseif (preg_match('/^([123])([0-9]{3}|[0-9]{6})v?$/iu', $compact, $phaseMatches)) {
            $phaseCount = (int) $phaseMatches[1];
        } elseif (preg_match('/однофаз/ui', $value)) {
            $phaseCount = 1;
        }

        if ($phaseCount === 1) {
            $phaseLabel = 'single_phase';
        } elseif ($phaseCount === 3) {
            $phaseLabel = 'three_phase';
        }

        $withoutPhase = $value;
        $withoutPhase = preg_replace('/\b[123]\s*x\s*/iu', '', $withoutPhase);
        $withoutPhase = preg_replace('/\b[123]\s*~/u', '', $withoutPhase);
        $withoutPhase = preg_replace('/\([^)]*\)/u', '', $withoutPhase);

        if (preg_match('/^([123])([0-9]{3}|[0-9]{6})v?$/iu', $compact, $phaseMatches)) {
            $withoutPhase = $phaseMatches[2];
        }

        $range = $this->extractRange($withoutPhase, preg_replace('/\s+/u', '', $withoutPhase));
        $single = null;

        if ($range === null && preg_match('/([0-9]{2,4})/u', $withoutPhase, $singleMatches)) {
            $single = $this->normalizeInteger($singleMatches[1]);
        }

        return array(
            'status' => 'review_required',
            'value_type' => 'compound',
            'canonical_value' => null,
            'unit' => 'V',
            'range_min' => $range === null ? null : $range[0],
            'range_max' => $range === null ? null : $range[1],
            'single_value' => $single,
            'phase_count' => $phaseCount,
            'phase_label' => $phaseLabel,
            'warnings' => array('compound_voltage_value_requires_review'),
            'ambiguity_reason' => 'compound_voltage_value',
            'metadata' => array('raw_value' => $original),
        );
    }

    private function extractRange($value, $compact)
    {
        if (preg_match('/([0-9]{2,4})\s*(?:-|\.\.)\s*([0-9]{2,4})\s*(?:v)?/iu', $value, $matches)) {
            return $this->buildRange($matches[1], $matches[2]);
        }

        if (preg_match('/^([0-9]{3})([0-9]{3})(?:v)?$/iu', $compact, $matches)) {
            return $this->buildRange($matches[1], $matches[2]);
        }

        return null;
    }

    private function buildRange($left, $right)
    {
        $min = (int) $left;
        $max = (int) $right;

        if ($min > $max) {
            $tmp = $min;
            $min = $max;
            $max = $tmp;
        }

        return array((string) $min, (string) $max);
    }

    private function normalizeInteger($value)
    {
        return (string) (int) $value;
    }
}
