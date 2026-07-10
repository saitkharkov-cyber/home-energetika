<?php

namespace FrameworkStandardization\Pipeline;

use FrameworkStandardization\Discovery\DbReadOnlyAttributeDiscovery;
use FrameworkStandardization\Inventory\DbReadOnlyRawValuesInventory;
use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\OpenCart\OpenCartRuntimeConfig;
use FrameworkStandardization\OpenCart\PdoReadOnlyDbConnection;

final class StandardizationPipeline
{
    private $baseDir;
    private $normalizers;

    public function __construct($baseDir, NormalizerRegistry $normalizers)
    {
        $this->baseDir = rtrim(str_replace('\\', '/', (string) $baseDir), '/');
        $this->normalizers = $normalizers;
    }

    public function run(array $job, $outputDirOverride = null, $dryRun = false)
    {
        $startTime = date('c');
        $warnings = array();
        $runtimePath = $this->resolvePath($job['runtime_config']);
        $runtimeConfig = OpenCartRuntimeConfig::fromArray(require $runtimePath);
        $database = $runtimeConfig->getDatabase();
        $this->assertReadOnlyRuntime($runtimeConfig);

        $db = PdoReadOnlyDbConnection::fromRuntimeConfig($runtimeConfig);
        $dbPrefix = $runtimeConfig->getDbPrefix();
        $languageId = 1;
        $discovery = new DbReadOnlyAttributeDiscovery($db, $dbPrefix, $languageId);
        $inventoryService = new DbReadOnlyRawValuesInventory($db, $dbPrefix, $languageId);
        $normalizer = $this->normalizers->get($job['normalization']['normalizer_key']);
        $categoryId = (int) $job['scope']['category_ids'][0];
        $configuredCandidateIds = $this->filterExcluded(
            $this->getConfiguredCandidateIds($job),
            $job['target']['excluded_attribute_ids']
        );

        $discoveryResult = $this->runDiscovery($discovery, $job, $categoryId);
        $discoveredIds = $this->extractDiscoveredIds($discoveryResult);
        $discoveryBreakdown = $this->buildDiscoveryBreakdown($discoveredIds, $this->getConfiguredCandidateIds($job), $job['target']['excluded_attribute_ids']);

        if (count($discoveryBreakdown['unconfigured_discovered_candidates']) > 0) {
            $warnings[] = 'discovery_found_unconfigured_attributes:' . implode(',', $discoveryBreakdown['unconfigured_discovered_candidates']);
        }

        $inventory = $inventoryService->inventory($categoryId, $configuredCandidateIds);
        $inventory['runtime_mode'] = $runtimeConfig->getRuntimeMode();
        $inventory = $this->enrichInventory($db, $dbPrefix, $inventory, $job, $configuredCandidateIds);

        $canonicalFound = false;

        foreach ($inventory['attributes'] as $attribute) {
            if ((int) $attribute['attribute_id'] === (int) $job['target']['canonical_attribute_id']
                && (int) $attribute['distinct_product_count'] > 0
            ) {
                $canonicalFound = true;
            }

            if ((int) $attribute['distinct_product_count'] === 0) {
                $warnings[] = 'configured_candidate_not_found_in_scope:' . (int) $attribute['attribute_id'];
            }
        }

        if (!$canonicalFound) {
            throw new \RuntimeException('pipeline_canonical_attribute_not_found');
        }

        $scopeDiagnostics = $this->buildScopeDiagnostics($db, $dbPrefix, $job, $configuredCandidateIds, $languageId);
        $proposals = $this->buildProposals($db, $dbPrefix, $job, $configuredCandidateIds, $normalizer, $languageId);
        $proposalStatusCounts = $this->countProposalStatuses($proposals['items']);
        $proposalValueTypeCounts = $this->countProposalValueTypes($proposals['items']);
        $inventoryCounts = $this->buildInventoryCounts($inventory);
        $target = $job['target'];
        $target['canonical_attribute_name'] = $this->findAttributeName($inventory, (int) $job['target']['canonical_attribute_id']);
        $outputDirectory = $outputDirOverride === null ? $job['output']['directory'] : $outputDirOverride;
        $runId = $this->createRunId();
        $writer = new ReviewPackageWriter($this->baseDir);
        $generatedFiles = $this->buildGeneratedFilePaths($outputDirectory, (string) $job['job_key'], $runId, $writer->getFileNames());
        $safetyMarkers = array(
            'read_only' => 1,
            'discovery_completed' => 1,
            'raw_values_inventory_completed' => 1,
            'normalization_proposals_created' => 1,
            'review_package_created' => $dryRun ? 0 : 1,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'apply_performed' => 0,
            'safe_to_apply' => 0,
            'sql_apply_allowed' => 0,
            'production_ready' => 0,
            'cache_rebuild_allowed' => 0,
        );
        $manifest = array(
            'job_contract_version' => (int) $job['job_version'],
            'job_key' => (string) $job['job_key'],
            'run_id' => $runId,
            'start_time' => $startTime,
            'end_time' => date('c'),
            'runtime_mode' => $runtimeConfig->getRuntimeMode(),
            'database_name' => $database['dbname'],
            'scope' => $job['scope'],
            'target' => $target,
            'normalization' => $job['normalization'],
            'normalizer_key' => $job['normalization']['normalizer_key'],
            'generated_files' => $generatedFiles,
            'discovery' => $discoveryBreakdown,
            'inventory' => $inventoryCounts,
            'counts' => array(
                'discovery_candidates' => count($discoveryResult['candidates']),
                'inventory_attributes' => count($inventory['attributes']),
                'proposals_total' => count($proposals['items']),
                'proposal_statuses' => $proposalStatusCounts,
                'proposal_value_types' => $proposalValueTypeCounts,
                'scope_diagnostics' => $scopeDiagnostics['counts'],
            ),
            'warnings' => $warnings,
            'safety_markers' => $safetyMarkers,
            'final_pipeline_status' => $dryRun ? 'dry_run_completed_without_artifacts' : 'review_package_created',
        );
        $package = array(
            'job_key' => (string) $job['job_key'],
            'run_id' => $runId,
            'output_directory' => $outputDirectory,
            'discovery' => $discoveryResult,
            'inventory' => $inventory,
            'proposals' => $proposals,
            'scope_diagnostics' => $scopeDiagnostics,
            'manifest' => $manifest,
        );

        if ($dryRun) {
            return array(
                'package' => $package,
                'run_directory' => null,
            'files' => array(),
            );
        }

        $writeResult = $writer->write($package);

        return array(
            'package' => $package,
            'run_directory' => $writeResult['run_directory'],
            'files' => $writeResult['files'],
        );
    }

