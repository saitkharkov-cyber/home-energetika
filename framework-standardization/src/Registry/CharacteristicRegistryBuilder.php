<?php

namespace FrameworkStandardization\Registry;

final class CharacteristicRegistryBuilder
{
    private $normalizerRegistry;

    public function __construct($normalizerRegistry)
    {
        if (!is_object($normalizerRegistry) || !method_exists($normalizerRegistry, 'has')) {
            throw new \InvalidArgumentException('characteristic_registry_normalizer_registry_invalid');
        }

        $this->normalizerRegistry = $normalizerRegistry;
    }

    public function build(array $scope, array $discoveredAttributes, array $legacyDecisions)
    {
        $validatedScope = $this->validateScope($scope);
        $validatedDiscovery = $this->validateDiscoveredAttributes($discoveredAttributes);
        $legacyIndex = $this->buildLegacyIndex($legacyDecisions);

        $rows = array();
        foreach ($validatedDiscovery as $discovered) {
            $rows[] = $this->buildRow($discovered, $legacyIndex);
        }

        usort($rows, array($this, 'compareRows'));

        return array(
            'builder' => 'read_only_characteristic_registry',
            'scope' => $validatedScope,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
            'safety' => array(
                'read_only' => 1,
                'db_connected' => 0,
                'pipeline_executed' => 0,
                'normalization_performed' => 0,
                'sql_generated' => 0,
                'apply_plan_created' => 0,
                'apply_performed' => 0,
                'product_data_changed' => 0,
                'production_touched' => 0,
                'cache_rebuild_performed' => 0,
            ),
        );
    }

    private function validateScope(array $scope)
    {
        if (!isset($scope['root_category_id']) || !is_int($scope['root_category_id']) || $scope['root_category_id'] <= 0) {
            throw new \InvalidArgumentException('characteristic_registry_root_category_id_invalid');
        }

        if (!isset($scope['scope_mode']) || !is_string($scope['scope_mode']) || trim($scope['scope_mode']) === '') {
            throw new \InvalidArgumentException('characteristic_registry_scope_mode_invalid');
        }

        $scopeMode = trim($scope['scope_mode']);
        if ($scopeMode !== 'hierarchical_category_path_exists') {
            throw new \InvalidArgumentException('characteristic_registry_scope_mode_unsupported');
        }

        return array(
            'root_category_id' => $scope['root_category_id'],
            'scope_mode' => $scopeMode,
        );
    }

    private function validateDiscoveredAttributes(array $discoveredAttributes)
    {
        $seenIds = array();
        $validated = array();

        foreach ($discoveredAttributes as $row) {
            if (!is_array($row) || !isset($row['attribute_id']) || !is_int($row['attribute_id']) || $row['attribute_id'] <= 0) {
                throw new \InvalidArgumentException('characteristic_registry_attribute_id_invalid');
            }

            $attributeId = $row['attribute_id'];
            if (isset($seenIds[$attributeId])) {
                throw new \InvalidArgumentException('characteristic_registry_duplicate_attribute_id');
            }
            $seenIds[$attributeId] = true;

            if (!isset($row['attribute_name']) || !is_string($row['attribute_name']) || trim($row['attribute_name']) === '') {
                throw new \InvalidArgumentException('characteristic_registry_attribute_name_required');
            }

            if (!array_key_exists('attribute_group_name', $row) || !is_string($row['attribute_group_name'])) {
                throw new \InvalidArgumentException('characteristic_registry_attribute_group_name_invalid');
            }

            if (!isset($row['usage_count']) || !is_int($row['usage_count']) || $row['usage_count'] < 0) {
                throw new \InvalidArgumentException('characteristic_registry_usage_count_invalid');
            }

            if (!isset($row['distinct_products']) || !is_int($row['distinct_products']) || $row['distinct_products'] < 0) {
                throw new \InvalidArgumentException('characteristic_registry_distinct_products_invalid');
            }

            $validated[] = array(
                'attribute_id' => $attributeId,
                'attribute_name' => trim($row['attribute_name']),
                'attribute_group_name' => $row['attribute_group_name'],
                'usage_count' => $row['usage_count'],
                'distinct_products' => $row['distinct_products'],
            );
        }

        return $validated;
    }

