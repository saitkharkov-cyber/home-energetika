<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class PipelineEngine
{
    private $stages;
    private $safeModeStageNames = [
        'build_report',
        'build_framework_result',
    ];

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
            if ($context->hasErrors() && !$this->isSafeModeStage($stage)) {
                $context->addStageResult($stage->getName(), StageResult::skipped('pipeline_has_errors'));
                continue;
            }

            $context = $stage->run($context);
        }

        return $context;
    }

    private function isSafeModeStage(StageInterface $stage)
    {
        return in_array($stage->getName(), $this->safeModeStageNames, true);
    }
}
