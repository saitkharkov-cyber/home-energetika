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
            self::resolveStatus($context),
            [
                'completed_stages' => $context->completedStages,
                'stage_results' => $context->stageResults,
                'warnings' => $context->warnings,
                'errors' => $context->errors,
            ]
        );
    }

    private static function resolveStatus(AttributeContext $context)
    {
        if ($context->hasErrors()) {
            return 'failed';
        }

        if ($context->warnings !== []) {
            return 'ok_with_warnings';
        }

        return 'ok';
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
