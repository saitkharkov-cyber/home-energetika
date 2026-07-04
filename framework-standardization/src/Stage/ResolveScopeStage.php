<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class ResolveScopeStage implements StageInterface
{
    public function getName()
    {
        return 'resolve_scope';
    }

    public function run(AttributeContext $context)
    {
        $context->addStageResult($this->getName(), StageResult::ok(['stub' => true]));

        return $context;
    }
}
