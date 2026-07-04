<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class ValidateJobStage implements StageInterface
{
    public function getName()
    {
        return 'validate_job';
    }

    public function run(AttributeContext $context)
    {
        $job = $context->getJob();
        $rawJob = $job->getRawJob();
        $errors = [];

        if ($this->isEmpty($job->getJobId())) {
            $errors[] = 'job_id_empty';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $job->getJobId())) {
            $errors[] = 'job_id_invalid_format';
        }

        if ($this->isEmpty($job->getJobName())) {
            $errors[] = 'job_name_empty';
        }

        if ($this->isEmpty($this->getNestedValue($rawJob, ['canonical', 'canonical_code']))) {
            $errors[] = 'canonical_code_empty';
        }

        $scopeType = $this->getNestedValue($rawJob, ['scope', 'type']);
        if ($scopeType !== 'category') {
            $errors[] = 'unsupported_scope_type';
        } elseif ($this->isEmpty($this->getNestedValue($rawJob, ['scope', 'category_id']))) {
            $errors[] = 'scope_category_id_empty';
        }

        $sourceType = $this->getNestedValue($rawJob, ['source', 'type']);
        if ($sourceType !== 'opencart_db') {
            $errors[] = 'unsupported_source_type';
        }

        if ($this->isEmpty($this->getNestedValue($rawJob, ['source', 'language_id']))) {
            $errors[] = 'language_id_empty';
        }

        if ($this->isEmpty($this->getNestedValue($rawJob, ['value_rules', 'value_parser']))) {
            $errors[] = 'value_parser_empty';
        }

        $unknownValuePolicy = $this->getNestedValue($rawJob, ['value_rules', 'unknown_value_policy']);
        if (!in_array($unknownValuePolicy, ['block_sql', 'report_only'], true)) {
            $errors[] = 'unknown_value_policy_invalid';
        }

        $applyChanges = $this->getNestedValue($rawJob, ['output', 'apply_changes']);
        if (!($applyChanges === 0 || $applyChanges === '0')) {
            $errors[] = 'apply_changes_not_allowed';
        }

        $summary = [
            'validated' => true,
            'errors_count' => count($errors),
        ];

        if ($errors !== []) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }

    private function getNestedValue(array $data, array $path)
    {
        $value = $data;

        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    private function isEmpty($value)
    {
        return $value === null || trim((string)$value) === '';
    }
}
