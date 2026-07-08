<?php

namespace FrameworkStandardization\Discovery;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;

final class DbReadOnlyAttributeDiscovery
{
    private $db;
    private $dbPrefix;
    private $languageId;

    public function __construct(ReadOnlyDbConnectionInterface $db, $dbPrefix, $languageId)
    {
        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
        $this->languageId = (int) $languageId;
    }

    public function discover($targetText, $limit, $categoryId = null)
    {
        $targetText = trim((string) $targetText);
        $limit = (int) $limit;
        $categoryId = $categoryId === null ? null : (int) $categoryId;

        if ($limit < 1) {
            $limit = 20;
        }

        if ($limit > 50) {
            $limit = 50;
        }

        $terms = $this->buildSearchTerms($targetText);
        $categoryIds = $categoryId === null ? array() : $this->loadCategoryScopeIds($categoryId);

        if (count($terms) === 0) {
            return array(
                'target' => $targetText,
                'category_id' => $categoryId,
                'category_scope_ids' => $categoryIds,
                'candidates' => array(),
                'warnings' => array('no_search_terms'),
            );
        }

        $candidates = $this->loadCandidates($terms, $categoryIds);
        $enrichedCandidates = array();

        foreach ($candidates as $candidate) {
            $attributeId = isset($candidate['attribute_id']) ? (int) $candidate['attribute_id'] : 0;
            $attributeName = isset($candidate['attribute_name']) ? (string) $candidate['attribute_name'] : '';
            $usageCount = isset($candidate['usage_count']) ? (int) $candidate['usage_count'] : 0;
            $rawSamples = $this->loadRawSamples($attributeId, $categoryIds);
            $match = $this->classifyMatch($targetText, $attributeName, $terms, $usageCount);

            $enrichedCandidates[] = array(
                'attribute_id' => $attributeId,
                'attribute_name' => $attributeName,
                'attribute_group_name' => isset($candidate['attribute_group_name']) ? (string) $candidate['attribute_group_name'] : '',
                'usage_count' => $usageCount,
                'reason_found' => $match['reason_found'],
                'possible_role' => $match['possible_role'],
                'warnings' => $match['warnings'],
                'raw_samples' => $rawSamples,
            );
        }

        usort($enrichedCandidates, array($this, 'sortCandidates'));
        $enrichedCandidates = array_slice($enrichedCandidates, 0, $limit);

        return array(
            'target' => $targetText,
            'category_id' => $categoryId,
            'category_scope_ids' => $categoryIds,
            'candidates' => $enrichedCandidates,
            'warnings' => array(),
        );
    }

    private function loadCandidates(array $terms, array $categoryIds)
    {
        $params = array(':language_id' => $this->languageId);
        $scoped = count($categoryIds) > 0;
        $categoryJoin = '';
        $categoryWhere = '';

        if ($scoped) {
            $placeholders = $this->buildCategoryPlaceholders($categoryIds, $params);
            $categoryJoin = 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
            $categoryJoin .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $placeholders) . ') ';
            $categoryWhere = 'AND p2c.product_id IS NOT NULL ';
        }

        $sql = 'SELECT ad.attribute_id, ad.name AS attribute_name, ';
        $sql .= 'COALESCE(agd.name, \'\') AS attribute_group_name, ';
        $sql .= 'COUNT(DISTINCT pa.product_id) AS usage_count ';
        $sql .= 'FROM ' . $this->dbPrefix . 'attribute_description ad ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute a ON a.attribute_id = ad.attribute_id ';
        $sql .= 'LEFT JOIN ' . $this->dbPrefix . 'attribute_group_description agd ';
        $sql .= 'ON agd.attribute_group_id = a.attribute_group_id AND agd.language_id = ad.language_id ';
        $sql .= ($scoped ? 'INNER JOIN ' : 'LEFT JOIN ') . $this->dbPrefix . 'product_attribute pa ';
        $sql .= 'ON pa.attribute_id = ad.attribute_id AND pa.language_id = ad.language_id ';
        $sql .= $categoryJoin;
        $sql .= 'WHERE ad.language_id = :language_id ';
        $sql .= $categoryWhere;
        $sql .= 'GROUP BY ad.attribute_id, ad.name, agd.name ';
        $sql .= 'ORDER BY usage_count DESC, ad.name ASC';

        $rows = $this->db->fetchAll($sql, $params);
        $matchedRows = array();

