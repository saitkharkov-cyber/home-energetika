<?php
class ModelExtensionModulePumpSelector extends Model {
	public function validateInput($input) {
		$errors = array();

		$total_well_depth_m = $this->toFloat($this->getValue($input, 'total_well_depth_m', 0));
		if ($total_well_depth_m <= 0) {
			$errors['total_well_depth_m'] = 'Глубина скважины должна быть больше 0.';
		}

		$water_level_mode = $this->getValue($input, 'water_level_mode', '');
		if ($water_level_mode != 'known' && $water_level_mode != 'unknown') {
			$errors['water_level_mode'] = 'Укажите режим уровня воды: known или unknown.';
		}

		if ($water_level_mode == 'known') {
			$water_level_m = $this->toFloat($this->getValue($input, 'water_level_m', 0));

			if ($water_level_m <= 0) {
				$errors['water_level_m'] = 'Уровень воды должен быть больше 0.';
			} elseif ($total_well_depth_m > 0 && $water_level_m > $total_well_depth_m) {
				$errors['water_level_m'] = 'Уровень воды не должен быть больше общей глубины скважины.';
			}
		}

		$distance_to_house_m = $this->toFloat($this->getValue($input, 'distance_to_house_m', 0));
		if ($distance_to_house_m < 0) {
			$errors['distance_to_house_m'] = 'Расстояние до дома должно быть 0 или больше.';
		}

		$highest_water_point_floor = (string)$this->getValue($input, 'highest_water_point_floor', '');
		$valid_floors = array('1', '2', '3', 'custom');
		if (!in_array($highest_water_point_floor, $valid_floors)) {
			$errors['highest_water_point_floor'] = 'Укажите самую высокую точку водоразбора: 1, 2, 3 или custom.';
		}

		if ($highest_water_point_floor == 'custom') {
			$custom_vertical_lift_m = $this->toFloat($this->getValue($input, 'custom_vertical_lift_m', 0));

			if ($custom_vertical_lift_m <= 0) {
				$errors['custom_vertical_lift_m'] = 'Высота самой высокой точки должна быть больше 0.';
			}
		}

		$selected_water_points = $this->getSelectedWaterPoints($input);
		if (count($selected_water_points) < 1) {
			$errors['water_points'] = 'Выберите минимум одну точку водоразбора.';
		}

		$casing_diameter_mode = $this->getValue($input, 'casing_diameter_mode', '');
		if ($casing_diameter_mode != 'known' && $casing_diameter_mode != 'unknown') {
			$errors['casing_diameter_mode'] = 'Укажите режим диаметра обсадной трубы: known или unknown.';
		}

		if ($casing_diameter_mode == 'known') {
			$casing_diameter_mm = $this->toFloat($this->getValue($input, 'casing_diameter_mm', 0));

			if ($casing_diameter_mm <= 0) {
				$errors['casing_diameter_mm'] = 'Диаметр обсадной трубы должен быть больше 0.';
			}
		}

		$voltage_mode = (string)$this->getValue($input, 'voltage_mode', '');
		if ($voltage_mode != '220' && $voltage_mode != '380' && $voltage_mode != 'unknown') {
			$errors['voltage_mode'] = 'Укажите напряжение: 220, 380 или unknown.';
		}

		return $errors;
	}

