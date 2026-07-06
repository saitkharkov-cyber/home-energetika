<?php
	
	namespace FrameworkStandardization\Analyzer;
	
	use FrameworkStandardization\Contract\AttributeValueAnalyzerInterface;
	
	final class DbReadOnlyAttributeValueAnalyzer implements AttributeValueAnalyzerInterface
	{
		public function analyze(array $canonical, array $rawValues, array $valueRules)
		{
			if ($rawValues === array()) {
				return $this->failed(array('raw_values_missing'));
			}
			
			if (!isset($canonical['canonical_code']) || $canonical['canonical_code'] !== 'pump_diameter') {
				return $this->failed(array('value_analysis_failed'));
			}
			
			if (!isset($valueRules['value_parser']) || $valueRules['value_parser'] !== 'diameter_mm') {
				return $this->failed(array('value_parser_unknown'));
			}
			
			$emptyValues = array();
			
			foreach ($rawValues as $rawValue) {
				if (!isset($rawValue['product_id']) || (int)$rawValue['product_id'] <= 0) {
					return $this->failed(array('value_analysis_failed'));
				}
				
				if (!isset($rawValue['attribute_id']) || (int)$rawValue['attribute_id'] <= 0) {
					return $this->failed(array('value_analysis_failed'));
				}
				
				if (!isset($rawValue['raw_text']) || trim((string)$rawValue['raw_text']) === '') {
					$emptyValues[] = $rawValue;
				}
			}
			
			$rawProfile = $this->buildRawProfile($rawValues, $emptyValues);
			
			$attributeValueStructure = array(
            'raw_values' => $rawValues,
            'normalized_values' => array(),
            'unknown_values' => array(),
            'invalid_values' => array(),
            'empty_values' => $emptyValues,
            'diagnostics' => array(
			'total_values' => count($rawValues),
			'normalized_count' => 0,
			'unknown_count' => 0,
			'invalid_count' => 0,
			'empty_count' => count($emptyValues),
			'unique_normalized_values' => array(),
			'raw_profile' => $rawProfile,
			'source' => 'local_dump_db_readonly',
            ),
			);
			
			return array(
            'analyzed' => 1,
            'attribute_value_structure' => $attributeValueStructure,
            'value_report' => array(
			'parser' => 'diameter_mm',
			'value_type' => isset($valueRules['value_type']) ? $valueRules['value_type'] : 'decimal',
			'unit' => isset($valueRules['unit']) ? $valueRules['unit'] : 'mm',
			'total_values' => count($rawValues),
			'normalized_values' => 0,
			'unknown_values' => 0,
			'invalid_values' => 0,
			'empty_values' => count($emptyValues),
			'unique_raw_values_count' => $rawProfile['unique_raw_values_count'],
			'top_raw_values' => $rawProfile['top_raw_values'],
			'examples' => $this->buildExamples($rawValues),
			'note' => 'db_readonly_values_not_normalized',
			'profiling_note' => 'db_readonly_raw_value_profiling_only',
            ),
            'errors' => array(),
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
			);
		}
		
		private function buildRawProfile(array $rawValues, array $emptyValues)
		{
			$frequencies = array();
			$exampleProductIdsByRawText = array();
			$containsDigitsCount = 0;
			$containsUnitMmCount = 0;
			$suspiciousNoDigitsCount = 0;
			$suspiciousLongValueCount = 0;
			$suspiciousMultipleNumbersCount = 0;
			$minRawLength = null;
			$maxRawLength = 0;
			$totalRawLength = 0;
			
			foreach ($rawValues as $rawValue) {
				$rawText = isset($rawValue['raw_text']) ? (string)$rawValue['raw_text'] : '';
				$profileText = trim($rawText);
				$rawLength = strlen($rawText);
				$hasDigits = preg_match('/[0-9]/', $rawText) === 1;
				$numberFragmentCount = preg_match_all('/[0-9]+(?:[.,][0-9]+)?/', $rawText, $matches);
				
				if (!isset($frequencies[$profileText])) {
					$frequencies[$profileText] = 0;
					$exampleProductIdsByRawText[$profileText] = array();
				}
				
				$frequencies[$profileText]++;
				
				if (isset($rawValue['product_id']) && count($exampleProductIdsByRawText[$profileText]) < 3) {
					$productId = (int)$rawValue['product_id'];
					
					if (!in_array($productId, $exampleProductIdsByRawText[$profileText], true)) {
						$exampleProductIdsByRawText[$profileText][] = $productId;
					}
				}
				
				if ($hasDigits) {
					$containsDigitsCount++;
				}
				
				if ($this->containsUnitMm($rawText)) {
					$containsUnitMmCount++;
				}
				
				if ($profileText !== '' && !$hasDigits) {
					$suspiciousNoDigitsCount++;
				}
				
				if ($rawLength > 64) {
					$suspiciousLongValueCount++;
				}
				
				if ($numberFragmentCount > 1) {
					$suspiciousMultipleNumbersCount++;
				}
				
				if ($minRawLength === null || $rawLength < $minRawLength) {
					$minRawLength = $rawLength;
				}
				
				if ($rawLength > $maxRawLength) {
					$maxRawLength = $rawLength;
				}
				
				$totalRawLength += $rawLength;
			}
			
			$totalValues = count($rawValues);
			
			return array(
            'total_values' => $totalValues,
            'unique_raw_values_count' => count($frequencies),
            'empty_values_count' => count($emptyValues),
            'top_raw_values' => $this->buildTopRawValues($frequencies, $exampleProductIdsByRawText),
            'raw_value_frequencies' => $frequencies,
            'examples' => $this->buildExamples($rawValues),
            'min_raw_length' => $minRawLength === null ? 0 : $minRawLength,
            'max_raw_length' => $maxRawLength,
            'avg_raw_length' => $totalValues > 0 ? round($totalRawLength / $totalValues, 2) : 0,
            'contains_digits_count' => $containsDigitsCount,
            'contains_unit_mm_count' => $containsUnitMmCount,
            'suspicious_no_digits_count' => $suspiciousNoDigitsCount,
            'suspicious_long_value_count' => $suspiciousLongValueCount,
            'suspicious_multiple_numbers_count' => $suspiciousMultipleNumbersCount,
            'source' => 'local_dump_db_readonly',
			);
		}
		
		private function buildTopRawValues(array $frequencies, array $exampleProductIdsByRawText)
		{
			arsort($frequencies);
			
			$topRawValues = array();
			
			foreach ($frequencies as $rawText => $count) {
				if (count($topRawValues) >= 20) {
					break;
				}
				
				$topRawValues[] = array(
                'raw_text' => (string)$rawText,
                'count' => (int)$count,
                'example_product_ids' => isset($exampleProductIdsByRawText[$rawText]) ? $exampleProductIdsByRawText[$rawText] : array(),
				);
			}
			
			return $topRawValues;
		}
		
		private function containsUnitMm($rawText)
		{
			if (stripos($rawText, 'mm') !== false) {
				return true;
			}
			
			if (
			strpos($rawText, 'мм') !== false ||
			strpos($rawText, 'ММ') !== false ||
			strpos($rawText, 'Мм') !== false ||
			strpos($rawText, 'мМ') !== false
			) {
				return true;
			}
			
			return false;
		}
		
		private function buildExamples(array $rawValues)
		{
			$examples = array();
			
			foreach ($rawValues as $rawValue) {
				if (count($examples) >= 5) {
					break;
				}
				
				$examples[] = array(
                'product_id' => isset($rawValue['product_id']) ? (int)$rawValue['product_id'] : 0,
                'attribute_id' => isset($rawValue['attribute_id']) ? (int)$rawValue['attribute_id'] : 0,
                'raw_text' => isset($rawValue['raw_text']) ? (string)$rawValue['raw_text'] : '',
				);
			}
			
			return $examples;
		}
		
		private function failed(array $errors)
		{
			return array(
            'analyzed' => 0,
            'attribute_value_structure' => array(
			'raw_values' => array(),
			'normalized_values' => array(),
			'unknown_values' => array(),
			'invalid_values' => array(),
			'empty_values' => array(),
			'diagnostics' => array(),
            ),
            'value_report' => array(),
            'errors' => $errors,
            'warnings' => array(),
            'source' => 'local_dump_db_readonly',
			);
		}
	}