    private function runDiscovery(DbReadOnlyAttributeDiscovery $discovery, array $job, $categoryId)
    {
        $merged = array(
            'target' => implode(' ', $job['target']['search_terms']),
            'category_id' => $categoryId,
            'category_scope_ids' => array(),
            'candidates' => array(),
            'warnings' => array(),
        );
        $seen = array();

        foreach ($job['target']['search_terms'] as $term) {
            $result = $discovery->discover($term, 50, $categoryId);
            $merged['category_scope_ids'] = $result['category_scope_ids'];

            foreach ($result['candidates'] as $candidate) {
                $attributeId = (int) $candidate['attribute_id'];

                if (isset($seen[$attributeId])) {
                    continue;
                }

                $seen[$attributeId] = true;
                $merged['candidates'][] = $candidate;
            }
        }

        return $merged;
    }

    private function enrichInventory(
        PdoReadOnlyDbConnection $db,
        $dbPrefix,
        array $inventory,
        array $job,
        array $configuredCandidateIds
    ) {
        foreach ($inventory['attributes'] as $index => $attribute) {
            $rowCount = 0;
            $blankValuesCount = 0;

            foreach ($attribute['raw_values'] as $rawValue) {
                $count = isset($rawValue['count']) ? (int) $rawValue['count'] : 0;
                $rowCount += $count;

                if (trim((string) $rawValue['raw_value']) === '') {
                    $blankValuesCount += $count;
                }
            }

            $inventory['attributes'][$index]['row_count'] = $rowCount;
            $inventory['attributes'][$index]['distinct_product_count'] = (int) $attribute['products_with_attribute_count'];
            $inventory['attributes'][$index]['unique_raw_values_count'] = (int) $attribute['distinct_raw_values_count'];
            $inventory['attributes'][$index]['blank_values_count'] = $blankValuesCount;
        }

        $inventory['overlap'] = $this->loadOverlap($db, $dbPrefix, $job, $configuredCandidateIds);

        return $inventory;
    }

    private function loadOverlap(PdoReadOnlyDbConnection $db, $dbPrefix, array $job, array $configuredCandidateIds)
    {
        $params = array(':category_id' => (int) $job['scope']['category_ids'][0]);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $configuredCandidateIds, $params);

