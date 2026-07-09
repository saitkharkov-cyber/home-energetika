<?php

namespace FrameworkStandardization\Contract;

final class AttributeContractLoader
{
    public function load($path)
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException('contract_not_found');
        }

        $contract = require $path;

        if (!is_array($contract)) {
            throw new \InvalidArgumentException('contract_must_return_array');
        }

        $this->validateShape($contract);

        return $contract;
    }

    public function assertRuntimeAllowed(array $contract, $runtimeConfig)
    {
        $database = $runtimeConfig->getDatabase();
        $runtimeAllowlist = $contract['runtime_allowlist'];

        foreach ($runtimeAllowlist as $runtime) {
            if (!is_array($runtime)) {
                continue;
            }

            if ((string) $runtimeConfig->getRuntimeMode() !== (string) $runtime['runtime_mode']) {
                continue;
            }

            if (!isset($database['host']) || (string) $database['host'] !== (string) $runtime['host']) {
                continue;
            }

            if (!isset($database['dbname']) || (string) $database['dbname'] !== (string) $runtime['dbname']) {
                continue;
            }

            if ((string) $runtimeConfig->getDbPrefix() !== (string) $runtime['db_prefix']) {
                continue;
            }

            return;
        }

        throw new \RuntimeException('runtime_not_allowed_by_contract');
    }

    private function validateShape(array $contract)
    {
        $required = array(
            'target_key',
            'target_meaning',
            'category_scope_id',
            'canonical_attribute_id',
            'alias_attribute_ids',
            'normalizer_key',
            'allowed_table',
            'expected_alias_total_rows_after_cleanup',
            'expected_alias_safely_removable_after_cleanup',
            'expected_alias_not_removable_after_cleanup',
            'expected_alias_unresolved_or_excluded_after_cleanup',
            'runtime_allowlist',
        );

        foreach ($required as $key) {
            if (!array_key_exists($key, $contract)) {
                throw new \InvalidArgumentException('contract_missing_' . $key);
            }
        }

        if (!is_array($contract['alias_attribute_ids']) || count($contract['alias_attribute_ids']) === 0) {
            throw new \InvalidArgumentException('contract_invalid_alias_attribute_ids');
        }

        if (!is_array($contract['runtime_allowlist']) || count($contract['runtime_allowlist']) === 0) {
            throw new \InvalidArgumentException('contract_invalid_runtime_allowlist');
        }

        if ((string) $contract['allowed_table'] !== 'oc_product_attribute') {
            throw new \InvalidArgumentException('contract_allowed_table_not_supported');
        }
    }
}
