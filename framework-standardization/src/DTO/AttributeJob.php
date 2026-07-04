<?php

namespace FrameworkStandardization\DTO;

final class AttributeJob
{
    private $jobId;
    private $jobName;
    private $rawJob;

    public function __construct($jobId, $jobName, array $rawJob)
    {
        $this->jobId = $jobId;
        $this->jobName = $jobName;
        $this->rawJob = $rawJob;
    }

    public static function fromArray(array $rawJob)
    {
        return new self(
            isset($rawJob['job_id']) ? (string)$rawJob['job_id'] : '',
            isset($rawJob['job_name']) ? (string)$rawJob['job_name'] : '',
            $rawJob
        );
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function getJobName()
    {
        return $this->jobName;
    }

    public function getRawJob()
    {
        return $this->rawJob;
    }
}
