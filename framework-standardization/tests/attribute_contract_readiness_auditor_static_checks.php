<?php

require dirname(__DIR__) . '/src/Normalizer/SimpleMetersNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/VoltageNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/BooleanYesNoNormalizer.php';
require dirname(__DIR__) . '/src/Normalizer/NormalizerRegistry.php';
require dirname(__DIR__) . '/src/Contract/AttributeContractReadinessAuditor.php';

use FrameworkStandardization\Contract\AttributeContractReadinessAuditor;
use FrameworkStandardization\Normalizer\NormalizerRegistry;

function acra_check($label, $condition) { if (!$condition) { echo $label . ": failed\n"; exit(1); } }
function acra_item(array $result, $key) { foreach ($result['items'] as $item) { if ($item['target_key'] === $key) { return $item; } } return null; }
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'acra_' . uniqid(); mkdir($dir);
$fixtures = array(
    'a_ready.php' => "<?php return array('target_key'=>'a_ready','normalizer_key'=>'boolean_yes_no','normalizer_ready'=>true,'read_only_ready'=>true,'apply_ready'=>false);",
    'b_empty.php' => "<?php return array('target_key'=>'b_empty','normalizer_key'=>'');",
    'c_unknown.php' => "<?php return array('target_key'=>'c_unknown','normalizer_key'=>'missing');",
    'd_unknown_ready.php' => "<?php return array('target_key'=>'d_unknown_ready','normalizer_key'=>'missing','normalizer_ready'=>true);",
    'e_false_readonly.php' => "<?php return array('target_key'=>'e_false_readonly','normalizer_key'=>'boolean_yes_no','normalizer_ready'=>false,'read_only_ready'=>true);",
    'f_missing_readonly.php' => "<?php return array('target_key'=>'f_missing_readonly','normalizer_key'=>'boolean_yes_no','read_only_ready'=>true);",
    'g_apply.php' => "<?php return array('target_key'=>'g_apply','normalizer_key'=>'missing','normalizer_ready'=>true,'read_only_ready'=>true,'apply_ready'=>true);",
    'h_invalid.php' => "<?php return 'bad';",
    'i_missing.php' => "<?php return array('normalizer_key'=>'');",
);
foreach ($fixtures as $name => $contents) { file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $contents); }
$auditor = new AttributeContractReadinessAuditor(NormalizerRegistry::createDefault()); $result = $auditor->audit($dir);
acra_check('summary', $result['total_contracts'] === 9 && $result['ready_contracts'] === 1 && $result['not_ready_contracts'] === 6 && $result['invalid_contracts'] === 2 && $result['contracts_with_issues'] === 7);
acra_check('sorted', $result['items'][0]['target_key'] === 'a_ready' && $result['items'][8]['status'] === 'invalid');
acra_check('ready', acra_item($result, 'a_ready')['status'] === 'ready');
acra_check('empty', acra_item($result, 'b_empty')['status'] === 'not_ready' && acra_item($result, 'b_empty')['issues'] === array());
acra_check('unknown', in_array('unknown_normalizer_key', acra_item($result, 'c_unknown')['issues'], true));
acra_check('unknown_ready', in_array('normalizer_ready_without_registered_normalizer', acra_item($result, 'd_unknown_ready')['issues'], true));
acra_check('false_readonly', in_array('read_only_ready_without_normalizer_ready', acra_item($result, 'e_false_readonly')['issues'], true));
acra_check('missing_readonly', in_array('read_only_ready_without_normalizer_ready', acra_item($result, 'f_missing_readonly')['issues'], true));
acra_check('apply', in_array('apply_ready_with_readiness_issues', acra_item($result, 'g_apply')['issues'], true));
acra_check('invalid_return_type', $result['items'][7]['status'] === 'invalid' && in_array('contract_must_return_array', $result['items'][7]['issues'], true));
acra_check('missing_target_key', $result['items'][8]['status'] === 'invalid' && in_array('target_key_required', $result['items'][8]['issues'], true));
foreach ($result['items'] as $item) { foreach (array('sql','apply_plan','product','password','cache') as $field) { acra_check('safe_result_' . $field, !array_key_exists($field, $item)); } }
$real = $auditor->audit(dirname(__DIR__) . '/config/attribute-contracts'); $dry = acra_item($real, 'dry_run_protection'); $max = acra_item($real, 'max_head');
acra_check('dry', $dry['normalizer_key'] === 'boolean_yes_no' && $dry['normalizer_key_present'] === true && $dry['normalizer_registered'] === true && $dry['declared_normalizer_ready'] === true && $dry['declared_read_only_ready'] === true && $dry['declared_apply_ready'] === false && $dry['status'] === 'ready' && $dry['issues'] === array());
acra_check('max', $max['normalizer_key'] === 'simple_meters' && $max['normalizer_registered'] === true && $max['declared_normalizer_ready'] === null && $max['declared_read_only_ready'] === null && $max['declared_apply_ready'] === null && $max['status'] === 'not_ready' && $max['issues'] === array());
foreach (glob($dir . '/*.php') as $file) { unlink($file); } rmdir($dir);
echo "attribute_contract_readiness_auditor_static_checks: ok\n";
