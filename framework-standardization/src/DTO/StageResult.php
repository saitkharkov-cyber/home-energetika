<?php

namespace FrameworkStandardization\DTO;

final class StageResult
{
    private $status;
    private $errors;
    private $warnings;
    private $summary;

    public function __construct($status, array $errors = [], array $warnings = [], array $summary = [])
    {
        $this->status = $status;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->summary = $summary;
    }

    public static function ok(array $summary = [])
    {
        return new self('ok', [], [], $summary);
    }

    public function toArray()
    {
        return [
            'status' => $this->status,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->summary,
        ];
    }
}
