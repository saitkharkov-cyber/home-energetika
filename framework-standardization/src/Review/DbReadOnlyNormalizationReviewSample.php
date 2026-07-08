<?php

namespace FrameworkStandardization\Review;

final class DbReadOnlyNormalizationReviewSample
{
    private $reviewChain;

    public function __construct(DbReadOnlyNormalizationReviewChain $reviewChain)
    {
        $this->reviewChain = $reviewChain;
    }

    public function generate($categoryId, array $attributeIds, $canonicalAttributeId, $canonicalUnit, $limit)
    {
        $limit = (int) $limit;
        $reviewChainResult = $this->reviewChain->generate(
            $categoryId,
            $attributeIds,
            $canonicalAttributeId,
            $canonicalUnit
        );

        $pendingReviewSample = array_slice($reviewChainResult['review_rows'], 0, $limit);
        $unresolvedSample = array_slice($reviewChainResult['unresolved_rows'], 0, $limit);

        return array(
            'runtime_mode' => 'db_readonly',
            'command' => 'normalization_review_sample',
            'category_id' => (int) $categoryId,
            'category_scope_ids' => $reviewChainResult['category_scope_ids'],
            'attribute_ids' => $attributeIds,
            'canonical_attribute_id' => (int) $canonicalAttributeId,
            'canonical_unit' => (string) $canonicalUnit,
            'limit' => $limit,
            'pending_review_sample' => $pendingReviewSample,
            'unresolved_sample' => $unresolvedSample,
            'total_pending_review_count' => (int) $reviewChainResult['pending_review_count'],
            'pending_review_sample_count' => count($pendingReviewSample),
            'total_unresolved_count' => (int) $reviewChainResult['unresolved_count'],
            'unresolved_sample_count' => count($unresolvedSample),
            'skipped_count' => (int) $reviewChainResult['skipped_count'],
            'review_sample_generated' => 1,
            'review_sample_persisted' => 0,
            'approved_auto_assigned' => 0,
            'review_chain_persisted' => (int) $reviewChainResult['review_chain_persisted'],
        );
    }
}
