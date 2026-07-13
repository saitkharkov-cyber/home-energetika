<?php

namespace FrameworkStandardization\Contract;

final class AttributeContractReadinessAuditor
{
    private $normalizers;

    public function __construct($normalizers)
    {
        if (!is_object($normalizers) || !method_exists($normalizers, 'has')) {
            throw new \InvalidArgumentException('attribute_contract_auditor_normalizer_registry_invalid');
        }

        $this->normalizers = $normalizers;
    }

    public function audit($directory)
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException('attribute_contract_auditor_directory_invalid');
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php');
        sort($files, SORT_STRING);
        $items = array();

        foreach ($files as $file) {
            $items[] = $this->auditFile($file);
        }

        $result = array(
            'total_contracts' => count($items),
            'ready_contracts' => 0,
            'not_ready_contracts' => 0,
            'invalid_contracts' => 0,
            'contracts_with_issues' => 0,
            'items' => $items,
        );

        foreach ($items as $item) {
            $result[$item['status'] . '_contracts']++;
            if (count($item['issues']) > 0) {
                $result['contracts_with_issues']++;
            }
        }

        return $result;
    }

    private function auditFile($file)
    {
        $base = array('contract_file' => $file, 'target_key' => null, 'contract_status' => null, 'normalizer_key' => null, 'normalizer_key_present' => false, 'normalizer_registered' => false, 'declared_normalizer_ready' => null, 'declared_read_only_ready' => null, 'declared_apply_ready' => null, 'issues' => array());

        try {
            $contract = require $file;
        } catch (\Exception $e) {
            $base['issues'][] = 'contract_load_failed';
            return $this->invalid($base);
        }

        if (!is_array($contract)) {
            $base['issues'][] = 'contract_must_return_array';
            return $this->invalid($base);
        }
        if (!isset($contract['target_key']) || !is_string($contract['target_key']) || trim($contract['target_key']) === '') {
            $base['issues'][] = 'target_key_required';
            return $this->invalid($base);
        }

        $base['target_key'] = $contract['target_key'];
        $base['contract_status'] = isset($contract['contract_status']) ? $contract['contract_status'] : null;
        $base['normalizer_key'] = isset($contract['normalizer_key']) ? trim((string) $contract['normalizer_key']) : '';
        $base['normalizer_key_present'] = $base['normalizer_key'] !== '';
        $base['normalizer_registered'] = $base['normalizer_key_present'] && $this->normalizers->has($base['normalizer_key']);

        foreach (array('normalizer_ready' => 'declared_normalizer_ready', 'read_only_ready' => 'declared_read_only_ready', 'apply_ready' => 'declared_apply_ready') as $field => $output) {
            if (array_key_exists($field, $contract)) {
                $base[$output] = $contract[$field];
            }
        }

        if ($base['normalizer_key_present'] && !$base['normalizer_registered']) {
            $base['issues'][] = 'unknown_normalizer_key';
        }
        if ($base['declared_normalizer_ready'] === true && !$base['normalizer_registered']) {
            $base['issues'][] = 'normalizer_ready_without_registered_normalizer';
        }
        if ($base['declared_read_only_ready'] === true && (!$base['normalizer_registered'] || $base['declared_normalizer_ready'] !== true)) {
            $base['issues'][] = 'read_only_ready_without_normalizer_ready';
        }
        if ($base['declared_apply_ready'] === true && count($base['issues']) > 0) {
            $base['issues'][] = 'apply_ready_with_readiness_issues';
        }

        $base['status'] = $base['normalizer_registered'] && $base['declared_normalizer_ready'] === true && $base['declared_read_only_ready'] === true && count($base['issues']) === 0 ? 'ready' : 'not_ready';
        return $base;
    }

    private function invalid(array $item)
    {
        $item['status'] = 'invalid';
        return $item;
    }
}