	public function calculateRequirements($input) {
		$warnings = array();
		$assumptions = array();

		$total_well_depth_m = $this->toFloat($this->getValue($input, 'total_well_depth_m', 0));
		$water_level_mode = $this->getValue($input, 'water_level_mode', 'unknown');
		$user_water_level = null;
		$dynamic_level_safety_margin = 0;

		if ($water_level_mode == 'known') {
			$user_water_level = $this->toFloat($this->getValue($input, 'water_level_m', 0));
			$dynamic_level_safety_margin = min(5, max(0, ($total_well_depth_m - $user_water_level) / 2));
			$water_level = $user_water_level + $dynamic_level_safety_margin;
			$warnings[] = 'Указанный уровень воды используется как ориентировочный. В расчет добавлен запас на возможное снижение уровня воды при работе насоса. Точный подбор требует данных по динамическому уровню и дебиту скважины.';
		} else {
			$water_level = $total_well_depth_m * 0.7;
			$warnings[] = 'Уровень воды неизвестен, поэтому расчет выполнен в оценочном режиме: рабочий уровень принят как 70% от общей глубины скважины. Результат является предварительным и требует подтверждения специалистом.';
			$assumptions[] = 'water_level_70_percent';
		}

		$required_pressure = 30.0;
		$vertical_lift = $this->getVerticalLift($input);
		$distance_to_house_m = $this->toFloat($this->getValue($input, 'distance_to_house_m', 0));
		$pipe_losses = max($distance_to_house_m * 0.1, 2);
		$required_head_m = $water_level + $required_pressure + $vertical_lift + $pipe_losses;

		$selected_water_points = $this->getSelectedWaterPoints($input);
		$raw_flow_l_min = 0;
		$flows = $this->getWaterPointFlows();

		foreach ($selected_water_points as $point) {
			if (isset($flows[$point])) {
				$raw_flow_l_min += $flows[$point];
			}
		}

		$required_flow_l_min = 0;
		if ($raw_flow_l_min > 0) {
			$required_flow_l_min = ceil($raw_flow_l_min / 5) * 5;
		}

		$voltage_mode = (string)$this->getValue($input, 'voltage_mode', 'unknown');
		$voltage_was_assumed = false;

		if ($voltage_mode == '380') {
			$selected_voltage = '380';
		} elseif ($voltage_mode == '220') {
			$selected_voltage = '220';
		} else {
			$selected_voltage = '220';
			$voltage_was_assumed = true;
			$warnings[] = 'Напряжение не указано, поэтому предварительный подбор выполнен для сети 220В. Если на объекте доступно 380В, сообщите это специалисту.';
			$assumptions[] = 'voltage_default_220';
		}

		$casing_diameter_mode = $this->getValue($input, 'casing_diameter_mode', 'unknown');
		if ($casing_diameter_mode == 'known') {
			$casing_diameter_mm = $this->toFloat($this->getValue($input, 'casing_diameter_mm', 0));
		} else {
			$casing_diameter_mm = null;
			$warnings[] = 'Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой.';
		}

		return array(
			'required_head_m'       => round($required_head_m, 2),
			'required_flow_l_min'   => round($required_flow_l_min, 2),
			'selected_voltage'      => $selected_voltage,
			'voltage_was_assumed'   => $voltage_was_assumed,
			'casing_diameter_mm'    => $casing_diameter_mm,
			'warnings'              => $warnings,
			'assumptions'           => $assumptions,
			'debug'                 => array(
				'calculation_summary' => array(
					'water_level'            => round($water_level, 2),
					'user_water_level'       => ($user_water_level !== null) ? round($user_water_level, 2) : null,
					'dynamic_level_safety_margin' => round($dynamic_level_safety_margin, 2),
					'effective_water_level'  => round($water_level, 2),
					'required_pressure'      => $required_pressure,
					'vertical_lift'          => round($vertical_lift, 2),
					'pipe_losses'            => round($pipe_losses, 2),
					'selected_water_points'  => $selected_water_points,
					'raw_flow_l_min'         => round($raw_flow_l_min, 2),
					'required_flow_l_min'    => round($required_flow_l_min, 2),
					'voltage_was_assumed'    => $voltage_was_assumed
				)
			)
		);
	}

