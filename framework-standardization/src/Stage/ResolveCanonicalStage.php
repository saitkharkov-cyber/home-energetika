<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\CanonicalAttributeResolverInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class ResolveCanonicalStage implements StageInterface
{
    private $resolver;

    public function __construct(CanonicalAttributeResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getName()
    {
        return 'resolve_canonical';
    }

    public function run(AttributeContext $context)
    {
        $rawJob = $context->getJob()->getRawJob();
        $canonicalCode = $this->getCanonicalCode($rawJob);
        $result = $this->resolver->resolve($canonicalCode);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $summary = array(
            'canonical_code' => $canonicalCode,
            'found' => isset($result['found']) ? $result['found'] : 0,
            'source' => isset($result['source']) ? $result['source'] : 'unknown',
        );

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $canonical = isset($result['canonical']) && is_array($result['canonical']) ? $result['canonical'] : array();
        $context->setCanonical($canonical);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }

    private function getCanonicalCode(array $rawJob)
    {
        if (!isset($rawJob['canonical']) || !is_array($rawJob['canonical'])) {
            return '';
        }

        if (!isset($rawJob['canonical']['canonical_code'])) {
            return '';
        }

        return (string)$rawJob['canonical']['canonical_code'];
    }
}
