<?php

namespace FrameworkStandardization\Runner;

use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\AttributeJob;
use FrameworkStandardization\DTO\FrameworkResult;
use FrameworkStandardization\Pipeline\PipelineFactory;

final class FrameworkRunner
{
    public function run(array $rawJob)
    {
        $job = AttributeJob::fromArray($rawJob);
        $context = new AttributeContext($job);

        $pipeline = (new PipelineFactory())->createDefault();
        $context = $pipeline->run($context);

        return $context->frameworkResult !== null ? $context->frameworkResult : FrameworkResult::fromContext($context);
    }
}