	public function getRecommendedProducts($requirements) {
		$products = array();
		$best_price_product = $this->getBestPriceProduct($requirements);
		$best_price_product_id = 0;

		if ($best_price_product && isset($best_price_product['product_id'])) {
			$best_price_product_id = (int)$best_price_product['product_id'];
		}

		$optimal_product = $this->getOptimalProduct($requirements, $best_price_product_id);

		$candidates = array(
			$best_price_product,
			$optimal_product,
			$this->getPremiumProduct($requirements, $optimal_product, $best_price_product_id)
		);

		foreach ($candidates as $candidate) {
			if (!$candidate || !isset($candidate['product_id'])) {
				continue;
			}

			$product_id = (int)$candidate['product_id'];

			if (!isset($products[$product_id])) {
				$candidate['result_types'] = array($candidate['result_type']);
				unset($candidate['result_type']);
				$products[$product_id] = $candidate;
			} else {
				if (!in_array($candidate['result_type'], $products[$product_id]['result_types'])) {
					$products[$product_id]['result_types'][] = $candidate['result_type'];
				}
			}
		}

		return array_values($products);
	}

	public function getBestPriceProduct($requirements) {
		$where = $this->buildProductWhere($requirements, false);

		$sql = "SELECT 'best_price' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " (psp.max_head_m - " . (float)$requirements['required_head_m'] . ") AS head_reserve,";
		$sql .= " (psp.max_flow_l_min - " . (float)$requirements['required_flow_l_min'] . ") AS flow_reserve,";
		$sql .= " ((psp.max_head_m - " . (float)$requirements['required_head_m'] . ") + (psp.max_flow_l_min - " . (float)$requirements['required_flow_l_min'] . ")) AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " ORDER BY p.price ASC, (psp.max_head_m - " . (float)$requirements['required_head_m'] . ") ASC, (psp.max_flow_l_min - " . (float)$requirements['required_flow_l_min'] . ") ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		return $this->fetchProduct($sql);
	}

	public function getOptimalProduct($requirements, $excluded_product_id = 0) {
		$where = $this->buildProductWhere($requirements, false);
		$required_head_m = (float)$requirements['required_head_m'];
		$required_flow_l_min = (float)$requirements['required_flow_l_min'];
		$head_reserve_expression = "(psp.max_head_m - " . $required_head_m . ")";
		$total_reserve_expression = "((psp.max_head_m - " . $required_head_m . ") + (psp.max_flow_l_min - " . $required_flow_l_min . "))";

		$sql = "SELECT 'optimal_choice' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " (psp.max_head_m - " . $required_head_m . ") AS head_reserve,";
		$sql .= " (psp.max_flow_l_min - " . $required_flow_l_min . ") AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " AND " . $head_reserve_expression . " >= 5";
		$sql .= " AND " . $head_reserve_expression . " <= 15";
		$sql .= " AND " . $total_reserve_expression . " <= 30";
		if ((int)$excluded_product_id > 0) {
			$sql .= " AND psp.product_id <> " . (int)$excluded_product_id;
		}
		$sql .= " ORDER BY p.price ASC, " . $head_reserve_expression . " ASC, " . $total_reserve_expression . " ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		$product = $this->fetchProduct($sql);

		if ($product) {
			return $product;
		}

		$sql = "SELECT 'optimal_choice' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " (psp.max_head_m - " . $required_head_m . ") AS head_reserve,";
		$sql .= " (psp.max_flow_l_min - " . $required_flow_l_min . ") AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " AND " . $total_reserve_expression . " >= 10";
		$sql .= " AND " . $total_reserve_expression . " <= 30";
		if ((int)$excluded_product_id > 0) {
			$sql .= " AND psp.product_id <> " . (int)$excluded_product_id;
		}
		$sql .= " ORDER BY p.price ASC, " . $total_reserve_expression . " ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		$product = $this->fetchProduct($sql);

		if ($product) {
			return $product;
		}

		$sql = "SELECT 'optimal_choice' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " (psp.max_head_m - " . $required_head_m . ") AS head_reserve,";
		$sql .= " (psp.max_flow_l_min - " . $required_flow_l_min . ") AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " AND " . $total_reserve_expression . " >= 10";
		$sql .= " AND " . $total_reserve_expression . " <= 30";
		$sql .= " ORDER BY p.price ASC, " . $total_reserve_expression . " ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		$product = $this->fetchProduct($sql);

		if ($product) {
			return $product;
		}

		$sql = "SELECT 'optimal_choice' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " (psp.max_head_m - " . $required_head_m . ") AS head_reserve,";
		$sql .= " (psp.max_flow_l_min - " . $required_flow_l_min . ") AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " ORDER BY " . $total_reserve_expression . " ASC, p.price ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		return $this->fetchProduct($sql);
	}

