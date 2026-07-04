<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\SqlPreviewBuilderInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class BuildSqlPreviewStage implements StageInterface
{
    private $builder;

    public function __construct(SqlPreviewBuilderInterface $builder)
    {
        $this->builder = $builder;
    }

    public function getName()
    {
        return 'build_sql_preview';
    }

    public function run(AttributeContext $context)
    {
        $rawJob = $context->getJob()->getRawJob();
        $outputRules = isset($rawJob['output']) && is_array($rawJob['output']) ? $rawJob['output'] : array();
        $valueRules = isset($rawJob['value_rules']) && is_array($rawJob['value_rules']) ? $rawJob['value_rules'] : array();
        $generateSqlPreview = isset($outputRules['generate_sql_preview']) ? $outputRules['generate_sql_preview'] : 0;

        if ((string)$generateSqlPreview !== '1') {
            $sqlPreview = array(
                'enabled' => 0,
                'generated' => 0,
                'safe_to_apply' => 0,
                'apply_changes' => isset($outputRules['apply_changes']) ? (int)$outputRules['apply_changes'] : 0,
                'source' => 'dry_run_fixture',
                'mode' => 'preview_only',
                'blocked_by' => array(),
                'statements' => array(),
                'operations' => array(
                    'would_create' => array(),
                    'would_update' => array(),
                    'skipped' => array(),
                    'blocked' => array(),
                ),
                'diagnostics' => array(),
            );
            $context->setSqlPreview($sqlPreview);
            $context->addStageResult($this->getName(), StageResult::skipped('sql_preview_disabled', $this->buildSummary($sqlPreview)));

            return $context;
        }

        $result = $this->builder->build(
            $context->getCanonical(),
            $context->getScope(),
            $context->getAttributeNameStructure(),
            $context->getSynonymCandidates(),
            $context->getAttributeValueStructure(),
            $context->getValueReport(),
            $outputRules,
            $valueRules
        );

        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $sqlPreview = isset($result['sql_preview']) && is_array($result['sql_preview']) ? $result['sql_preview'] : array();
        $summary = $this->buildSummary($sqlPreview);

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->setSqlPreview($sqlPreview);
            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setSqlPreview($sqlPreview);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }

    private function buildSummary(array $sqlPreview)
    {
        $statements = isset($sqlPreview['statements']) && is_array($sqlPreview['statements']) ? $sqlPreview['statements'] : array();
        $operations = isset($sqlPreview['operations']) && is_array($sqlPreview['operations']) ? $sqlPreview['operations'] : array();
        $wouldCreate = isset($operations['would_create']) && is_array($operations['would_create']) ? $operations['would_create'] : array();
        $wouldUpdate = isset($operations['would_update']) && is_array($operations['would_update']) ? $operations['would_update'] : array();
        $skipped = isset($operations['skipped']) && is_array($operations['skipped']) ? $operations['skipped'] : array();
        $blocked = isset($operations['blocked']) && is_array($operations['blocked']) ? $operations['blocked'] : array();
        $blockedBy = isset($sqlPreview['blocked_by']) && is_array($sqlPreview['blocked_by']) ? $sqlPreview['blocked_by'] : array();

        return array(
            'enabled' => isset($sqlPreview['enabled']) ? $sqlPreview['enabled'] : 0,
            'generated' => isset($sqlPreview['generated']) ? $sqlPreview['generated'] : 0,
            'safe_to_apply' => isset($sqlPreview['safe_to_apply']) ? $sqlPreview['safe_to_apply'] : 0,
            'statement_count' => count($statements),
            'would_create_count' => count($wouldCreate),
            'would_update_count' => count($wouldUpdate),
            'skipped_count' => count($skipped),
            'blocked_count' => count($blocked),
            'blocked_by_count' => count($blockedBy),
            'source' => isset($sqlPreview['source']) ? $sqlPreview['source'] : 'unknown',
        );
    }
}
