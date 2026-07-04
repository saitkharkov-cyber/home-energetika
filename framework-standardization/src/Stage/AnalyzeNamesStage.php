<?php

namespace FrameworkStandardization\Stage;

use FrameworkStandardization\Contract\AttributeNameAnalyzerInterface;
use FrameworkStandardization\Contract\StageInterface;
use FrameworkStandardization\DTO\AttributeContext;
use FrameworkStandardization\DTO\StageResult;

final class AnalyzeNamesStage implements StageInterface
{
    private $analyzer;

    public function __construct(AttributeNameAnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function getName()
    {
        return 'analyze_names';
    }

    public function run(AttributeContext $context)
    {
        $canonical = $context->getCanonical();
        $rawData = $context->getRawData();
        $attributeNameStructure = $context->getAttributeNameStructure();
        $result = $this->analyzer->analyze($canonical, $rawData, $attributeNameStructure);
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();
        $exactMatches = isset($result['exact_matches']) && is_array($result['exact_matches']) ? $result['exact_matches'] : array();
        $foundAttributes = isset($result['found_attributes']) && is_array($result['found_attributes']) ? $result['found_attributes'] : array();
        $synonymCandidates = isset($result['synonym_candidates']) && is_array($result['synonym_candidates']) ? $result['synonym_candidates'] : array();
        $proposedSynonyms = isset($synonymCandidates['proposed']) && is_array($synonymCandidates['proposed']) ? $synonymCandidates['proposed'] : array();
        $ambiguousCandidates = isset($synonymCandidates['ambiguous']) && is_array($synonymCandidates['ambiguous']) ? $synonymCandidates['ambiguous'] : array();
        $targetAttribute = isset($result['target_attribute']) && is_array($result['target_attribute']) ? $result['target_attribute'] : array();
        $summary = array(
            'canonical_code' => isset($canonical['canonical_code']) ? $canonical['canonical_code'] : '',
            'target_attribute_id' => isset($targetAttribute['attribute_id']) ? $targetAttribute['attribute_id'] : '',
            'found_attribute_count' => count($foundAttributes),
            'exact_match_count' => count($exactMatches),
            'proposed_synonym_count' => count($proposedSynonyms),
            'ambiguous_candidate_count' => count($ambiguousCandidates),
            'source' => isset($result['source']) ? $result['source'] : 'unknown',
        );

        if ($errors !== array()) {
            foreach ($errors as $error) {
                $context->addError($error);
            }

            $context->addStageResult($this->getName(), StageResult::failed($errors, $summary));

            return $context;
        }

        $context->setAttributeNameStructure(array(
            'target_attribute' => $targetAttribute,
            'found_attributes' => $foundAttributes,
            'exact_matches' => $exactMatches,
            'similar_name_candidates' => isset($result['similar_name_candidates']) && is_array($result['similar_name_candidates']) ? $result['similar_name_candidates'] : array(),
            'rejected_name_candidates' => isset($result['rejected_name_candidates']) && is_array($result['rejected_name_candidates']) ? $result['rejected_name_candidates'] : array(),
            'diagnostics' => isset($result['diagnostics']) && is_array($result['diagnostics']) ? $result['diagnostics'] : array(),
        ));
        $context->setSynonymCandidates($synonymCandidates);
        $context->addStageResult($this->getName(), StageResult::ok($summary));

        return $context;
    }
}