	public function getPremiumProduct($requirements, $optimal_product = null, $best_price_product_id = 0) {
		if (!$optimal_product || !isset($optimal_product['product_id'])) {
			return null;
		}

		$where = $this->buildProductWhere($requirements, true);
		$required_head_m = (float)$requirements['required_head_m'];
		$required_flow_l_min = (float)$requirements['required_flow_l_min'];
		$head_reserve_expression = "(psp.max_head_m - " . $required_head_m . ")";
		$flow_reserve_expression = "(psp.max_flow_l_min - " . $required_flow_l_min . ")";
		$total_reserve_expression = "(" . $head_reserve_expression . " + " . $flow_reserve_expression . ")";
		$optimal_product_id = (int)$optimal_product['product_id'];
		$optimal_max_head_m = (float)$optimal_product['max_head_m'];
		$optimal_max_flow_l_min = (float)$optimal_product['max_flow_l_min'];

		$sql = "SELECT 'premium' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " " . $head_reserve_expression . " AS head_reserve,";
		$sql .= " " . $flow_reserve_expression . " AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " AND " . $head_reserve_expression . " >= 10";
		$sql .= " AND " . $head_reserve_expression . " <= 25";
		$sql .= " AND " . $total_reserve_expression . " <= 50";
		$sql .= " AND psp.max_head_m >= " . $optimal_max_head_m;
		$sql .= " AND psp.max_flow_l_min >= " . $optimal_max_flow_l_min;
		$sql .= " AND (psp.max_head_m > " . $optimal_max_head_m . " OR psp.max_flow_l_min > " . $optimal_max_flow_l_min . ")";
		$sql .= " AND psp.product_id <> " . $optimal_product_id;
		if ((int)$best_price_product_id > 0) {
			$sql .= " AND psp.product_id <> " . (int)$best_price_product_id;
		}
		$sql .= " ORDER BY psp.brand_priority DESC, " . $head_reserve_expression . " ASC, " . $total_reserve_expression . " ASC, p.price ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		$product = $this->fetchProduct($sql);

		if ($product) {
			return $product;
		}

		$sql = "SELECT 'premium' AS result_type, psp.product_id, pd.name, psp.max_head_m, psp.max_flow_l_min, psp.pump_diameter_mm, psp.voltage, psp.brand_priority,";
		$sql .= " " . $head_reserve_expression . " AS head_reserve,";
		$sql .= " " . $flow_reserve_expression . " AS flow_reserve,";
		$sql .= " " . $total_reserve_expression . " AS total_reserve,";
		$sql .= " p.price";
		$sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
		$sql .= " INNER JOIN " . DB_PREFIX . "product p ON p.product_id = psp.product_id";
		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON pd.product_id = p.product_id AND pd.language_id = " . (int)$this->config->get('config_language_id');
		$sql .= " WHERE " . implode(" AND ", $where);
		$sql .= " AND " . $head_reserve_expression . " >= 10";
		$sql .= " AND " . $head_reserve_expression . " <= 25";
		$sql .= " AND " . $total_reserve_expression . " <= 50";
		$sql .= " AND psp.max_head_m >= " . $optimal_max_head_m;
		$sql .= " AND psp.max_flow_l_min >= " . $optimal_max_flow_l_min;
		$sql .= " AND (psp.max_head_m > " . $optimal_max_head_m . " OR psp.max_flow_l_min > " . $optimal_max_flow_l_min . ")";
		$sql .= " AND psp.product_id <> " . $optimal_product_id;
		$sql .= " ORDER BY psp.brand_priority DESC, " . $head_reserve_expression . " ASC, " . $total_reserve_expression . " ASC, p.price ASC, psp.product_id ASC";
		$sql .= " LIMIT 1";

		return $this->fetchProduct($sql);
	}

