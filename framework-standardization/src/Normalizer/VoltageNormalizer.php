<?php

namespace FrameworkStandardization\Normalizer;

final class VoltageNormalizer
{
    public function normalize($rawValue)
    {
        $original = trim((string) $rawValue);

        if ($original === '') {
            return $this->result('invalid', 'invalid', null, array('empty_value'), 'empty_value', $this->emptyMetadata($original));
        }

        $value = $this->normalizeText($original);
        $analysis = $this->analyze($original, $value);

        if (count($analysis['evidence']) === 0) {
            return $this->result(
                'unsupported',
                'unsupported',
                null,
                array('unsupported_voltage_value'),
                'unsupported_voltage_value',
                $this->buildMetadata($original, $analysis)
            );
        }

        if (count($analysis['outside_policy_voltages']) > 0) {
            return $this->result(
                'review_required',
                $this->valueType($analysis),
                null,
                $this->warningsForReason($analysis, 'voltage_outside_allowed_classes'),
                'voltage_outside_allowed_classes',
                $this->buildMetadata($original, $analysis)
            );
        }

        if (count($analysis['detected_classes']) > 1) {
            return $this->result(
                'review_required',
                $this->valueType($analysis),
                null,
                $this->warningsForReason($analysis, 'mixed_voltage_classes'),
                'mixed_voltage_classes',
                $this->buildMetadata($original, $analysis)
            );
        }

        $class = count($analysis['detected_classes']) === 1 ? $analysis['detected_classes'][0] : null;

        if ($class === null) {
            return $this->result(
                'unsupported',
                'unsupported',
                null,
                array('unsupported_voltage_value'),
                'unsupported_voltage_value',
                $this->buildMetadata($original, $analysis)
            );
        }

        if ($this->hasPhaseVoltageClassConflict($analysis, $class)) {
            return $this->result(
                'review_required',
                $this->valueType($analysis),
                null,
                $this->warningsForReason($analysis, 'phase_voltage_class_conflict'),
                'phase_voltage_class_conflict',
                $this->buildMetadata($original, $analysis)
            );
        }

        return $this->result(
            'normalized',
            $this->valueType($analysis),
            $class,
            array(),
            '',
            $this->buildMetadata($original, $analysis)
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

    private function analyze($original, $value)
    {
        $analysis = array(
            'evidence' => array(),
            'detected_single_voltages' => array(),
            'detected_ranges' => array(),
            'detected_classes' => array(),
            'outside_policy_voltages' => array(),
            'phase_count' => null,
            'phase_label' => null,
            'frequency_values' => array(),
        );

        $this->detectFrequency($value, $analysis);
        $this->detectPhase($value, $analysis);

        $scan = $this->removeFrequencyTokens($value);
        $this->detectRanges($scan, $analysis);
        $this->detectSingles($this->removeRangeTokens($scan), $analysis);

        return $analysis;
    }

    private function detectFrequency($value, array &$analysis)
    {
        if (preg_match_all('/\b(50|60)\s*(?:гц|hz)\b/iu', $value, $matches)) {
            foreach ($matches[1] as $frequency) {
                $this->appendUnique($analysis['frequency_values'], (string) $frequency);
            }
        }
    }

    private function detectPhase($value, array &$analysis)
    {
        $phaseCount = null;

        if (preg_match('/\b([13])\s*(?:x|~)\s*/iu', $value, $matches)) {
            $phaseCount = (int) $matches[1];
        } elseif (preg_match('/\b([13])\s*фаз/iu', $value, $matches)) {
            $phaseCount = (int) $matches[1];
        } elseif (preg_match('/однофаз/iu', $value)) {
            $phaseCount = 1;
        } elseif (preg_match('/тр(?:е|ё)хфаз/iu', $value) || preg_match('/тр.*фаз/iu', $value)) {
            $phaseCount = 3;
        } elseif (preg_match('/^\s*([13])\s+[0-9]{3}/u', $value, $matches)) {
            $phaseCount = (int) $matches[1];
        }

        if ($phaseCount === 1 || $phaseCount === 3) {
            $analysis['phase_count'] = $phaseCount;
            $analysis['phase_label'] = $phaseCount === 1 ? 'single_phase' : 'three_phase';
        }
    }

    private function removeFrequencyTokens($value)
    {
        return preg_replace('/\b(?:50|60)\s*(?:гц|hz)\b/iu', ' ', $value);
    }

    private function detectRanges($value, array &$analysis)
    {
        if (preg_match_all('/\b([0-9]{2,4})\s*(?:-|\.\.)\s*([0-9]{2,4})\s*(?:v)?\b/iu', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addRange($match[1], $match[2], $analysis);
            }
        }

        if (preg_match_all('/\b(200240|210240|220230|220240|380400|380420)\s*(?:v)?\b/iu', $value, $matches)) {
            foreach ($matches[1] as $compactRange) {
                $this->addRange(substr($compactRange, 0, 3), substr($compactRange, 3, 3), $analysis);
            }
        }
    }

    private function removeRangeTokens($value)
    {
        $value = preg_replace('/\b[0-9]{2,4}\s*(?:-|\.\.)\s*[0-9]{2,4}\s*(?:v)?\b/iu', ' ', $value);
        $value = preg_replace('/\b(?:200240|210240|220230|220240|380400|380420)\s*(?:v)?\b/iu', ' ', $value);

        return $value;
    }

    private function detectSingles($value, array &$analysis)
    {
        if (preg_match_all('/\b([13])\s*(?:x|~)?\s*(220|230|380|400)\s*(?:v)?\b/iu', $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addSingle($match[2], $analysis);
            }
        }

        if (preg_match_all('/\b(1220|1230|3380|3400)\s*(?:v)?\b/iu', $value, $matches)) {
            foreach ($matches[1] as $compactSingle) {
                $this->addSingle(substr($compactSingle, 1, 3), $analysis);
            }
        }

        if (preg_match_all('/\b(110|127|200|210|220|230|240|380|400|420|480)\s*(?:v)?\b/iu', $value, $matches)) {
            foreach ($matches[1] as $single) {
                $this->addSingle($single, $analysis);
            }
        }
    }

    private function addSingle($value, array &$analysis)
    {
        $value = (string) (int) $value;
        $class = $this->classifySingle($value);
        $analysis['evidence'][] = array('type' => 'single', 'value' => $value, 'class' => $class);
        $this->appendUnique($analysis['detected_single_voltages'], $value);

        if ($class === null) {
            $this->appendUnique($analysis['outside_policy_voltages'], $value);
            return;
        }

        $this->appendUnique($analysis['detected_classes'], $class);
    }

    private function addRange($left, $right, array &$analysis)
    {
        $min = (int) $left;
        $max = (int) $right;

        if ($min > $max) {
            $tmp = $min;
            $min = $max;
            $max = $tmp;
        }

        $range = array('min' => (string) $min, 'max' => (string) $max);
        $class = $this->classifyRange($min, $max);
        $range['class'] = $class;
        $analysis['evidence'][] = array('type' => 'range', 'min' => $range['min'], 'max' => $range['max'], 'class' => $class);
        $this->appendRangeUnique($analysis['detected_ranges'], $range);

        if ($class === null) {
            $this->appendUnique($analysis['outside_policy_voltages'], $range['min']);
            $this->appendUnique($analysis['outside_policy_voltages'], $range['max']);
            return;
        }

        $this->appendUnique($analysis['detected_classes'], $class);
    }

    private function classifySingle($value)
    {
        $value = (int) $value;

        if ($value === 220 || $value === 230) {
            return '220';
        }

        if ($value === 380 || $value === 400) {
            return '380';
        }

        return null;
    }

    private function classifyRange($min, $max)
    {
        $key = (int) $min . '-' . (int) $max;

        if (in_array($key, array('200-240', '210-240', '220-230', '220-240'), true)) {
            return '220';
        }

        if (in_array($key, array('380-400', '380-420'), true)) {
            return '380';
        }

        return null;
    }

    private function valueType(array $analysis)
    {
        if (count($analysis['detected_ranges']) > 0 && ($analysis['phase_count'] !== null || count($analysis['frequency_values']) > 0 || count($analysis['evidence']) > 1)) {
            return 'compound';
        }

        if (count($analysis['detected_ranges']) > 0) {
            return 'range';
        }

        if ($analysis['phase_count'] !== null || count($analysis['frequency_values']) > 0 || count($analysis['evidence']) > 1) {
            return 'compound';
        }

        return 'single';
    }

    private function hasPhaseVoltageClassConflict(array $analysis, $class)
    {
        if ($analysis['phase_count'] === 1 && $class === '380') {
            return true;
        }

        if ($analysis['phase_count'] === 3 && $class === '220') {
            return true;
        }

        return false;
    }

    private function warningsForReason(array $analysis, $primaryReason)
    {
        $warnings = array();

        if (count($analysis['outside_policy_voltages']) > 0) {
            $warnings[] = 'voltage_outside_allowed_classes';
        }

        if (count($analysis['detected_classes']) > 1) {
            $warnings[] = 'mixed_voltage_classes';
        }

        $class = count($analysis['detected_classes']) === 1 ? $analysis['detected_classes'][0] : null;

        if ($class !== null && $this->hasPhaseVoltageClassConflict($analysis, $class)) {
            $warnings[] = 'phase_voltage_class_conflict';
        }

        if (!in_array($primaryReason, $warnings, true)) {
            $warnings[] = $primaryReason;
        }

        return $warnings;
    }

    private function result($status, $valueType, $canonicalValue, array $warnings, $ambiguityReason, array $metadata)
    {
        $result = array(
            'status' => $status,
            'value_type' => $valueType,
            'canonical_value' => $canonicalValue,
            'unit' => 'V',
            'warnings' => $warnings,
            'ambiguity_reason' => $ambiguityReason,
            'metadata' => $metadata,
        );

        if ($metadata['phase_count'] !== null) {
            $result['phase_count'] = $metadata['phase_count'];
            $result['phase_label'] = $metadata['phase_label'];
        }

        return $result;
    }

    private function buildMetadata($original, array $analysis)
    {
        return array(
            'raw_value' => $original,
            'detected_single_voltages' => $analysis['detected_single_voltages'],
            'detected_ranges' => $analysis['detected_ranges'],
            'detected_classes' => $analysis['detected_classes'],
            'outside_policy_voltages' => $analysis['outside_policy_voltages'],
            'phase_count' => $analysis['phase_count'],
            'phase_label' => $analysis['phase_label'],
            'frequency_values' => $analysis['frequency_values'],
        );
    }

    private function emptyMetadata($original)
    {
        return array(
            'raw_value' => $original,
            'detected_single_voltages' => array(),
            'detected_ranges' => array(),
            'detected_classes' => array(),
            'outside_policy_voltages' => array(),
            'phase_count' => null,
            'phase_label' => null,
            'frequency_values' => array(),
        );
    }

    private function appendUnique(array &$values, $value)
    {
        $value = (string) $value;

        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }
    }

    private function appendRangeUnique(array &$ranges, array $range)
    {
        foreach ($ranges as $existing) {
            if ($existing['min'] === $range['min'] && $existing['max'] === $range['max']) {
                return;
            }
        }

        $ranges[] = $range;
    }
}