        foreach ($rows as $row) {
            $attributeName = isset($row['attribute_name']) ? (string) $row['attribute_name'] : '';

            if ($this->attributeNameMatchesTerms($attributeName, $terms)) {
                $matchedRows[] = $row;
            }
        }

        return $matchedRows;
    }

    private function attributeNameMatchesTerms($attributeName, array $terms)
    {
        $normalizedName = $this->normalizeText($attributeName);

        foreach ($terms as $term) {
            if (strpos($normalizedName, $term) !== false) {
                return true;
            }
        }

        return false;
    }

    private function loadRawSamples($attributeId, array $categoryIds)
    {
        if ((int) $attributeId <= 0) {
            return array();
        }

        $params = array(
            ':attribute_id' => (int) $attributeId,
            ':language_id' => $this->languageId,
        );
        $categoryJoin = '';

        if (count($categoryIds) > 0) {
            $placeholders = $this->buildCategoryPlaceholders($categoryIds, $params);
            $categoryJoin = 'INNER JOIN ' . $this->dbPrefix . 'product_to_category p2c ';
            $categoryJoin .= 'ON p2c.product_id = pa.product_id AND p2c.category_id IN (' . implode(', ', $placeholders) . ') ';
        }

        $sql = 'SELECT TRIM(pa.text) AS raw_value ';
        $sql .= 'FROM ' . $this->dbPrefix . 'product_attribute pa ';
        $sql .= $categoryJoin;
        $sql .= 'WHERE pa.attribute_id = :attribute_id ';
        $sql .= 'AND pa.language_id = :language_id ';
        $sql .= 'AND TRIM(pa.text) <> \'\' ';
        $sql .= 'GROUP BY TRIM(pa.text) ';
        $sql .= 'ORDER BY MIN(pa.product_id) ASC ';
        $sql .= 'LIMIT 3';

        $rows = $this->db->fetchAll($sql, $params);

        $samples = array();

        foreach ($rows as $row) {
            if (isset($row['raw_value']) && $row['raw_value'] !== '') {
                $samples[] = (string) $row['raw_value'];
            }
        }

        return $samples;
    }

    private function loadCategoryScopeIds($categoryId)
    {
        if ((int) $categoryId <= 0) {
            return array();
        }

        $rows = $this->db->fetchAll(
            'SELECT category_id, parent_id FROM ' . $this->dbPrefix . 'category',
            array()
        );
        $childrenByParent = array();
        $knownCategoryIds = array();

        foreach ($rows as $row) {
            $id = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : 0;

            if ($id <= 0) {
                continue;
            }

            $knownCategoryIds[$id] = true;

            if (!isset($childrenByParent[$parentId])) {
                $childrenByParent[$parentId] = array();
            }

            $childrenByParent[$parentId][] = $id;
        }

        if (!isset($knownCategoryIds[(int) $categoryId])) {
            return array((int) $categoryId);
        }

        $scopeIds = array();
        $queue = array((int) $categoryId);
        $seen = array();

        while (count($queue) > 0) {
            $currentId = array_shift($queue);

            if (isset($seen[$currentId])) {
                continue;
            }

            $seen[$currentId] = true;
            $scopeIds[] = $currentId;

            if (!isset($childrenByParent[$currentId])) {
                continue;
            }

            foreach ($childrenByParent[$currentId] as $childId) {
                if (!isset($seen[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        sort($scopeIds);

        return $scopeIds;
    }

    private function buildCategoryPlaceholders(array $categoryIds, array &$params)
    {
        $placeholders = array();
        $index = 0;

        foreach ($categoryIds as $categoryId) {
            $key = ':category_id_' . $index;
            $params[$key] = (int) $categoryId;
            $placeholders[] = $key;
            $index++;
        }

        return $placeholders;
    }

    private function buildSearchTerms($targetText)
    {
        $variants = array($targetText);
        $terms = array();

        foreach ($variants as $variant) {
            $normalized = $this->normalizeText($variant);
            $parts = preg_split('/\s+/u', $normalized);

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part === '' || $this->stringLength($part) < 2) {
                    continue;
                }

                if (!in_array($part, $terms, true)) {
                    $terms[] = $part;
                }
            }
        }

        return $terms;
    }

    private function classifyMatch($targetText, $attributeName, array $terms, $usageCount)
    {
        $normalizedTarget = $this->normalizeText($targetText);
        $normalizedName = $this->normalizeText($attributeName);
        $matchedTerms = 0;

        foreach ($terms as $term) {
            if (strpos($normalizedName, $term) !== false) {
                $matchedTerms++;
            }
        }

        $warnings = array();

        if ((int) $usageCount === 0) {
            $warnings[] = 'no_usage';
        }

        if ($matchedTerms === 0) {
            $warnings[] = 'weak_match';
        }

        if ($normalizedTarget !== '' && $normalizedName === $normalizedTarget) {
            return array(
                'reason_found' => 'exact_name_match',
                'possible_role' => 'canonical_candidate',
                'warnings' => $warnings,
            );
        }

        if (count($terms) > 0 && $matchedTerms === count($terms)) {
            return array(
                'reason_found' => 'all_search_terms_matched',
                'possible_role' => 'canonical_candidate',
                'warnings' => $warnings,
            );
        }

        if ($matchedTerms > 0) {
            return array(
                'reason_found' => 'partial_search_term_overlap',
                'possible_role' => count($terms) > 1 ? 'similar_but_different' : 'possible_alias_or_duplicate',
                'warnings' => $warnings,
            );
        }

        return array(
            'reason_found' => 'weak_or_unclear_overlap',
            'possible_role' => 'unsafe_or_unresolved',
            'warnings' => $warnings,
        );
    }

    private function sortCandidates($left, $right)
    {
        $roleRank = array(
            'canonical_candidate' => 0,
            'possible_alias_or_duplicate' => 1,
            'similar_but_different' => 2,
            'unsafe_or_unresolved' => 3,
        );

        $leftRole = isset($left['possible_role']) ? $left['possible_role'] : 'unsafe_or_unresolved';
        $rightRole = isset($right['possible_role']) ? $right['possible_role'] : 'unsafe_or_unresolved';
        $leftRank = isset($roleRank[$leftRole]) ? $roleRank[$leftRole] : 9;
        $rightRank = isset($roleRank[$rightRole]) ? $roleRank[$rightRole] : 9;

        if ($leftRank !== $rightRank) {
            return $leftRank < $rightRank ? -1 : 1;
        }

        $leftUsage = isset($left['usage_count']) ? (int) $left['usage_count'] : 0;
        $rightUsage = isset($right['usage_count']) ? (int) $right['usage_count'] : 0;

        if ($leftUsage !== $rightUsage) {
            return $leftUsage > $rightUsage ? -1 : 1;
        }

        return strcmp((string) $left['attribute_name'], (string) $right['attribute_name']);
    }

    private function normalizeText($value)
    {
        $value = trim((string) $value);

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtr($value, array(
                'A' => 'a',
                'B' => 'b',
                'C' => 'c',
                'D' => 'd',
                'E' => 'e',
                'F' => 'f',
                'G' => 'g',
                'H' => 'h',
                'I' => 'i',
                'J' => 'j',
                'K' => 'k',
                'L' => 'l',
                'M' => 'm',
                'N' => 'n',
                'O' => 'o',
                'P' => 'p',
                'Q' => 'q',
                'R' => 'r',
                'S' => 's',
                'T' => 't',
                'U' => 'u',
                'V' => 'v',
                'W' => 'w',
                'X' => 'x',
                'Y' => 'y',
                'Z' => 'z',
                'А' => 'а',
                'Б' => 'б',
                'В' => 'в',
                'Г' => 'г',
                'Д' => 'д',
                'Е' => 'е',
                'Ё' => 'ё',
                'Ж' => 'ж',
                'З' => 'з',
                'И' => 'и',
                'Й' => 'й',
                'К' => 'к',
                'Л' => 'л',
                'М' => 'м',
                'Н' => 'н',
                'О' => 'о',
                'П' => 'п',
                'Р' => 'р',
                'С' => 'с',
                'Т' => 'т',
                'У' => 'у',
                'Ф' => 'ф',
                'Х' => 'х',
                'Ц' => 'ц',
                'Ч' => 'ч',
                'Ш' => 'ш',
                'Щ' => 'щ',
                'Ъ' => 'ъ',
                'Ы' => 'ы',
                'Ь' => 'ь',
                'Э' => 'э',
                'Ю' => 'ю',
                'Я' => 'я',
                'І' => 'і',
                'Ї' => 'ї',
                'Є' => 'є',
                'Ґ' => 'ґ',
            ));
        }

        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }

    private function stringLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        if (preg_match_all('/./us', $value, $matches)) {
            return count($matches[0]);
        }

        return strlen($value);
    }
}
