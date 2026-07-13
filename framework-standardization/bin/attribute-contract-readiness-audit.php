<?php

require dirname(__DIR__) . '/src/Normalizer/SimpleMetersNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/VoltageNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/NormalizerRegistry.php';
require dirname(__DIR__) . '/src/Contract/AttributeContractReadinessAuditor.php';

use FrameworkStandardization\Contract\AttributeContractReadinessAuditor;
use FrameworkStandardization\Normalizer\NormalizerRegistry;

try {
    $directory = isset($argv[1]) ? $argv[1] : dirname(__DIR__) . '/config/attribute-contracts';
    $auditor = new AttributeContractReadinessAuditor(NormalizerRegistry::createDefault());
    $result = $auditor->audit($directory);
    echo "attribute_contract_readiness_audit\ncontracts_directory: " . $directory . "\n";
    foreach (array('total_contracts', 'ready_contracts', 'not_ready_contracts', 'invalid_contracts', 'contracts_with_issues') as $key) { echo $key . ': ' . $result[$key] . "\n"; }
    foreach ($result['items'] as $item) { echo "\n" . $item['target_key'] . "\ncontract_file: " . $item['contract_file'] . "\nstatus: " . $item['status'] . "\nnormalizer_key: " . $item['normalizer_key'] . "\nnormalizer_registered: " . ($item['normalizer_registered'] ? '1' : '0') . "\ndeclared_normalizer_ready: " . var_export($item['declared_normalizer_ready'], true) . "\ndeclared_read_only_ready: " . var_export($item['declared_read_only_ready'], true) . "\ndeclared_apply_ready: " . var_export($item['declared_apply_ready'], true) . "\nissues: " . (count($item['issues']) ? implode(',', $item['issues']) : 'none') . "\n"; }
    echo "attribute_contract_readiness_audit: ok\n";
} catch (Exception $e) { echo $e->getMessage() . "\n"; exit(1); }
