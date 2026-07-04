<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class BuildSqlPreviewStage implements StageInterface
{
    public function getName()
    {
        return 'build_sql_preview';
    }

    public function run(AttributeContext $context)
    {
        $context->addStageResult($this->getName(), StageResult::ok(['stub' => true]));

        return $context;
    }
}
