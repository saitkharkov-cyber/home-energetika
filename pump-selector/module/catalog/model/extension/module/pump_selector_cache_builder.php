<?php

class ModelExtensionModulePumpSelectorCacheBuilder extends Model
{
	public function rebuild()
	{
		$table = DB_PREFIX . 'pump_selector_product';

		$this->db->query("TRUNCATE TABLE `" . $table . "`");

		$rows = $this->loadProducts();
		$total_scanned = count($rows);
		$eligible_inserted = 0;
		$date_modified = date('Y-m-d H:i:s');

		foreach ($rows as $row) {
			if (!$this->isEligibleForCache($row)) {
				continue;
			}

			$this->insertCacheRow($row, $date_modified);
			$eligible_inserted++;
		}

		return array(
			'total_scanned' => $total_scanned,
			'eligible_inserted' => $eligible_inserted
		);
	}

	private function loadProducts()
	{
		$language_id = (int)$this->config->get('config_language_id');
		$category_ids = array(11900308, 11900309, 11900321);

		$sql = "SELECT";
		$sql .= " p.product_id,";
		$sql .= " p.price AS product_price,";
		$sql .= " p.quantity,";
		$sql .= " p.status,";
		$sql .= " m.name AS manufacturer,";
		$sql .= " MAX(CASE WHEN pa.attribute_id = 12 THEN pa.text END) AS attribute_12,";
		$sql .= " MAX(CASE WHEN pa.attribute_id = 13 THEN pa.text END) AS attribute_13,";
		$sql .= " MAX(CASE WHEN pa.attribute_id = 15 THEN pa.text END) AS attribute_15,";
		$sql .= " MAX(CASE WHEN pa.attribute_id = 44 THEN pa.text END) AS attribute_44";
		$sql .= " FROM " . DB_PREFIX . "product p";
		$sql .= " INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (p2c.product_id = p.product_id AND p2c.category_id IN (" . implode(',', array_map('intval', $category_ids)) . "))";
		$sql .= " LEFT JOIN " . DB_PREFIX . "manufacturer m ON (m.manufacturer_id = p.manufacturer_id)";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_attribute pa ON (pa.product_id = p.product_id AND pa.language_id = " . $language_id . " AND pa.attribute_id IN (12, 13, 15, 44))";
		$sql .= " GROUP BY p.product_id, p.price, p.quantity, p.status, m.name";
		$sql .= " ORDER BY p.product_id ASC";

		$query = $this->db->query($sql);

		if (!$query->num_rows) {
			return array();
		}

		return $query->rows;
	}

	private function isEligibleForCache(array $row)
	{
		if (!isset($row['attribute_12']) || !isset($row['attribute_13']) || !isset($row['attribute_44'])) {
			return false;
		}

		if ($row['attribute_12'] === '' || $row['attribute_13'] === '' || $row['attribute_44'] === '') {
			return false;
		}

		if ((float)$row['product_price'] <= 0) {
			return false;
		}

		if ((int)$row['quantity'] <= 0) {
			return false;
		}

		if ((int)$row['status'] !== 1) {
			return false;
		}

		return true;
	}

	private function insertCacheRow(array $row, $date_modified)
	{
		$product_id = (int)$row['product_id'];
		$max_head_m = (float)$row['attribute_12'];
		$max_flow_l_min = (int)$row['attribute_13'];
		$voltage = isset($row['attribute_15']) && $row['attribute_15'] !== '' ? (string)(int)$row['attribute_15'] : '';
		$pump_diameter_mm = (float)$row['attribute_44'];
		$brand_priority = $this->getBrandPriority(isset($row['manufacturer']) ? (string)$row['manufacturer'] : '');
		$product_price = (float)$row['product_price'];
		$quantity = (int)$row['quantity'];
		$status = (int)$row['status'];

		$sql = "INSERT INTO " . DB_PREFIX . "pump_selector_product SET";
		$sql .= " product_id = " . $product_id . ",";
		$sql .= " max_head_m = " . $this->formatDecimal($max_head_m) . ",";
		$sql .= " max_flow_l_min = " . $max_flow_l_min . ",";
		$sql .= " pump_diameter_mm = " . $this->formatDecimal($pump_diameter_mm) . ",";
		$sql .= " voltage = '" . $this->db->escape($voltage) . "',";
		$sql .= " brand_priority = " . (int)$brand_priority . ",";
		$sql .= " is_eligible = 1,";
		$sql .= " product_price = " . $this->formatDecimal($product_price) . ",";
		$sql .= " quantity = " . $quantity . ",";
		$sql .= " status = " . $status . ",";
		$sql .= " date_modified = '" . $this->db->escape($date_modified) . "'";

		$this->db->query($sql);
	}

	private function getBrandPriority($manufacturer)
	{
		$manufacturer = trim($manufacturer);

		if ($manufacturer === '') {
			return 0;
		}

		if (stripos($manufacturer, 'Pedrollo') === 0) {
			return 10;
		}

		if (stripos($manufacturer, 'Sumoto') === 0) {
			return 8;
		}

		if (stripos($manufacturer, 'Belamos') === 0) {
			return 5;
		}

		return 0;
	}

	private function formatDecimal($value)
	{
		return number_format($value, 2, '.', '');
	}
}