	public function rebuildSelectorProducts() {
		// TODO: Implement cache rebuild from OpenCart product attributes in the next step.
		return array(
			'total_scanned'          => 0,
			'eligible_inserted'      => 0,
			'skipped_no_attributes'  => 0,
			'skipped_no_price'       => 0,
			'skipped_no_stock'       => 0,
			'skipped_disabled'       => 0
		);
	}

	private function buildProductWhere($requirements, $premium_only) {
		$required_head_m = $this->toFloat($this->getValue($requirements, 'required_head_m', 0));
		$required_flow_l_min = $this->toFloat($this->getValue($requirements, 'required_flow_l_min', 0));
		$selected_voltage = $this->db->escape((string)$this->getValue($requirements, 'selected_voltage', '220'));

		$where = array();
		$where[] = "psp.is_eligible = 1";
		$where[] = "psp.max_head_m >= " . $required_head_m;
		$where[] = "psp.max_flow_l_min >= " . $required_flow_l_min;
		$where[] = "psp.voltage = '" . $selected_voltage . "'";
		$where[] = "p.price > 0";
		$where[] = "p.quantity > 0";
		$where[] = "p.status = 1";

		if (isset($requirements['casing_diameter_mm']) && $requirements['casing_diameter_mm'] !== null && $requirements['casing_diameter_mm'] !== '') {
			$where[] = "psp.pump_diameter_mm <= " . $this->toFloat($requirements['casing_diameter_mm']);
		}

		if ($premium_only) {
			$where[] = "psp.brand_priority > 0";
		}

		return $where;
	}

	private function fetchProduct($sql) {
		$query = $this->db->query($sql);

		if ($query->num_rows) {
			return $query->row;
		}

		return null;
	}

	private function getVerticalLift($input) {
		$highest_water_point_floor = (string)$this->getValue($input, 'highest_water_point_floor', '1');

		if ($highest_water_point_floor == '2') {
			return 6.0;
		}

		if ($highest_water_point_floor == '3') {
			return 9.0;
		}

		if ($highest_water_point_floor == 'custom') {
			return $this->toFloat($this->getValue($input, 'custom_vertical_lift_m', 0));
		}

		return 3.0;
	}

	private function getSelectedWaterPoints($input) {
		$selected = array();
		$water_points = $this->getValue($input, 'water_points', array());
		$flows = $this->getWaterPointFlows();

		if (!is_array($water_points)) {
			return $selected;
		}

		foreach ($water_points as $key => $value) {
			if (is_int($key)) {
				$point = (string)$value;
				if (isset($flows[$point])) {
					$selected[] = $point;
				}
			} else {
				$point = (string)$key;
				if (isset($flows[$point]) && $value) {
					$selected[] = $point;
				}
			}
		}

		return array_values(array_unique($selected));
	}

	private function getWaterPointFlows() {
		return array(
			'sink'             => 8,
			'shower'           => 12,
			'toilet'           => 6,
			'washing_machine'  => 10,
			'dishwasher'       => 8,
			'irrigation'       => 20
		);
	}

	private function getValue($array, $key, $default) {
		if (is_array($array) && isset($array[$key])) {
			return $array[$key];
		}

		return $default;
	}

	private function toFloat($value) {
		if (is_string($value)) {
			$value = str_replace(',', '.', $value);
		}

		return (float)$value;
	}
}
