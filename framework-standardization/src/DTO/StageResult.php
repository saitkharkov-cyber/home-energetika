<?php

namespace FrameworkStandardization\DTO;

final class StageResult
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_OK = 'ok';
    const STATUS_OK_WITH_WARNINGS = 'ok_with_warnings';
    const STATUS_FAILED = 'failed';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_SKIPPED = 'skipped';

    private $status;
    private $startedAt;
    private $finishedAt;
    private $errors;
    private $warnings;
    private $summary;

    public function __construct($status, $startedAt, $finishedAt, array $errors = [], array $warnings = [], array $summary = [])
    {
        $this->status = $status;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->summary = $summary;
    }

    public static function ok(array $summary = [])
    {
        return self::create(self::STATUS_OK, [], [], $summary);
    }

    public static function okWithWarnings(array $warnings = [], array $summary = [])
    {
        return self::create(self::STATUS_OK_WITH_WARNINGS, [], $warnings, $summary);
    }

    public static function failed(array $errors = [], array $summary = [])
    {
        return self::create(self::STATUS_FAILED, $errors, [], $summary);
    }

    public static function blocked(array $warnings = [], array $summary = [])
    {
        return self::create(self::STATUS_BLOCKED, [], $warnings, $summary);
    }

    public static function skipped($reason = '', array $summary = [])
    {
        if ($reason !== '') {
            $summary['reason'] = $reason;
        }

        return self::create(self::STATUS_SKIPPED, [], [], $summary);
    }

    private static function create($status, array $errors, array $warnings, array $summary)
    {
        $now = date('Y-m-d H:i:s');

        return new self($status, $now, $now, $errors, $warnings, $summary);
    }

    public function toArray()
    {
        return [
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => $this->summary,
        ];
    }
}
