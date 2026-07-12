<?php

namespace FrameworkStandardization\Review;

final class CharacteristicRegistryGate1ReviewQueueBuilder
{
    public function build(array $registry)
    {
        $validated = $this->validateRegistry($registry);
        $rows = $validated['rows'];
        $requiredIds = $this->collectContractRequiredIds($rows);
        $exactGroups = $this->buildExactDuplicateGroups($rows);
        $groupIdByAttributeId = $this->buildGroupIdMap($exactGroups);
        $tokensByAttributeId = $this->buildTokensByAttributeId($rows);
        $links = $this->buildRelatedCandidateLinks($rows, $requiredIds, $groupIdByAttributeId, $tokensByAttributeId);
        $reviewItems = $this->buildReviewItems($rows, $requiredIds, $groupIdByAttributeId, $links);
        $summary = $this->buildSummary($rows, $requiredIds, $exactGroups, $links);

        return array(
            'builder' => 'characteristic_registry_gate1_review_queue',
            'scope' => $validated['scope'],
            'review_items' => $reviewItems,
            'exact_duplicate_groups' => $exactGroups,
            'related_candidate_links' => $links,
            'summary' => $summary,
            'safety' => array(
                'read_only' => 1,
                'db_connected' => 0,
                'source_registry_db_connected' => $validated['safety']['db_connected'],
                'semantic_decisions_applied' => 0,
                'contracts_created' => 0,
                'aliases_approved' => 0,
                'exclusions_approved' => 0,
                'normalizers_assigned' => 0,
                'pipeline_executed' => 0,
                'normalization_performed' => 0,
                'sql_generated' => 0,
                'apply_performed' => 0,
                'product_data_changed' => 0,
                'production_touched' => 0,
                'cache_rebuild_performed' => 0,
            ),
        );
    }

    private function validateRegistry(array $registry)
    {
        if (!isset($registry['builder']) || $registry['builder'] !== 'read_only_characteristic_registry') {
            throw new \InvalidArgumentException('characteristic_registry_gate1_registry_builder_invalid');
        }

        foreach (array('scope', 'rows', 'summary', 'safety') as $key) {
            if (!isset($registry[$key]) || !is_array($registry[$key])) {
                throw new \InvalidArgumentException('characteristic_registry_gate1_' . $key . '_invalid');
            }
        }

        $seenIds = array();
        $rows = array();
        foreach ($registry['rows'] as $row) {
            $validated = $this->validateRow($row);
            $attributeId = $validated['attribute_id'];
            if (isset($seenIds[$attributeId])) {
                throw new \InvalidArgumentException('characteristic_registry_gate1_duplicate_attribute_id');
            }
            $seenIds[$attributeId] = true;
            $rows[] = $validated;
        }

        return array(
            'scope' => $registry['scope'],
            'rows' => $rows,
            'summary' => $registry['summary'],
            'safety' => $this->validateSafety($registry['safety']),
        );
    }

    private function validateSafety(array $safety)
    {
        if (!array_key_exists('db_connected', $safety) || !is_int($safety['db_connected']) || ($safety['db_connected'] !== 0 && $safety['db_connected'] !== 1)) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_source_db_connected_invalid');
        }

