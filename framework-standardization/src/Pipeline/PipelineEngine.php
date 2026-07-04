<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;

final class PipelineEngine
{
    private $stages;

    /**
     * @param StageInterface[] $stages
     */
    public function __construct(array $stages)
    {
        $this->stages = $stages;
    }

    public function run(AttributeContext $context)
    {
        foreach ($this->stages as $stage) {
            $context = $stage->run($context);

            if ($context->hasErrors()) {
                break;
            }
        }

        return $context;
    }
}
