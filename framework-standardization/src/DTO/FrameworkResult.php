<?php

namespace FrameworkStandardization\DTO;

final class FrameworkResult
{
    private $resultStatus;
    private $stageSummary;
    private $payload;

    public function __construct($resultStatus, array $stageSummary, array $payload = array())
    {
        $this->resultStatus = $resultStatus;
        $this->stageSummary = $stageSummary;
        $this->payload = $payload;
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
            ],
            array()
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

    public function getPayload()
    {
        return $this->payload;
    }
}