    private function buildLegacyIndex(array $legacyDecisions)
    {
        $index = array();

        foreach ($legacyDecisions as $decision) {
            $validated = $this->validateLegacyDecision($decision);
            $this->addLegacyMatch($index, $validated, $validated['canonical_attribute_id'], 'canonical');

            foreach ($validated['included_alias_attribute_ids'] as $attributeId) {
                $this->addLegacyMatch($index, $validated, $attributeId, 'alias');
            }

            foreach ($validated['excluded_attribute_ids'] as $attributeId) {
                $this->addLegacyMatch($index, $validated, $attributeId, 'excluded');
            }
        }

        return $index;
    }

    private function validateLegacyDecision($decision)
    {
        if (!is_array($decision)) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_record_invalid');
        }

        if (!isset($decision['characteristic_key']) || !is_string($decision['characteristic_key']) || trim($decision['characteristic_key']) === '') {
            throw new \InvalidArgumentException('characteristic_registry_legacy_characteristic_key_required');
        }

        if (!isset($decision['decision_status']) || !is_string($decision['decision_status'])) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_decision_status_invalid');
        }

        $decisionStatus = trim($decision['decision_status']);
        if ($decisionStatus !== 'draft' && $decisionStatus !== 'approved') {
            throw new \InvalidArgumentException('characteristic_registry_legacy_decision_status_invalid');
        }

        if (!isset($decision['canonical_attribute_id']) || !is_int($decision['canonical_attribute_id']) || $decision['canonical_attribute_id'] <= 0) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_canonical_attribute_id_invalid');
        }

        $aliases = $this->validateLegacyIdList($decision, 'included_alias_attribute_ids');
        $excluded = $this->validateLegacyIdList($decision, 'excluded_attribute_ids');
        $canonicalId = $decision['canonical_attribute_id'];

        if (in_array($canonicalId, $aliases, true)) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_canonical_in_aliases');
        }

        if (in_array($canonicalId, $excluded, true)) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_canonical_in_excluded');
        }

        foreach ($aliases as $aliasId) {
            if (in_array($aliasId, $excluded, true)) {
                throw new \InvalidArgumentException('characteristic_registry_legacy_alias_excluded_overlap');
            }
        }

        if (!array_key_exists('normalizer_key', $decision) || !is_string($decision['normalizer_key'])) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_normalizer_key_invalid');
        }

        $normalizerKey = trim($decision['normalizer_key']);

        if (!isset($decision['provenance']) || !is_string($decision['provenance']) || trim($decision['provenance']) === '') {
            throw new \InvalidArgumentException('characteristic_registry_legacy_provenance_required');
        }

        return array(
            'characteristic_key' => trim($decision['characteristic_key']),
            'decision_status' => $decisionStatus,
            'canonical_attribute_id' => $canonicalId,
            'included_alias_attribute_ids' => $aliases,
            'excluded_attribute_ids' => $excluded,
            'normalizer_key' => $normalizerKey,
            'provenance' => trim($decision['provenance']),
        );
    }

    private function validateLegacyIdList(array $decision, $key)
    {
        if (!isset($decision[$key]) || !is_array($decision[$key])) {
            throw new \InvalidArgumentException('characteristic_registry_legacy_' . $key . '_invalid');
        }

        $seen = array();
        $result = array();
        foreach ($decision[$key] as $attributeId) {
            if (!is_int($attributeId) || $attributeId <= 0) {
                throw new \InvalidArgumentException('characteristic_registry_legacy_attribute_id_invalid');
            }

            if (isset($seen[$attributeId])) {
                throw new \InvalidArgumentException('characteristic_registry_legacy_duplicate_attribute_id');
            }

            $seen[$attributeId] = true;
            $result[] = $attributeId;
        }

        return $result;
    }

    private function addLegacyMatch(array &$index, array $decision, $attributeId, $role)
    {
        if (!isset($index[$attributeId])) {
            $index[$attributeId] = array();
        }

        $index[$attributeId][] = array(
            'decision' => $decision,
            'role' => $role,
        );
    }

    private function buildRow(array $discovered, array $legacyIndex)
    {
        $attributeId = $discovered['attribute_id'];
        $statusMarkers = array(
            'discovery_contract' => array('discovered'),
            'normalizer' => array(),
            'processing_review' => array(),
        );
        $blockReasons = array();
        $legacyMatch = array(
            'matched' => false,
            'role' => 'unmatched',
        );

        if (!isset($legacyIndex[$attributeId])) {
            $statusMarkers['discovery_contract'][] = 'contract_required';
            return $this->composeRow($discovered, $legacyMatch, $statusMarkers, $blockReasons);
        }

        if (count($legacyIndex[$attributeId]) !== 1) {
            $statusMarkers['discovery_contract'][] = 'blocked';
            $statusMarkers['normalizer'][] = 'blocked';
            $statusMarkers['processing_review'][] = 'blocked';
            $blockReasons[] = 'legacy_mapping_conflict';
            $legacyMatch = array(
                'matched' => true,
                'role' => 'conflict',
            );

            return $this->composeRow($discovered, $legacyMatch, $statusMarkers, $blockReasons);
        }

        $match = $legacyIndex[$attributeId][0];
        $decision = $match['decision'];
        $role = $match['role'];
        $legacyMatch = array(
            'matched' => true,
            'characteristic_key' => $decision['characteristic_key'],
            'decision_status' => $decision['decision_status'],
            'role' => $role,
            'canonical_attribute_id' => $decision['canonical_attribute_id'],
            'normalizer_key' => $decision['normalizer_key'],
            'provenance' => $decision['provenance'],
        );

        if ($decision['decision_status'] === 'draft') {
            $statusMarkers['discovery_contract'][] = 'contract_draft';
            return $this->composeRow($discovered, $legacyMatch, $statusMarkers, $blockReasons);
        }

        $statusMarkers['discovery_contract'][] = 'contract_approved';

        if ($role === 'excluded') {
            $statusMarkers['processing_review'][] = 'blocked';
            $blockReasons[] = 'legacy_role_excluded';
            return $this->composeRow($discovered, $legacyMatch, $statusMarkers, $blockReasons);
        }

        if ($role === 'canonical' || $role === 'alias') {
            if ($decision['normalizer_key'] !== '' && $this->normalizerRegistry->has($decision['normalizer_key'])) {
                $statusMarkers['normalizer'][] = 'normalizer_ready';
                $statusMarkers['processing_review'][] = 'read_only_ready';
            } else {
                $statusMarkers['normalizer'][] = 'normalizer_required';
            }
        }

        return $this->composeRow($discovered, $legacyMatch, $statusMarkers, $blockReasons);
    }

    private function composeRow(array $discovered, array $legacyMatch, array $statusMarkers, array $blockReasons)
    {
        return array(
            'attribute_id' => $discovered['attribute_id'],
            'attribute_name' => $discovered['attribute_name'],
            'attribute_group_name' => $discovered['attribute_group_name'],
            'usage_count' => $discovered['usage_count'],
            'distinct_products' => $discovered['distinct_products'],
            'legacy_match' => $legacyMatch,
            'status_markers' => array(
                'discovery_contract' => array_values(array_unique($statusMarkers['discovery_contract'])),
                'normalizer' => array_values(array_unique($statusMarkers['normalizer'])),
                'processing_review' => array_values(array_unique($statusMarkers['processing_review'])),
            ),
            'block_reasons' => array_values(array_unique($blockReasons)),
        );
    }

    private function buildSummary(array $rows)
    {
        $summary = array(
            'total_discovered' => count($rows),
            'contract_required' => 0,
            'contract_draft' => 0,
            'contract_approved' => 0,
            'normalizer_required' => 0,
            'normalizer_ready' => 0,
            'read_only_ready' => 0,
            'blocked' => 0,
        );

        foreach ($rows as $row) {
            $allMarkers = array();
            foreach ($row['status_markers'] as $markers) {
                foreach ($markers as $marker) {
                    $allMarkers[$marker] = true;
                }
            }

            foreach (array('contract_required', 'contract_draft', 'contract_approved', 'normalizer_required', 'normalizer_ready', 'read_only_ready') as $marker) {
                if (isset($allMarkers[$marker])) {
                    $summary[$marker]++;
                }
            }

            if (isset($allMarkers['blocked'])) {
                $summary['blocked']++;
            }
        }

        return $summary;
    }

    private function compareRows(array $left, array $right)
    {
        $nameCompare = strcmp($left['attribute_name'], $right['attribute_name']);
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        if ($left['attribute_id'] === $right['attribute_id']) {
            return 0;
        }

        return $left['attribute_id'] < $right['attribute_id'] ? -1 : 1;
    }
}