        return $safety;
    }

    private function validateRow($row)
    {
        if (!is_array($row)) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_row_invalid');
        }

        if (!isset($row['attribute_id']) || !is_int($row['attribute_id']) || $row['attribute_id'] <= 0) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_attribute_id_invalid');
        }

        if (!isset($row['attribute_name']) || !is_string($row['attribute_name']) || trim($row['attribute_name']) === '') {
            throw new \InvalidArgumentException('characteristic_registry_gate1_attribute_name_required');
        }

        if (!array_key_exists('attribute_group_name', $row) || !is_string($row['attribute_group_name'])) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_attribute_group_name_invalid');
        }

        if (!isset($row['usage_count']) || !is_int($row['usage_count']) || $row['usage_count'] < 0) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_usage_count_invalid');
        }

        if (!isset($row['distinct_products']) || !is_int($row['distinct_products']) || $row['distinct_products'] < 0) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_distinct_products_invalid');
        }

        if (!isset($row['legacy_match']) || !is_array($row['legacy_match'])) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_legacy_match_invalid');
        }

        if (!isset($row['status_markers']) || !is_array($row['status_markers'])) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_status_markers_invalid');
        }

        foreach (array('discovery_contract', 'normalizer', 'processing_review') as $dimension) {
            if (!isset($row['status_markers'][$dimension]) || !is_array($row['status_markers'][$dimension])) {
                throw new \InvalidArgumentException('characteristic_registry_gate1_status_markers_invalid');
            }
        }

        if (!isset($row['block_reasons']) || !is_array($row['block_reasons'])) {
            throw new \InvalidArgumentException('characteristic_registry_gate1_block_reasons_invalid');
        }

        return array(
            'attribute_id' => $row['attribute_id'],
            'attribute_name' => trim($row['attribute_name']),
            'attribute_group_name' => $row['attribute_group_name'],
            'usage_count' => $row['usage_count'],
            'distinct_products' => $row['distinct_products'],
            'legacy_match' => $row['legacy_match'],
            'status_markers' => $row['status_markers'],
            'block_reasons' => $row['block_reasons'],
        );
    }

    private function collectContractRequiredIds(array $rows)
    {
        $ids = array();
        foreach ($rows as $row) {
            if ($this->hasMarker($row, 'discovery_contract', 'contract_required')) {
                $ids[$row['attribute_id']] = true;
            }
        }

        return $ids;
    }

    private function buildExactDuplicateGroups(array $rows)
    {
        $groupsByName = array();
        foreach ($rows as $row) {
            $normalizedName = $this->normalizeName($row['attribute_name']);
            if (!isset($groupsByName[$normalizedName])) {
                $groupsByName[$normalizedName] = array();
            }
            $groupsByName[$normalizedName][] = $row;
        }

        $groups = array();
        foreach ($groupsByName as $normalizedName => $groupRows) {
            if (count($groupRows) < 2) {
                continue;
            }

            $attributeIds = array();
            $requiredIds = array();
            $contextIds = array();
            $usageCountSum = 0;
            $distinctProductsSum = 0;

            foreach ($groupRows as $row) {
                $attributeIds[] = $row['attribute_id'];
                $usageCountSum += $row['usage_count'];
                $distinctProductsSum += $row['distinct_products'];
                if ($this->hasMarker($row, 'discovery_contract', 'contract_required')) {
                    $requiredIds[] = $row['attribute_id'];
                } else {
                    $contextIds[] = $row['attribute_id'];
                }
            }

            if (count($requiredIds) === 0) {
                continue;
            }

            sort($attributeIds, SORT_NUMERIC);
            sort($requiredIds, SORT_NUMERIC);
            sort($contextIds, SORT_NUMERIC);

            $groups[] = array(
                'group_id' => 'exact_name_' . substr(sha1($normalizedName), 0, 12),
                'group_type' => 'exact_normalized_name',
                'normalized_name' => $normalizedName,
                'candidate_only' => 1,
                'review_required' => 1,
                'attribute_ids' => $attributeIds,
                'contract_required_ids' => $requiredIds,
                'context_ids' => $contextIds,
                'usage_count_sum' => $usageCountSum,
                'distinct_products_sum' => $distinctProductsSum,
            );
        }

        usort($groups, array($this, 'compareGroups'));

        return $groups;
    }

    private function buildGroupIdMap(array $groups)
    {
        $map = array();
        foreach ($groups as $group) {
            foreach ($group['attribute_ids'] as $attributeId) {
                $map[$attributeId] = $group['group_id'];
            }
        }

        return $map;
    }

    private function buildTokensByAttributeId(array $rows)
    {
        $tokens = array();
        foreach ($rows as $row) {
            $tokens[$row['attribute_id']] = $this->analysisTokens($row['attribute_name']);
        }

        return $tokens;
    }

    private function buildRelatedCandidateLinks(array $rows, array $requiredIds, array $groupIdByAttributeId, array $tokensByAttributeId)
    {
        $links = array();
        $count = count($rows);
        for ($leftIndex = 0; $leftIndex < $count; $leftIndex++) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex++) {
                $left = $rows[$leftIndex];
                $right = $rows[$rightIndex];
                $leftId = $left['attribute_id'];
                $rightId = $right['attribute_id'];

                if (!isset($requiredIds[$leftId]) && !isset($requiredIds[$rightId])) {
                    continue;
                }

                if (isset($groupIdByAttributeId[$leftId]) && isset($groupIdByAttributeId[$rightId]) && $groupIdByAttributeId[$leftId] === $groupIdByAttributeId[$rightId]) {
                    continue;
                }

                $leftTokens = $tokensByAttributeId[$leftId];
                $rightTokens = $tokensByAttributeId[$rightId];
                if (count($leftTokens) === 0 || count($rightTokens) === 0) {
                    continue;
                }

                $sharedTokens = $this->intersectTokens($leftTokens, $rightTokens);
                $reasonCodes = array();
                if ($this->isTokenSubset($leftTokens, $rightTokens) || $this->isTokenSubset($rightTokens, $leftTokens)) {
                    $reasonCodes[] = 'token_subset';
                }
                if (count($sharedTokens) >= 2) {
                    $reasonCodes[] = 'shared_tokens_2_plus';
                }

                if (count($reasonCodes) === 0) {
                    continue;
                }

                if ($leftId > $rightId) {
                    $tmp = $left;
                    $left = $right;
                    $right = $tmp;
                    $tmpTokens = $leftTokens;
                    $leftTokens = $rightTokens;
                    $rightTokens = $tmpTokens;
                    $leftId = $left['attribute_id'];
                    $rightId = $right['attribute_id'];
                }

                $links[] = array(
                    'left_attribute_id' => $leftId,
                    'right_attribute_id' => $rightId,
                    'left_attribute_name' => $left['attribute_name'],
                    'right_attribute_name' => $right['attribute_name'],
                    'shared_tokens' => $sharedTokens,
                    'similarity_percent' => (int) round(100 * (2 * count($sharedTokens)) / (count($leftTokens) + count($rightTokens))),
                    'reason_codes' => $reasonCodes,
                    'candidate_only' => 1,
                    'review_required' => 1,
                );
            }
        }

        usort($links, array($this, 'compareLinks'));

        return $links;
    }

    private function buildReviewItems(array $rows, array $requiredIds, array $groupIdByAttributeId, array $links)
    {
        $relatedById = array();
        foreach ($links as $link) {
            $relatedById[$link['left_attribute_id']][$link['right_attribute_id']] = true;
            $relatedById[$link['right_attribute_id']][$link['left_attribute_id']] = true;
        }

        $items = array();
        foreach ($rows as $row) {
            $attributeId = $row['attribute_id'];
            if (!isset($requiredIds[$attributeId])) {
                continue;
            }

            $relatedIds = isset($relatedById[$attributeId]) ? array_keys($relatedById[$attributeId]) : array();
            sort($relatedIds, SORT_NUMERIC);

            $items[] = array(
                'attribute_id' => $attributeId,
                'attribute_name' => $row['attribute_name'],
                'attribute_group_name' => $row['attribute_group_name'],
                'usage_count' => $row['usage_count'],
                'distinct_products' => $row['distinct_products'],
                'exact_duplicate_group_id' => isset($groupIdByAttributeId[$attributeId]) ? $groupIdByAttributeId[$attributeId] : '',
                'related_candidate_ids' => $relatedIds,
                'review_status' => 'review_required',
                'candidate_only' => 1,
            );
        }

        usort($items, array($this, 'compareReviewItems'));

        return $items;
    }

    private function buildSummary(array $rows, array $requiredIds, array $groups, array $links)
    {
        $requiredInGroups = array();
        foreach ($groups as $group) {
            foreach ($group['contract_required_ids'] as $attributeId) {
                $requiredInGroups[$attributeId] = true;
            }
        }

        return array(
            'registry_rows_total' => count($rows),
            'contract_required_total' => count($requiredIds),
            'exact_duplicate_groups_total' => count($groups),
            'contract_required_in_exact_groups' => count($requiredInGroups),
            'contract_required_singletons' => count($requiredIds) - count($requiredInGroups),
            'related_candidate_links_total' => count($links),
            'auto_decisions_total' => 0,
        );
    }

    private function normalizeName($name)
    {
        $value = trim((string) $name);
        $value = strtr($value, $this->caseMap());
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function analysisTokens($name)
    {
        $normalized = $this->normalizeName($name);
        if ($normalized === '') {
            return array();
        }

        $tokens = preg_split('/\s+/u', $normalized);
        $unique = array();
        foreach ($tokens as $token) {
            if (preg_match('/^\p{L}{3,}$/u', $token) === 1) {
                $unique[$token] = true;
            }
        }

        $result = array_keys($unique);
        sort($result, SORT_STRING);

        return $result;
    }

    private function intersectTokens(array $leftTokens, array $rightTokens)
    {
        $right = array();
        foreach ($rightTokens as $token) {
            $right[$token] = true;
        }

        $result = array();
        foreach ($leftTokens as $token) {
            if (isset($right[$token])) {
                $result[] = $token;
            }
        }

        sort($result, SORT_STRING);

        return $result;
    }

    private function isTokenSubset(array $leftTokens, array $rightTokens)
    {
        if (count($leftTokens) === 0 || count($leftTokens) > count($rightTokens)) {
            return false;
        }

        $right = array();
        foreach ($rightTokens as $token) {
            $right[$token] = true;
        }

        foreach ($leftTokens as $token) {
            if (!isset($right[$token])) {
                return false;
            }
        }

        return true;
    }

    private function hasMarker(array $row, $dimension, $marker)
    {
        return in_array($marker, $row['status_markers'][$dimension], true);
    }

    private function compareGroups(array $left, array $right)
    {
        $nameCompare = strcmp($left['normalized_name'], $right['normalized_name']);
        if ($nameCompare !== 0) {
            return $nameCompare;
        }

        return strcmp($left['group_id'], $right['group_id']);
    }

    private function compareLinks(array $left, array $right)
    {
        if ($left['left_attribute_id'] !== $right['left_attribute_id']) {
            return $left['left_attribute_id'] < $right['left_attribute_id'] ? -1 : 1;
        }

        if ($left['right_attribute_id'] === $right['right_attribute_id']) {
            return 0;
        }

        return $left['right_attribute_id'] < $right['right_attribute_id'] ? -1 : 1;
    }

    private function compareReviewItems(array $left, array $right)
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

    private function caseMap()
    {
        return array(
            'A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd', 'E' => 'e',
            'F' => 'f', 'G' => 'g', 'H' => 'h', 'I' => 'i', 'J' => 'j',
            'K' => 'k', 'L' => 'l', 'M' => 'm', 'N' => 'n', 'O' => 'o',
            'P' => 'p', 'Q' => 'q', 'R' => 'r', 'S' => 's', 'T' => 't',
            'U' => 'u', 'V' => 'v', 'W' => 'w', 'X' => 'x', 'Y' => 'y',
            'Z' => 'z',
            'Ё' => 'е', 'ё' => 'е',
            'А' => 'а', 'Б' => 'б', 'В' => 'в', 'Г' => 'г', 'Д' => 'д',
            'Е' => 'е', 'Ж' => 'ж', 'З' => 'з', 'И' => 'и', 'Й' => 'й',
            'К' => 'к', 'Л' => 'л', 'М' => 'м', 'Н' => 'н', 'О' => 'о',
            'П' => 'п', 'Р' => 'р', 'С' => 'с', 'Т' => 'т', 'У' => 'у',
            'Ф' => 'ф', 'Х' => 'х', 'Ц' => 'ц', 'Ч' => 'ч', 'Ш' => 'ш',
            'Щ' => 'щ', 'Ъ' => 'ъ', 'Ы' => 'ы', 'Ь' => 'ь', 'Э' => 'э',
            'Ю' => 'ю', 'Я' => 'я', 'Ґ' => 'ґ', 'Є' => 'є', 'І' => 'і',
            'Ї' => 'ї',
        );
    }
}
