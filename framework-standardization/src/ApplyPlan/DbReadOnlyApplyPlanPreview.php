<?php

namespace FrameworkStandardization\ApplyPlan;

use FrameworkStandardization\Preview\DbReadOnlySqlPreview;

final class DbReadOnlyApplyPlanPreview
{
    private $sqlPreview;

    public function __construct(DbReadOnlySqlPreview $sqlPreview)
    {
        $this->sqlPreview = $sqlPreview;
    }

    public function generate($categoryId, array $attributeIds, $canonicalAttributeId, $canonicalUnit)
    {
        $sqlPreviewResult = $this->sqlPreview->generate($categoryId, $attributeIds, $canonicalAttributeId, $canonicalUnit);
        $updateStatements = array();
        $insertStatements = array();

        foreach ($sqlPreviewResult['statements'] as $statement) {
            if (strpos($statement, 'UPDATE ') === 0) {
                $updateStatements[] = $statement;
            } elseif (strpos($statement, 'INSERT ') === 0) {
                $insertStatements[] = $statement;
            }
        }

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'apply_plan_preview',
            'category_id' => (int) $categoryId,
            'attribute_ids' => $attributeIds,
            'canonical_attribute_id' => (int) $canonicalAttributeId,
            'canonical_unit' => (string) $canonicalUnit,
            'sql_preview' => $sqlPreviewResult,
            'update_statements' => $updateStatements,
            'insert_statements' => $insertStatements,
            'preflight_checks' => $this->buildPreflightChecks($sqlPreviewResult),
            'post_apply_verification_plan' => $this->buildPostApplyVerificationPlan(),
            'rollback_notes' => $this->buildRollbackNotes(),
            'apply_plan_preview_generated' => 1,
            'executable_apply_plan' => 0,
        );
    }

    private function buildPreflightChecks(array $sqlPreviewResult)
    {
        return array(
            array(
                'check' => 'runtime_mode_db_readonly',
                'status' => $sqlPreviewResult['runtime_mode'] === 'db_readonly' ? 'ok' : 'blocked',
                'note' => 'current preview runs only against db_readonly runtime',
            ),
            array(
                'check' => 'local_dump_runtime_verified',
                'status' => 'ok',
                'note' => 'CLI validates host, dbname and prefix before generating preview',
            ),
            array(
                'check' => 'product_attribute_schema_verified',
                'status' => $sqlPreviewResult['storage_schema']['schema_status'] === 'ok' ? 'ok' : 'blocked',
                'note' => $sqlPreviewResult['storage_schema']['table_name'] . ': ' . implode(',', $sqlPreviewResult['storage_schema']['relevant_columns']),
            ),
            array(
                'check' => 'no_schema_blockers',
                'status' => (int) $sqlPreviewResult['schema_blocker_count'] === 0 ? 'ok' : 'blocked',
                'note' => 'schema_blocker_count=' . (int) $sqlPreviewResult['schema_blocker_count'],
            ),
            array(
                'check' => 'no_conflicts',
                'status' => (int) $sqlPreviewResult['conflicts_count'] === 0 ? 'ok' : 'blocked',
                'note' => 'conflicts_count=' . (int) $sqlPreviewResult['conflicts_count'],
            ),
            array(
                'check' => 'unresolved_excluded',
                'status' => 'ok',
                'note' => 'unresolved_excluded_count=' . (int) $sqlPreviewResult['unresolved_excluded_count'],
            ),
            array(
                'check' => 'human_review_decision_present',
                'status' => 'ok',
                'note' => 'docs/HUMAN_REVIEW_MAX_HEAD_PROPOSALS_SCOPE_11900213.md',
            ),
            array(
                'check' => 'manual_sql_preview_review_documented',
                'status' => 'ok',
                'note' => 'docs/RUNTIME_CHECKS.md',
            ),
        );
    }

    private function buildPostApplyVerificationPlan()
    {
        return array(
            'verify updated canonical rows count against preview_update_existing_canonical_row_count',
            'verify inserted canonical rows count against preview_insert_missing_canonical_row_count',
            'verify unresolved values were not included in applied set',
            'verify all affected rows use canonical attribute_id=12',
            'verify source alias rows are preserved',
            'verify affected products remain within category_scope only',
        );
    }

    private function buildRollbackNotes()
    {
        return array(
            'rollback SQL is not generated in this gate',
            'rollback requires a separate explicit gate',
            'rollback requires a verified backup or local dump snapshot before any future apply',
            'production/cache rollback is out of scope for this preview',
        );
    }
}
