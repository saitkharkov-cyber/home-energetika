<?php

namespace FrameworkStandardization\Pipeline;

final class ReviewPackageWriter
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = rtrim(str_replace('\\', '/', (string) $baseDir), '/');
    }

    public function write(array $package)
    {
        $outputDirectory = $this->baseDir . '/' . trim($package['output_directory'], '/');
        $runDirectory = $outputDirectory . '/' . $package['job_key'] . '/' . $package['run_id'];

        if (!is_dir($runDirectory) && !mkdir($runDirectory, 0777, true)) {
            throw new \RuntimeException('pipeline_output_directory_create_failed');
        }

        $files = $this->buildFiles($package);

        $written = array();

        foreach ($files as $fileName => $content) {
            $path = $runDirectory . '/' . $fileName;

            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException('pipeline_output_file_write_failed');
            }

            $written[$fileName] = $path;
        }

        return array(
            'run_directory' => $runDirectory,
            'files' => $written,
        );
    }

    public function buildFiles(array $package)
    {
        return array(
            'summary.md' => $this->renderSummary($package),
            'discovery.md' => $this->renderDiscovery($package),
            'inventory.md' => $this->renderInventory($package),
            'proposals.md' => $this->renderProposals($package),
            'manifest.json' => $this->renderJson($package['manifest']),
            'inventory.json' => $this->renderJson($package['inventory']),
            'proposals.json' => $this->renderJson($package['proposals']),
        );
    }

    public function getFileNames()
    {
        return array(
            'summary.md',
            'discovery.md',
            'inventory.md',
            'proposals.md',
            'manifest.json',
            'inventory.json',
            'proposals.json',
        );
    }

    private function renderSummary(array $package)
    {
        $manifest = $package['manifest'];
        $lines = array();
        $counts = $manifest['counts'];
        $discovery = isset($manifest['discovery']) ? $manifest['discovery'] : array();
        $inventoryCounts = isset($manifest['inventory']) ? $manifest['inventory'] : array();
        $proposalTypes = isset($counts['proposal_value_types']) ? $counts['proposal_value_types'] : array();
        $target = $manifest['target'];
        $lines[] = '# Standardization review summary';
        $lines[] = '';
        $lines[] = '## Run';
        $lines[] = '';
        $lines[] = '- job_key: ' . $manifest['job_key'];
        $lines[] = '- run_id: ' . $manifest['run_id'];
        $lines[] = '- start_time: ' . $manifest['start_time'];
        $lines[] = '- end_time: ' . $manifest['end_time'];
        $lines[] = '- final_pipeline_status: ' . $manifest['final_pipeline_status'];
        $lines[] = '';
        $lines[] = '## Runtime';
        $lines[] = '';
        $lines[] = '- runtime_mode: ' . $manifest['runtime_mode'];
        $lines[] = '- database_name: ' . $manifest['database_name'];
        $lines[] = '- connection_sensitive_fields: hidden';
        $lines[] = '- category_ids: ' . implode(',', $manifest['scope']['category_ids']);
        $lines[] = '';
        $lines[] = '## Target';
        $lines[] = '';
        $lines[] = '- search_terms: ' . implode(', ', $target['search_terms']);
        $lines[] = '- canonical_attribute_id: ' . $target['canonical_attribute_id'];
        $lines[] = '- canonical_attribute_name: ' . $this->readNested($manifest, array('target', 'canonical_attribute_name'), 'unknown');
        $lines[] = '- configured_candidate_ids: ' . implode(',', $target['candidate_attribute_ids']);
        $lines[] = '- excluded_attribute_ids: ' . implode(',', $target['excluded_attribute_ids']);
        $lines[] = '- normalizer_key: ' . $manifest['normalizer_key'];
        $lines[] = '- canonical_unit: ' . $this->readNested($manifest, array('normalization', 'canonical_unit'), 'unknown');
        $lines[] = '';
        $lines[] = '## Discovery';
        $lines[] = '';
        $lines[] = '- candidates_found: ' . $counts['discovery_candidates'];
        $lines[] = '- configured_candidates_found_in_scope: ' . $this->joinOrNone($this->readArray($discovery, 'configured_candidates_found_in_scope'));
        $lines[] = '- configured_candidates_missing_in_scope: ' . $this->joinOrNone($this->readArray($discovery, 'configured_candidates_missing_in_scope'));
        $lines[] = '- unconfigured_discovered_candidates: ' . $this->joinOrNone($this->readArray($discovery, 'unconfigured_discovered_candidates'));
        $lines[] = '- excluded_discovered_candidates: ' . $this->joinOrNone($this->readArray($discovery, 'excluded_discovered_candidates'));
        $lines[] = '';
        $lines[] = '## Inventory';
        $lines[] = '';
        $lines[] = '| attribute_id | name | group | rows | distinct_products | unique_raw_values | blank_values |';
        $lines[] = '| --- | --- | --- | --- | --- | --- | --- |';

        foreach ($package['inventory']['attributes'] as $attribute) {
            $lines[] = '| ' . $this->cell($attribute['attribute_id'])
                . ' | ' . $this->cell($attribute['attribute_name'])
                . ' | ' . $this->cell($attribute['attribute_group_name'])
                . ' | ' . $this->cell($attribute['row_count'])
                . ' | ' . $this->cell($attribute['distinct_product_count'])
                . ' | ' . $this->cell($attribute['unique_raw_values_count'])
                . ' | ' . $this->cell($attribute['blank_values_count']) . ' |';
        }

        $lines[] = '';
        $lines[] = '- inventory_attributes: ' . $counts['inventory_attributes'];
        $lines[] = '- total_inventory_rows: ' . $this->readValue($inventoryCounts, 'total_inventory_rows', 0);
        $lines[] = '- products_with_multiple_configured_voltage_attributes: ' . $this->readValue($inventoryCounts, 'products_with_multiple_configured_candidate_attributes', 0);
        $lines[] = '';
        $lines[] = '## Proposals';
        $lines[] = '';
        $lines[] = '- total: ' . $counts['proposals_total'];

        foreach ($manifest['counts']['proposal_statuses'] as $status => $count) {
            $lines[] = '- ' . $status . ': ' . $count;
        }

        $lines[] = '';
        $lines[] = '## Review warnings';
        $lines[] = '';

        if (count($manifest['warnings']) === 0) {
            $lines[] = '- none';
        } else {
            foreach ($manifest['warnings'] as $warning) {
                $lines[] = '- ' . $warning;
            }
        }

        $lines[] = '- compound_count: ' . $this->readValue($proposalTypes, 'compound', 0);
        $lines[] = '- range_count: ' . $this->readValue($proposalTypes, 'range', 0);
        $lines[] = '- blank_invalid_values: ' . $this->readValue($manifest['counts']['proposal_statuses'], 'invalid', 0);
        $lines[] = '';
        $lines[] = '## Safety markers';
        $lines[] = '';
        $lines[] = '```text';

        foreach ($manifest['safety_markers'] as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }

        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## Review package files';
        $lines[] = '';

        foreach ($manifest['generated_files'] as $fileName => $path) {
            if ($fileName === 'summary.md') {
                continue;
            }

            $lines[] = '- [' . $fileName . '](' . $this->relativeLink($path) . ')';
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderDiscovery(array $package)
    {
        $lines = array('# Discovery', '');
        $lines[] = '| attribute_id | name | group | usage_count | reason_found | possible_role | warnings | raw_samples |';
        $lines[] = '| --- | --- | --- | --- | --- | --- | --- | --- |';

        foreach ($package['discovery']['candidates'] as $candidate) {
            $lines[] = '| ' . $this->cell($candidate['attribute_id'])
                . ' | ' . $this->cell($candidate['attribute_name'])
                . ' | ' . $this->cell($candidate['attribute_group_name'])
                . ' | ' . $this->cell($candidate['usage_count'])
                . ' | ' . $this->cell($candidate['reason_found'])
                . ' | ' . $this->cell($candidate['possible_role'])
                . ' | ' . $this->cell(implode(', ', $candidate['warnings']))
                . ' | ' . $this->cell(implode(', ', $candidate['raw_samples'])) . ' |';
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderInventory(array $package)
    {
        $lines = array('# Raw values inventory', '');

        foreach ($package['inventory']['attributes'] as $attribute) {
            $lines[] = '## Attribute ' . $attribute['attribute_id'] . ' - ' . $attribute['attribute_name'];
            $lines[] = '';
            $lines[] = '- group: ' . $attribute['attribute_group_name'];
            $lines[] = '- row_count: ' . $attribute['row_count'];
            $lines[] = '- distinct_product_count: ' . $attribute['distinct_product_count'];
            $lines[] = '- unique_raw_values_count: ' . $attribute['unique_raw_values_count'];
            $lines[] = '- blank_values_count: ' . $attribute['blank_values_count'];
            $lines[] = '';
            $lines[] = '| raw_value | frequency | sample_product_ids | warnings |';
            $lines[] = '| --- | --- | --- | --- |';

            foreach ($attribute['raw_values'] as $rawValue) {
                $lines[] = '| ' . $this->cell($rawValue['raw_value'])
                    . ' | ' . $this->cell($rawValue['count'])
                    . ' | ' . $this->cell(implode(', ', $rawValue['sample_product_ids']))
                    . ' | ' . $this->cell(implode(', ', $rawValue['warnings'])) . ' |';
            }

            $lines[] = '';
        }

        $lines[] = '## Overlap';
        $lines[] = '';
        $lines[] = '- products_with_multiple_configured_candidate_attributes: ' . $package['inventory']['overlap']['products_with_multiple_configured_candidate_attributes'];
        $lines[] = '- canonical_attribute_coverage: ' . $package['inventory']['overlap']['canonical_attribute_coverage'];
        $lines[] = '- alias_candidate_coverage: ' . $package['inventory']['overlap']['alias_candidate_coverage'];

        return implode("\n", $lines) . "\n";
    }

    private function renderProposals(array $package)
    {
        $lines = array('# Normalization proposals', '');
        $lines[] = '| product_id | source_attribute_id | source_attribute_name | raw_value | status | value_type | canonical_value | warnings | ambiguity_reason |';
        $lines[] = '| --- | --- | --- | --- | --- | --- | --- | --- | --- |';

        foreach ($package['proposals']['items'] as $proposal) {
            $lines[] = '| ' . $this->cell($proposal['product_id'])
                . ' | ' . $this->cell($proposal['source_attribute_id'])
                . ' | ' . $this->cell($proposal['source_attribute_name'])
                . ' | ' . $this->cell($proposal['raw_value'])
                . ' | ' . $this->cell($proposal['proposal_status'])
                . ' | ' . $this->cell($proposal['normalized_result']['value_type'])
                . ' | ' . $this->cell($proposal['normalized_result']['canonical_value'])
                . ' | ' . $this->cell(implode(', ', $proposal['warnings']))
                . ' | ' . $this->cell($proposal['ambiguity_reason']) . ' |';
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderJson(array $data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function readNested(array $source, array $path, $default)
    {
        $current = $source;

        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    private function readArray(array $source, $key)
    {
        if (!isset($source[$key]) || !is_array($source[$key])) {
            return array();
        }

        return $source[$key];
    }

    private function readValue(array $source, $key, $default)
    {
        return array_key_exists($key, $source) ? $source[$key] : $default;
    }

    private function joinOrNone(array $values)
    {
        if (count($values) === 0) {
            return 'none';
        }

        return implode(',', $values);
    }

    private function relativeLink($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $parts = explode('/', $path);

        return end($parts);
    }

    private function cell($value)
    {
        if ($value === null || $value === '') {
            $value = 'none';
        }

        $value = str_replace(array("\r\n", "\r", "\n"), ' ', (string) $value);

        return str_replace('|', '\\|', $value);
    }
}
