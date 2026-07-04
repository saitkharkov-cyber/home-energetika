<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\ScopeResolverInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class ResolveScopeStage implements StageInterface
{
    private $resolver;

    public function __construct(ScopeResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getName()
    {
        return 'resolve_scope';
    }

    public function run(AttributeContext $context)
    {
        $rawJob = $context->getJob()->getRawJob();
        $scope = $this->getScope($rawJob);
        $result = $this->resolver->resolve($scope);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $resolvedScope = isset($result['scope']) && is_array($result['scope']) ? $result['scope'] : array();
        $products = isset($result['products']) && is_array($result['products']) ? $result['products'] : array();
        $summary = array(
            'scope_type' => isset($scope['type']) ? $scope['type'] : '',
            'category_id' => isset($scope['category_id']) ? $scope['category_id'] : '',
            'found' => isset($result['found']) ? $result['found'] : 0,
            'product_count' => isset($resolvedScope['product_count']) ? $resolvedScope['product_count'] : 0,
            'source' => isset($result['source']) ? $result['source'] : 'unknown',
        );

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setScope($resolvedScope);
        $context->setRawDataProducts($products);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }

    private function getScope(array $rawJob)
    {
        if (!isset($rawJob['scope']) || !is_array($rawJob['scope'])) {
            return array();
        }

        return $rawJob['scope'];
    }
}
