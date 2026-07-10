<?php

namespace FrameworkStandardization\Normalizer;

final class NormalizerRegistry
{
    private $normalizers;

    public function __construct()
    {
        $this->normalizers = array();
    }

    public static function createDefault()
    {
        $registry = new self();
        $registry->register('simple_meters', new SimpleMetersNormalizer());
        $registry->register('voltage', new VoltageNormalizer());

        return $registry;
    }

    public function register($key, $normalizer)
    {
        $key = trim((string) $key);

        if ($key === '') {
            throw new \InvalidArgumentException('normalizer_key_required');
        }

        if (!is_object($normalizer) || !method_exists($normalizer, 'normalize')) {
            throw new \InvalidArgumentException('normalizer_must_have_normalize_method');
        }

        $this->normalizers[$key] = $normalizer;
    }

    public function get($key)
    {
        $key = trim((string) $key);

        if (!isset($this->normalizers[$key])) {
            throw new \InvalidArgumentException('pipeline_normalizer_unknown');
        }

        return $this->normalizers[$key];
    }

    public function has($key)
    {
        return isset($this->normalizers[trim((string) $key)]);
    }
}
