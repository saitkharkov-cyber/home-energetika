<?php

namespace FrameworkStandardization\Normalizer;

final class SimpleMetersNormalizer
{
    public function normalize($rawValue)
    {
        $value = trim((string) $rawValue);

        if ($value === '') {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        if (preg_match('/[a-zA-Zа-яА-Я]/u', str_replace(array('м', 'М', 'm', 'M'), '', $value))) {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        if (preg_match('/^\s*до\s+/ui', $value)) {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        if (preg_match('/[–—-]/u', $value)) {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        preg_match_all('/[0-9]+(?:[\.,][0-9]+)?/u', $value, $matches);

        if (count($matches[0]) !== 1) {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        $number = str_replace(',', '.', $matches[0][0]);

        if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/', $number)) {
            return array('normalized_value' => null, 'reason' => 'unresolved_or_excluded_value');
        }

        $float = (float) $number;

        if ((string) (int) $float === $number) {
            return array('normalized_value' => (string) (int) $float, 'reason' => 'normalized');
        }

        return array('normalized_value' => rtrim(rtrim(sprintf('%.6F', $float), '0'), '.'), 'reason' => 'normalized');
    }
}
