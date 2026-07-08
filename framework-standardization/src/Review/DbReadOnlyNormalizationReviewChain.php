<?php

namespace FrameworkStandardization\Review;

use FrameworkStandardization\Proposals\DbReadOnlyNormalizationProposals;

final class DbReadOnlyNormalizationReviewChain
{
    private $proposalGenerator;

    public function __construct(DbReadOnlyNormalizationProposals $proposalGenerator)
    {
        $this->proposalGenerator = $proposalGenerator;
    }

    public function generate($categoryId, array $attributeIds, $canonicalAttributeId, $canonicalUnit)
    {
        $proposalResult = $this->proposalGenerator->generate(
            $categoryId,
            $attributeIds,
            $canonicalAttributeId,
            $canonicalUnit
        );

        $reviewRows = array();
        $unresolvedRows = array();

        foreach ($proposalResult['proposals'] as $proposal) {
            $reviewRows[] = array(
                'review_id' => $this->buildReviewId($categoryId, $proposal['product_id'], $proposal['attribute_id'], 'normalized'),
                'product_id' => (int) $proposal['product_id'],
                'attribute_id' => (int) $proposal['attribute_id'],
                'attribute_name' => (string) $proposal['attribute_name'],
                'raw_value' => (string) $proposal['raw_value'],
                'proposed_normalized_value' => (string) $proposal['normalized_value'],
                'canonical_unit' => (string) $proposal['canonical_unit'],
                'review_status' => 'pending_review',
                'reason' => (string) $proposal['reason'],
            );
        }

        foreach ($proposalResult['unresolved'] as $unresolved) {
            $unresolvedRows[] = array(
                'review_id' => $this->buildReviewId($categoryId, $unresolved['product_id'], $unresolved['attribute_id'], 'unresolved'),
                'product_id' => (int) $unresolved['product_id'],
                'attribute_id' => (int) $unresolved['attribute_id'],
                'attribute_name' => (string) $unresolved['attribute_name'],
                'raw_value' => (string) $unresolved['raw_value'],
                'review_status' => 'unresolved',
                'reason' => (string) $unresolved['reason'],
            );
        }

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'normalization_review_chain',
            'category_id' => (int) $categoryId,
            'category_scope_ids' => $proposalResult['category_scope_ids'],
            'attribute_ids' => $attributeIds,
            'canonical_attribute_id' => (int) $canonicalAttributeId,
            'canonical_unit' => (string) $canonicalUnit,
            'review_rows' => $reviewRows,
            'unresolved_rows' => $unresolvedRows,
            'pending_review_count' => count($reviewRows),
            'unresolved_count' => count($unresolvedRows),
            'skipped_count' => (int) $proposalResult['skipped_count'],
            'review_chain_generated' => 1,
            'review_chain_persisted' => 0,
            'approved_auto_assigned' => 0,
            'normalization_proposals_generated' => (int) $proposalResult['normalization_proposals_generated'],
            'unresolved_values_reported' => (int) $proposalResult['unresolved_values_reported'],
        );
    }

    private function buildReviewId($categoryId, $productId, $attributeId, $marker)
    {
        return 'max_head_' . (int) $categoryId . '_' . (int) $productId . '_' . (int) $attributeId . '_' . $marker;
    }
}
