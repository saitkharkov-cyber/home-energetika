<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\FrameworkResult;
use FrameworkStandardization\DTO\StageResult;

final class BuildFrameworkResultStage implements StageInterface
{
    public function getName()
    {
        return 'build_framework_result';
    }

    public function run(AttributeContext $context)
    {
        $context->addStageResult($this->getName(), StageResult::ok(['stub' => true]));
        $context->frameworkResult = FrameworkResult::fromContext($context);

        return $context;
    }
}
