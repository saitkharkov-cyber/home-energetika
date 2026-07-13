<?php

namespace FrameworkStandardization\Preview;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;

final class DbReadOnlyContractNormalizationPreview
{
    private $db;
    private $dbPrefix;
    private $contract;
    private $normalizer;

    public function __construct(ReadOnlyDbConnectionInterface $db, $dbPrefix, array $contract, $normalizer)
    {
        if (!is_object($normalizer) || !method_exists($normalizer, 'normalize')) {
            throw new \InvalidArgumentException('normalizer_invalid');
        }
        if (!is_string($dbPrefix) || $dbPrefix === '') {
            throw new \InvalidArgumentException('db_prefix_required');
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbPrefix)) {
            throw new \InvalidArgumentException('db_prefix_invalid');
        }
        $this->db = $db;
        $this->dbPrefix = $dbPrefix;
        $this->contract = $contract;
        $this->normalizer = $normalizer;
    }

    public function generate($runtimeMode)
    {
        $this->assertContract($runtimeMode);
        $scopeIds = $this->loadScopeIds();
        $productIds = $this->loadScopeProducts($scopeIds);
        $rows = $this->loadAttributeRows($scopeIds);
        $domain = $this->domain();
        $processed = array();
        $statusCounts = array('normalized' => 0, 'review_required' => 0, 'unsupported' => 0, 'invalid' => 0);
        $reasonCounts = array();
        $breakdown = $this->createBreakdown($domain);
        $valueCounts = array('canonical' => $this->emptyCounts($domain), 'alias' => $this->emptyCounts($domain), 'all' => $this->emptyCounts($domain));
        foreach ($rows as $row) {
            $item = $this->processRow($row, $domain);
            $processed[] = $item;
            $statusCounts[$item['status']]++;
            if ($item['reason'] !== '') $reasonCounts[$item['reason']] = isset($reasonCounts[$item['reason']]) ? $reasonCounts[$item['reason']] + 1 : 1;
            $id = $item['attribute_id'];
            $breakdown[$id]['rows']++;
            $breakdown[$id]['products'][$item['product_id']] = true;
            $breakdown[$id][$item['status']]++;
            if ($item['status'] === 'normalized') {
                $this->increment($breakdown[$id]['canonical_value_counts'], $item['canonical_value']);
                $this->increment($valueCounts[$item['source_role']], $item['canonical_value']);
                $this->increment($valueCounts['all'], $item['canonical_value']);
            }
        }
        ksort($reasonCounts, SORT_STRING);
        $canonicalId = (int) $this->contract['canonical_attribute_id'];
        $canonicalProducts = $breakdown[$canonicalId]['products'];
        $aliasProducts = array();
        foreach ($breakdown as $item) if ($item['source_role'] === 'alias') $aliasProducts += $item['products'];
        foreach ($breakdown as &$item) { $item['distinct_products'] = count($item['products']); unset($item['products']); $item['canonical_value_counts'] = $this->orderedCounts($item['canonical_value_counts'], $domain); } unset($item);
        $both = $this->bothAnalysis($processed);
        $evidence = $this->evidence(count($productIds), $breakdown, $valueCounts, $both['products'], count($processed) - $breakdown[$canonicalId]['rows']);
        $match = 1; foreach ($evidence as $check) if (!$check['match']) $match = 0;
        return array('runtime_mode' => $runtimeMode, 'command' => 'db_readonly_contract_normalization_preview', 'target_key' => $this->contract['target_key'], 'target_meaning' => $this->contract['target_meaning'], 'category_scope' => (int) $this->contract['category_scope_id'], 'category_scope_ids_count' => count($scopeIds), 'scope_distinct_products' => count($productIds), 'canonical_attribute_id' => $canonicalId, 'alias_attribute_ids' => $this->contract['alias_attribute_ids'], 'normalizer_key' => $this->contract['normalizer_key'], 'target_table' => $this->dbPrefix . 'product_attribute', 'total_attribute_rows' => count($processed), 'canonical_rows' => $breakdown[$canonicalId]['rows'], 'alias_rows' => count($processed) - $breakdown[$canonicalId]['rows'], 'canonical_distinct_products' => count($canonicalProducts), 'alias_distinct_products' => count($aliasProducts), 'products_with_both_attributes' => $both['products'], 'product_language_groups_with_both_attributes' => $both['groups'], 'products_with_both_same_normalized_value' => $both['same'], 'products_with_both_conflict_or_review' => $both['conflict'], 'normalization_status_counts' => $statusCounts, 'normalization_reason_counts' => $reasonCounts, 'canonical_value_counts' => array('canonical' => $this->orderedCounts($valueCounts['canonical'], $domain), 'alias' => $this->orderedCounts($valueCounts['alias'], $domain), 'all' => $this->orderedCounts($valueCounts['all'], $domain)), 'breakdown_by_attribute_id' => array_values($breakdown), 'evidence_checks' => $evidence, 'evidence_match' => $match, 'sample_rows' => array_slice($processed, 0, 20), 'safety_markers' => array('db_readonly'=>1,'select_only'=>1,'contract_driven'=>1,'normalization_preview_only'=>1,'pipeline_executed'=>0,'sql_generated'=>0,'apply_plan_created'=>0,'insert_executed'=>0,'update_executed'=>0,'delete_executed'=>0,'transaction_started'=>0,'product_data_changed'=>0,'contracts_changed'=>0,'production_touched'=>0,'cache_rebuild_performed'=>0));
    }

    private function assertContract($runtimeMode)
    {
        if ($runtimeMode !== 'db_readonly') throw new \InvalidArgumentException('runtime_mode_not_readonly');
        foreach (array('read_only_ready','apply_ready','scope_mode','allowed_table','category_scope_id','canonical_attribute_id','alias_attribute_ids','normalizer_key') as $field) if (!array_key_exists($field, $this->contract)) throw new \InvalidArgumentException('contract_missing_' . $field);
        if ($this->contract['read_only_ready'] !== true) throw new \InvalidArgumentException('contract_not_read_only_ready');
        if ($this->contract['apply_ready'] === true) throw new \InvalidArgumentException('contract_apply_ready');
        if ($this->contract['scope_mode'] !== 'hierarchical_category_path_exists') throw new \InvalidArgumentException('scope_mode_not_supported');
        if ($this->contract['allowed_table'] !== 'oc_product_attribute') throw new \InvalidArgumentException('allowed_table_not_supported');
        if ((int) $this->contract['category_scope_id'] <= 0) throw new \InvalidArgumentException('category_scope_id_invalid');
        if ((int) $this->contract['canonical_attribute_id'] <= 0) throw new \InvalidArgumentException('canonical_attribute_id_invalid');
        if (!is_array($this->contract['alias_attribute_ids']) || count($this->contract['alias_attribute_ids']) === 0) throw new \InvalidArgumentException('alias_attribute_ids_invalid');
        $seen = array(); foreach ($this->contract['alias_attribute_ids'] as $id) { if (!is_int($id) || $id <= 0) throw new \InvalidArgumentException('alias_attribute_id_invalid'); if ($id === (int) $this->contract['canonical_attribute_id']) throw new \InvalidArgumentException('alias_attribute_matches_canonical'); if (isset($seen[$id])) throw new \InvalidArgumentException('alias_attribute_id_duplicate'); $seen[$id] = true; }
        if (!is_string($this->contract['normalizer_key']) || trim($this->contract['normalizer_key']) === '') throw new \InvalidArgumentException('normalizer_key_invalid');
    }

    private function loadScopeIds() { $rows=$this->db->fetchAll('SELECT category_id, parent_id FROM '.$this->dbPrefix.'category ORDER BY category_id ASC',array()); $children=array(); foreach($rows as $row)$children[(int)$row['parent_id']][]=(int)$row['category_id']; $seen=array((int)$this->contract['category_scope_id']=>true); $queue=array_keys($seen); while($queue){$id=array_shift($queue);if(isset($children[$id]))foreach($children[$id] as $child)if(!isset($seen[$child])){$seen[$child]=true;$queue[]=$child;}}$ids=array_keys($seen);sort($ids,SORT_NUMERIC);return $ids; }
    private function loadScopeProducts(array $ids) { $params=array();$placeholders=$this->placeholders('category',$ids,$params);$rows=$this->db->fetchAll('SELECT DISTINCT product_id FROM '.$this->dbPrefix.'product_to_category WHERE category_id IN ('.implode(', ',$placeholders).') ORDER BY product_id ASC',$params);$set=array();foreach($rows as$row)$set[(int)$row['product_id']]=true;return $set; }
    private function loadAttributeRows(array $ids) { $attrs=array_merge(array((int)$this->contract['canonical_attribute_id']),$this->contract['alias_attribute_ids']);sort($attrs,SORT_NUMERIC);$params=array();$ap=$this->placeholders('attribute',$attrs,$params);$cp=$this->placeholders('category',$ids,$params);$sql='SELECT pa.product_id, pa.attribute_id, pa.language_id, pa.text FROM '.$this->dbPrefix.'product_attribute pa WHERE pa.attribute_id IN ('.implode(', ',$ap).') AND EXISTS (SELECT 1 FROM '.$this->dbPrefix.'product_to_category p2c WHERE p2c.product_id = pa.product_id AND p2c.category_id IN ('.implode(', ',$cp).')) ORDER BY pa.attribute_id ASC, pa.product_id ASC, pa.language_id ASC, pa.text ASC';return $this->db->fetchAll($sql,$params); }
    private function placeholders($prefix,array $values,array &$params){if(!count($values))throw new \InvalidArgumentException('placeholder_values_required');$r=array();foreach($values as$i=>$v){$k=':'.$prefix.'_'.$i;$params[$k]=(int)$v;$r[]=$k;}return $r;}
    private function domain(){if(!isset($this->contract['allowed_canonical_values']))return array();if(!is_array($this->contract['allowed_canonical_values']))throw new \InvalidArgumentException('allowed_canonical_values_invalid');$r=array();foreach($this->contract['allowed_canonical_values'] as$v){$v=(string)$v;if(!in_array($v,$r,true))$r[]=$v;}return $r;}
    private function processRow(array $row,array $domain){$id=(int)$row['attribute_id'];$canonical=(int)$this->contract['canonical_attribute_id'];if($id!==$canonical&&!in_array($id,$this->contract['alias_attribute_ids'],true))throw new \RuntimeException('attribute_row_outside_contract');$n=$this->adapt($this->normalizer->normalize($row['text']));if($n['status']==='normalized'&&count($domain)&&!in_array((string)$n['canonical_value'],$domain,true))$n=array('status'=>'review_required','canonical_value'=>$n['canonical_value'],'reason'=>'canonical_value_outside_contract_domain');return array('product_id'=>(int)$row['product_id'],'attribute_id'=>$id,'source_role'=>$id===$canonical?'canonical':'alias','language_id'=>(int)$row['language_id'],'raw_value'=>(string)$row['text'],'status'=>$n['status'],'canonical_value'=>$n['canonical_value'],'reason'=>$n['reason'],'raw_equals_canonical'=>$n['canonical_value']!==null&&(string)$row['text']===(string)$n['canonical_value']?1:0);}
    private function adapt($r){if(!is_array($r))throw new \RuntimeException('normalizer_result_schema_not_supported');if(isset($r['status'])&&array_key_exists('canonical_value',$r)&&array_key_exists('ambiguity_reason',$r)&&isset($r['warnings'])&&array_key_exists('metadata',$r)){if(!is_array($r['warnings']))throw new \RuntimeException('normalizer_result_schema_not_supported');if(!in_array($r['status'],array('normalized','review_required','unsupported','invalid'),true))throw new \RuntimeException('normalizer_result_status_not_supported');$reason=$r['ambiguity_reason']!==''?$r['ambiguity_reason']:'';if($reason===''&&isset($r['warnings'][0])&&$r['warnings'][0]!=='')$reason=$r['warnings'][0];return array('status'=>$r['status'],'canonical_value'=>$r['canonical_value'],'reason'=>$reason);}if(array_key_exists('normalized_value',$r)&&array_key_exists('reason',$r))return array('status'=>$r['normalized_value']===null?'unsupported':'normalized','canonical_value'=>$r['normalized_value'],'reason'=>(string)$r['reason']);throw new \RuntimeException('normalizer_result_schema_not_supported');}

    private function emptyCounts(array $domain)
    {
        $counts = array();
        foreach ($domain as $value) { $counts[(string) $value] = 0; }
        return $counts;
    }

    private function increment(array &$counts, $value)
    {
        $key = (string) $value;
        if (!isset($counts[$key])) { $counts[$key] = 0; }
        $counts[$key]++;
    }

    private function orderedCounts(array $counts, array $domain)
    {
        $ordered = $this->emptyCounts($domain);
        $extra = array();
        foreach ($counts as $key => $value) { if (array_key_exists($key, $ordered)) $ordered[$key] = $value; else $extra[$key] = $value; }
        ksort($extra, SORT_STRING);
        foreach ($extra as $key => $value) { $ordered[$key] = $value; }
        return $ordered;
    }

    private function createBreakdown(array $domain)
    {
        $ids = $this->contract['alias_attribute_ids'];
        sort($ids, SORT_NUMERIC);
        array_unshift($ids, (int) $this->contract['canonical_attribute_id']);
        $result = array();
        foreach ($ids as $id) {
            $result[$id] = array('attribute_id' => $id, 'source_role' => $id === (int) $this->contract['canonical_attribute_id'] ? 'canonical' : 'alias', 'rows' => 0, 'distinct_products' => 0, 'normalized' => 0, 'review_required' => 0, 'unsupported' => 0, 'invalid' => 0, 'canonical_value_counts' => $this->emptyCounts($domain), 'products' => array());
        }
        return $result;
    }

    private function bothAnalysis(array $processedRows)
    {
        $groups = array();
        foreach ($processedRows as $row) { $groups[$row['product_id'] . '|' . $row['language_id']][] = $row; }
        $products = array(); $same = array(); $conflict = array(); $groupCount = 0;
        foreach ($groups as $rows) {
            $canonical = array(); $aliases = array(); foreach ($rows as $row) { if ($row['source_role'] === 'canonical') $canonical[] = $row; else $aliases[] = $row; }
            if (!count($canonical) || !count($aliases)) continue;
            $groupCount++; $productId = $rows[0]['product_id']; $products[$productId] = true; $ok = true; $canonicalValues = array(); $aliasValues = array();
            foreach ($canonical as $row) { if ($row['status'] !== 'normalized') $ok = false; $canonicalValues[] = $row['canonical_value']; }
            foreach ($aliases as $row) { if ($row['status'] !== 'normalized') $ok = false; $aliasValues[] = $row['canonical_value']; }
            if ($ok && count(array_unique($canonicalValues)) === 1 && count(array_unique($aliasValues)) === 1 && $canonicalValues[0] === $aliasValues[0]) $same[$productId] = true; else $conflict[$productId] = true;
        }
        foreach ($conflict as $productId => $value) unset($same[$productId]);
        return array('products' => count($products), 'groups' => $groupCount, 'same' => count($same), 'conflict' => count($conflict));
    }

    private function evidence($scopeDistinctProducts, array $breakdown, array $valueCounts, $productsWithBoth, $aliasRows)
    {
        if (!isset($this->contract['evidence']) || !is_array($this->contract['evidence'])) return array();
        $evidence = $this->contract['evidence']; $checks = array(); $canonicalId = (int) $this->contract['canonical_attribute_id']; $canonicalKey = 'canonical_attribute_' . $canonicalId;
        if (isset($evidence['scope_distinct_products'])) $this->appendEvidenceCheck($checks, 'scope_distinct_products', $evidence['scope_distinct_products'], $scopeDistinctProducts);
        if (isset($evidence[$canonicalKey])) { $entry = $evidence[$canonicalKey]; if (isset($entry['distinct_products'])) $this->appendEvidenceCheck($checks, $canonicalKey . '_distinct_products', $entry['distinct_products'], $breakdown[$canonicalId]['distinct_products']); if (isset($entry['values'])) foreach ($entry['values'] as $value => $count) $this->appendEvidenceCheck($checks, $canonicalKey . '_value_' . $value, $count, isset($valueCounts['canonical'][$value]) ? $valueCounts['canonical'][$value] : 0); }
        foreach ($this->contract['alias_attribute_ids'] as $aliasId) { $aliasKey = 'alias_attribute_' . $aliasId; if (!isset($evidence[$aliasKey])) continue; $entry = $evidence[$aliasKey]; if (isset($entry['distinct_products'])) $this->appendEvidenceCheck($checks, $aliasKey . '_distinct_products', $entry['distinct_products'], $breakdown[$aliasId]['distinct_products']); if (isset($entry['values'])) foreach ($entry['values'] as $value => $count) $this->appendEvidenceCheck($checks, $aliasKey . '_value_' . $value, $count, isset($breakdown[$aliasId]['canonical_value_counts'][$value]) ? $breakdown[$aliasId]['canonical_value_counts'][$value] : 0); }
        if (isset($evidence['products_with_both_attributes'])) $this->appendEvidenceCheck($checks, 'products_with_both_attributes', $evidence['products_with_both_attributes'], $productsWithBoth);
        if (isset($evidence['observed_alias_rows_before_migration'])) $this->appendEvidenceCheck($checks, 'observed_alias_rows_before_migration', $evidence['observed_alias_rows_before_migration'], $aliasRows);
        if (!count($checks)) $checks[] = array('name' => 'supported_evidence_checks', 'expected' => 1, 'actual' => 0, 'match' => 0);
        return $checks;
    }

    private function appendEvidenceCheck(array &$checks, $name, $expected, $actual)
    {
        $checks[] = array('name' => $name, 'expected' => $expected, 'actual' => $actual, 'match' => (int) $expected === (int) $actual ? 1 : 0);
    }
}
