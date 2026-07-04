<?php

namespace FrameworkStandardization\DTO;

final class FrameworkResult
{
    private $resultStatus;
    private $stageSummary;

    public function __construct($resultStatus, array $stageSummary)
    {
        $this->resultStatus = $resultStatus;
        $this->stageSummary = $stageSummary;
    }

    public static function fromContext(AttributeContext $context)
    {
        return new self(
            $context->hasErrors() ? 'failed' : 'ok',
            [
                'completed_stages' => $context->completedStages,
                'stage_results' => $context->stageResults,
            ]
        );
    }

    public function getResultStatus()
    {
        return $this->resultStatus;
    }

    public function getStageSummary()
    {
        return $this->stageSummary;
    }
}
