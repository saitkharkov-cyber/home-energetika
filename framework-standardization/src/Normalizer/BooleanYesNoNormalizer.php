<?php

namespace FrameworkStandardization\Normalizer;

final class BooleanYesNoNormalizer
{
    public function normalize($rawValue)
    {
        if ($rawValue === null) {
            return $this->result('invalid', null, 'empty_value', array('input_type' => 'NULL'));
        }

        if (is_int($rawValue) || is_float($rawValue) || is_bool($rawValue)) {
            return $this->result('invalid', null, 'non_string_scalar_value', array('input_type' => gettype($rawValue)));
        }

        if (is_array($rawValue) || is_object($rawValue) || is_resource($rawValue)) {
            $metadata = array('input_type' => gettype($rawValue));

            if (is_object($rawValue)) {
                $metadata['class_name'] = get_class($rawValue);
            }

            return $this->result('invalid', null, 'non_scalar_value', $metadata);
        }

        if (!is_string($rawValue)) {
            return $this->result('invalid', null, 'non_scalar_value', array('input_type' => gettype($rawValue)));
        }

        $trimmedValue = $this->trimBoundaryWhitespace($rawValue);
        $metadata = array(
            'original_value' => $rawValue,
            'trimmed_value' => $trimmedValue,
            'boundary_whitespace_changed' => $rawValue !== $trimmedValue,
            'input_type' => 'string',
        );

        if ($trimmedValue === '') {
            return $this->result('invalid', null, 'empty_value', $metadata);
        }

        if ($trimmedValue === 'Да') {
            return $this->result('normalized', 'Да', '', $metadata);
        }

        if ($trimmedValue === 'Нет') {
            return $this->result('normalized', 'Нет', '', $metadata);
        }

        if ($this->containsMixedCanonicalTokens($trimmedValue)) {
            return $this->result('review_required', null, 'mixed_boolean_values', $metadata);
        }

        return $this->result('unsupported', null, 'unsupported_boolean_value', $metadata);
    }

    private function trimBoundaryWhitespace($value)
    {
        $pattern = '/^[\\x{0009}-\\x{000D}\\x{0020}\\x{00A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}]+|[\\x{0009}-\\x{000D}\\x{0020}\\x{00A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}]+$/u';

        return preg_replace($pattern, '', $value);
    }

    private function containsMixedCanonicalTokens($value)
    {
        $yesPattern = '/(?<![\\p{L}\\p{N}_])Да(?![\\p{L}\\p{N}_])/u';
        $noPattern = '/(?<![\\p{L}\\p{N}_])Нет(?![\\p{L}\\p{N}_])/u';

        return preg_match($yesPattern, $value) === 1 && preg_match($noPattern, $value) === 1;
    }

    private function result($status, $canonicalValue, $reason, array $metadata)
    {
        return array(
            'status' => $status,
            'value_type' => 'boolean_enum',
            'canonical_value' => $canonicalValue,
            'unit' => '',
            'warnings' => $reason === '' ? array() : array($reason),
            'ambiguity_reason' => $reason,
            'metadata' => $metadata,
        );
    }
}