        $sql = 'SELECT COUNT(*) AS products_count FROM (';
        $sql .= 'SELECT pa.product_id, COUNT(DISTINCT pa.attribute_id) AS attribute_count ';
        $sql .= 'FROM ' . $dbPrefix . 'product_attribute pa ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'AND ' . $this->hierarchicalScopeExistsSql($dbPrefix, 'pa', ':category_id') . ' ';
        $sql .= 'GROUP BY pa.product_id HAVING attribute_count > 1) x';
        $multiple = $db->fetchOne($sql, $params);

        $canonicalCoverage = $this->countDistinctProductsForAttributes(
            $db,
            $dbPrefix,
            $job,
            array((int) $job['target']['canonical_attribute_id'])
        );
        $aliasIds = array();

        foreach ($configuredCandidateIds as $attributeId) {
            if ((int) $attributeId !== (int) $job['target']['canonical_attribute_id']) {
                $aliasIds[] = (int) $attributeId;
            }
        }

        return array(
            'products_with_multiple_configured_candidate_attributes' => isset($multiple['products_count']) ? (int) $multiple['products_count'] : 0,
            'canonical_attribute_coverage' => $canonicalCoverage,
            'alias_candidate_coverage' => $this->countDistinctProductsForAttributes($db, $dbPrefix, $job, $aliasIds),
        );
    }

    private function countDistinctProductsForAttributes(PdoReadOnlyDbConnection $db, $dbPrefix, array $job, array $attributeIds)
    {
        if (count($attributeIds) === 0) {
            return 0;
        }

        $params = array(':category_id' => (int) $job['scope']['category_ids'][0]);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $attributeIds, $params);
        $sql = 'SELECT COUNT(DISTINCT pa.product_id) AS products_count ';
        $sql .= 'FROM ' . $dbPrefix . 'product_attribute pa ';
        $sql .= 'WHERE pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'AND ' . $this->hierarchicalScopeExistsSql($dbPrefix, 'pa', ':category_id');
        $row = $db->fetchOne($sql, $params);

        return isset($row['products_count']) ? (int) $row['products_count'] : 0;
    }

    private function buildProposals(PdoReadOnlyDbConnection $db, $dbPrefix, array $job, array $configuredCandidateIds, $normalizer, $languageId)
    {
        $params = array(':category_id' => (int) $job['scope']['category_ids'][0], ':language_id' => (int) $languageId);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $configuredCandidateIds, $params);
        $sql = 'SELECT DISTINCT pa.product_id, pa.language_id, pa.attribute_id, ad.name AS attribute_name, TRIM(pa.text) AS raw_value ';
        $sql .= 'FROM ' . $dbPrefix . 'product_attribute pa ';
        $sql .= 'INNER JOIN ' . $dbPrefix . 'attribute_description ad ';
        $sql .= 'ON ad.attribute_id = pa.attribute_id AND ad.language_id = pa.language_id ';
        $sql .= 'WHERE pa.language_id = :language_id ';
        $sql .= 'AND pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $sql .= 'AND ' . $this->hierarchicalScopeExistsSql($dbPrefix, 'pa', ':category_id') . ' ';
        $sql .= 'ORDER BY pa.product_id ASC, pa.language_id ASC, pa.attribute_id ASC, raw_value ASC';
        $rows = $db->fetchAll($sql, $params);
        $items = array();

        foreach ($rows as $row) {
            $normalized = $normalizer->normalize(isset($row['raw_value']) ? $row['raw_value'] : '');
            $normalized = $this->enforceCanonicalValueContract($normalized, $job);
            $status = isset($normalized['status']) ? (string) $normalized['status'] : 'unsupported';

            if ($status === 'normalized') {
                $proposalStatus = $this->isUnchangedCanonicalValue($row, $normalized, $job) ? 'unchanged' : 'normalized';
            } elseif ($status === 'review_required') {
                $proposalStatus = 'review_required';
            } elseif ($status === 'invalid') {
                $proposalStatus = 'invalid';
            } else {
                $proposalStatus = 'unsupported';
            }

            $items[] = array(
                'product_id' => (int) $row['product_id'],
                'language_id' => (int) $row['language_id'],
                'source_attribute_id' => (int) $row['attribute_id'],
                'source_attribute_name' => (string) $row['attribute_name'],
                'raw_value' => isset($row['raw_value']) ? (string) $row['raw_value'] : '',
                'normalized_result' => $normalized,
                'normalizer_key' => (string) $job['normalization']['normalizer_key'],
                'proposal_status' => $proposalStatus,
                'warnings' => isset($normalized['warnings']) ? $normalized['warnings'] : array(),
                'ambiguity_reason' => isset($normalized['ambiguity_reason']) ? $normalized['ambiguity_reason'] : '',
                'canonical_attribute_id' => (int) $job['target']['canonical_attribute_id'],
                'scope' => $job['scope'],
            );
        }

        return array(
            'items' => $items,
            'status_counts' => $this->countProposalStatuses($items),
        );
    }

    private function buildScopeDiagnostics(
        PdoReadOnlyDbConnection $db,
        $dbPrefix,
        array $job,
        array $configuredCandidateIds,
        $languageId
    ) {
        $rootCategoryId = (int) $job['scope']['category_ids'][0];
        $params = array(':category_id' => $rootCategoryId, ':language_id' => (int) $languageId);
        $attributePlaceholders = $this->buildPlaceholders('attribute_id', $configuredCandidateIds, $params);
        $attributeFilter = 'pa.attribute_id IN (' . implode(', ', $attributePlaceholders) . ') ';
        $hierarchicalScope = $this->hierarchicalScopeExistsSql($dbPrefix, 'pa', ':category_id');
        $directParent = $this->directParentExistsSql($dbPrefix, 'pa', ':category_id');

        $hierarchicalRows = $this->countTargetRows($db, $dbPrefix, $attributeFilter, $hierarchicalScope, $params);
        $directParentRows = $this->countTargetRows($db, $dbPrefix, $attributeFilter, $directParent, $params);
        $withoutDirectParentRows = $this->countTargetRows($db, $dbPrefix, $attributeFilter, $hierarchicalScope . ' AND NOT ' . $directParent, $params);

        $sql = 'SELECT pa.product_id, COALESCE(pd.name, \'\') AS product_name, ';
        $sql .= 'COUNT(*) AS target_attribute_row_count, ';
        $sql .= 'GROUP_CONCAT(DISTINCT pa.attribute_id ORDER BY pa.attribute_id SEPARATOR \',\') AS attribute_ids, ';
        $sql .= '(SELECT GROUP_CONCAT(DISTINCT p2c_all.category_id ORDER BY p2c_all.category_id SEPARATOR \',\') ';
        $sql .= 'FROM ' . $dbPrefix . 'product_to_category p2c_all ';
        $sql .= 'WHERE p2c_all.product_id = pa.product_id) AS direct_category_ids ';
        $sql .= 'FROM ' . $dbPrefix . 'product_attribute pa ';
        $sql .= 'LEFT JOIN ' . $dbPrefix . 'product_description pd ';
        $sql .= 'ON pd.product_id = pa.product_id AND pd.language_id = pa.language_id ';
        $sql .= 'WHERE pa.language_id = :language_id ';
        $sql .= 'AND ' . $attributeFilter;
        $sql .= 'AND ' . $hierarchicalScope . ' ';
        $sql .= 'AND NOT ' . $directParent . ' ';
        $sql .= 'GROUP BY pa.product_id, pd.name ';
        $sql .= 'ORDER BY pa.product_id ASC';

        $rows = $db->fetchAll($sql, $params);
        $products = array();

        foreach ($rows as $row) {
            $products[] = array(
                'product_id' => (int) $row['product_id'],
                'product_name' => isset($row['product_name']) ? (string) $row['product_name'] : '',
                'direct_category_ids' => $this->splitCsvInts(isset($row['direct_category_ids']) ? $row['direct_category_ids'] : ''),
                'target_attribute_row_count' => isset($row['target_attribute_row_count']) ? (int) $row['target_attribute_row_count'] : 0,
                'attribute_ids' => $this->splitCsvInts(isset($row['attribute_ids']) ? $row['attribute_ids'] : ''),
            );
        }

        return array(
            'root_category_id' => $rootCategoryId,
            'scope_pattern' => 'hierarchical_category_path_exists',
            'counts' => array(
                'hierarchical_scope_rows' => $hierarchicalRows,
                'direct_parent_rows' => $directParentRows,
                'rows_without_direct_parent' => $withoutDirectParentRows,
                'products_without_direct_parent' => count($products),
            ),
            'products_without_direct_parent' => $products,
        );
    }

    private function countTargetRows(PdoReadOnlyDbConnection $db, $dbPrefix, $attributeFilter, $scopePredicate, array $params)
    {
        $sql = 'SELECT COUNT(*) AS rows_count ';
        $sql .= 'FROM ' . $dbPrefix . 'product_attribute pa ';
        $sql .= 'WHERE pa.language_id = :language_id ';
        $sql .= 'AND ' . $attributeFilter;
        $sql .= 'AND ' . $scopePredicate;
        $row = $db->fetchOne($sql, $params);

        return isset($row['rows_count']) ? (int) $row['rows_count'] : 0;
    }

    private function hierarchicalScopeExistsSql($dbPrefix, $productAlias, $categoryPlaceholder)
    {
        return 'EXISTS (SELECT 1 FROM ' . $dbPrefix . 'product_to_category scope_p2c '
            . 'INNER JOIN ' . $dbPrefix . 'category_path scope_cp '
            . 'ON scope_cp.category_id = scope_p2c.category_id AND scope_cp.path_id = ' . $categoryPlaceholder . ' '
            . 'WHERE scope_p2c.product_id = ' . $productAlias . '.product_id)';
    }

    private function directParentExistsSql($dbPrefix, $productAlias, $categoryPlaceholder)
    {
        return 'EXISTS (SELECT 1 FROM ' . $dbPrefix . 'product_to_category direct_p2c '
            . 'WHERE direct_p2c.product_id = ' . $productAlias . '.product_id '
            . 'AND direct_p2c.category_id = ' . $categoryPlaceholder . ')';
    }

    private function splitCsvInts($value)
    {
        $result = array();
        $parts = explode(',', (string) $value);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $result[] = (int) $part;
        }

        return $result;
    }

    private function countProposalStatuses(array $items)
    {
        $counts = array(
            'normalized' => 0,
            'unchanged' => 0,
            'review_required' => 0,
            'unsupported' => 0,
            'invalid' => 0,
        );

        foreach ($items as $item) {
            $status = isset($item['proposal_status']) ? (string) $item['proposal_status'] : 'unsupported';

            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }

            $counts[$status]++;
        }

        return $counts;
    }

    private function countProposalValueTypes(array $items)
    {
        $counts = array(
            'single' => 0,
            'range' => 0,
            'compound' => 0,
            'unsupported' => 0,
            'invalid' => 0,
        );

        foreach ($items as $item) {
            $valueType = isset($item['normalized_result']['value_type']) ? (string) $item['normalized_result']['value_type'] : 'unsupported';

            if (!isset($counts[$valueType])) {
                $counts[$valueType] = 0;
            }

            $counts[$valueType]++;
        }

        return $counts;
    }

    private function buildInventoryCounts(array $inventory)
    {
        $totalRows = 0;

        foreach ($inventory['attributes'] as $attribute) {
            $totalRows += isset($attribute['row_count']) ? (int) $attribute['row_count'] : 0;
        }

        return array(
            'total_inventory_rows' => $totalRows,
            'products_with_multiple_configured_candidate_attributes' => isset($inventory['overlap']['products_with_multiple_configured_candidate_attributes'])
                ? (int) $inventory['overlap']['products_with_multiple_configured_candidate_attributes']
                : 0,
        );
    }

    private function findAttributeName(array $inventory, $attributeId)
    {
        foreach ($inventory['attributes'] as $attribute) {
            if ((int) $attribute['attribute_id'] === (int) $attributeId) {
                return isset($attribute['attribute_name']) ? (string) $attribute['attribute_name'] : '';
            }
        }

        return '';
    }

    private function buildDiscoveryBreakdown(array $discoveredIds, array $candidateIds, array $excludedIds)
    {
        $configuredFound = array();
        $configuredMissing = array();
        $unconfigured = array();
        $excludedDiscovered = array();

        foreach ($candidateIds as $candidateId) {
            $candidateId = (int) $candidateId;

            if (in_array($candidateId, $discoveredIds, true)) {
                $configuredFound[] = $candidateId;
            } else {
                $configuredMissing[] = $candidateId;
            }
        }

        foreach ($discoveredIds as $discoveredId) {
            if (in_array($discoveredId, $excludedIds, true)) {
                $excludedDiscovered[] = $discoveredId;
            } elseif (!in_array($discoveredId, $candidateIds, true)) {
                $unconfigured[] = $discoveredId;
            }
        }

        return array(
            'configured_candidates_found_in_scope' => $configuredFound,
            'configured_candidates_missing_in_scope' => $configuredMissing,
            'unconfigured_discovered_candidates' => $unconfigured,
            'excluded_discovered_candidates' => $excludedDiscovered,
        );
    }

    private function assertReadOnlyRuntime(OpenCartRuntimeConfig $runtimeConfig)
    {
        $runtimeMode = $runtimeConfig->getRuntimeMode();

        if ($runtimeMode !== 'db_readonly' && $runtimeMode !== 'live_db_readonly') {
            throw new \RuntimeException('pipeline_runtime_invalid');
        }

        $safety = $runtimeConfig->getSafety();

        if ($runtimeMode === 'live_db_readonly') {
            if (empty($safety['read_only'])
                || !isset($safety['allow_write']) || $safety['allow_write'] !== false
                || !isset($safety['allow_confirm_apply']) || $safety['allow_confirm_apply'] !== false
                || !isset($safety['production_ready']) || $safety['production_ready'] !== false
                || !isset($safety['cache_rebuild_allowed']) || $safety['cache_rebuild_allowed'] !== false
            ) {
                throw new \RuntimeException('pipeline_runtime_invalid');
            }
        }
    }

    private function filterExcluded(array $candidateIds, array $excludedIds)
    {
        $filtered = array();

        foreach ($candidateIds as $candidateId) {
            if (!in_array((int) $candidateId, $excludedIds, true)) {
                $filtered[] = (int) $candidateId;
            }
        }

        return $filtered;
    }

    private function getConfiguredCandidateIds(array $job)
    {
        if (isset($job['target']['included_alias_attribute_ids']) && is_array($job['target']['included_alias_attribute_ids'])) {
            return array_merge(array((int) $job['target']['canonical_attribute_id']), $job['target']['included_alias_attribute_ids']);
        }

        return $job['target']['candidate_attribute_ids'];
    }

    private function isUnchangedCanonicalValue(array $row, array $normalized, array $job)
    {
        $rawValue = trim(isset($row['raw_value']) ? (string) $row['raw_value'] : '');
        $canonicalValue = isset($normalized['canonical_value']) ? (string) $normalized['canonical_value'] : '';

        return (int) $row['attribute_id'] === (int) $job['target']['canonical_attribute_id']
            && ($rawValue === '220' || $rawValue === '380')
            && $canonicalValue === $rawValue;
    }

    private function enforceCanonicalValueContract(array $normalized, array $job)
    {
        if (!isset($normalized['canonical_value']) || $normalized['canonical_value'] === null) {
            return $normalized;
        }

        if (!isset($job['normalization']['allowed_canonical_values'])
            || !is_array($job['normalization']['allowed_canonical_values'])
        ) {
            return $normalized;
        }

        $canonicalValue = (string) $normalized['canonical_value'];

        if (in_array($canonicalValue, $job['normalization']['allowed_canonical_values'], true)) {
            $normalized['canonical_value'] = $canonicalValue;
            return $normalized;
        }

        $warnings = isset($normalized['warnings']) && is_array($normalized['warnings'])
            ? $normalized['warnings']
            : array();

        if (!in_array('canonical_value_outside_contract', $warnings, true)) {
            $warnings[] = 'canonical_value_outside_contract';
        }

        $normalized['status'] = 'review_required';
        $normalized['canonical_value'] = null;
        $normalized['warnings'] = $warnings;
        $normalized['ambiguity_reason'] = 'canonical_value_outside_contract';

        return $normalized;
    }

    private function extractDiscoveredIds(array $discovery)
    {
        $ids = array();

        foreach ($discovery['candidates'] as $candidate) {
            $ids[] = (int) $candidate['attribute_id'];
        }

        return $ids;
    }

    private function buildPlaceholders($prefix, array $values, array &$params)
    {
        $placeholders = array();
        $index = 0;

        foreach ($values as $value) {
            $key = ':' . $prefix . '_' . $index;
            $params[$key] = (int) $value;
            $placeholders[] = $key;
            $index++;
        }

        if (count($placeholders) === 0) {
            $key = ':' . $prefix . '_empty';
            $params[$key] = 0;
            $placeholders[] = $key;
        }

        return $placeholders;
    }

    private function buildGeneratedFilePaths($outputDirectory, $jobKey, $runId, array $fileNames)
    {
        $paths = array();
        $base = trim($outputDirectory, '/') . '/' . $jobKey . '/' . $runId;

        foreach ($fileNames as $fileName) {
            $paths[$fileName] = $base . '/' . $fileName;
        }

        return $paths;
    }

    private function createRunId()
    {
        return gmdate('YmdHis') . '_' . substr(str_replace('.', '', uniqid('', true)), 0, 12);
    }

    private function resolvePath($path)
    {
        $path = (string) $path;

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || strpos($path, '\\\\') === 0) {
            return $path;
        }

        return $this->baseDir . '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}
