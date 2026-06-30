<?php
// Temporary diagnostic controller for pump selector model testing.
// Remove this file after database/model checks are complete.
class ControllerExtensionModulePumpSelectorTest extends Controller {
	public function index() {
		if (!isset($this->request->get['debug_token']) || $this->request->get['debug_token'] != 'pump_selector_test') {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput('Access denied');
			return;
		}

		$this->load->model('extension/module/pump_selector');

		$scenarios = array(
			'scenario_1_unknown_water_unknown_diameter_220' => array(
				'total_well_depth_m'          => 50,
				'water_level_mode'           => 'unknown',
				'distance_to_house_m'        => 20,
				'highest_water_point_floor'  => '2',
				'water_points'               => array('sink', 'shower', 'toilet', 'washing_machine'),
				'casing_diameter_mode'       => 'unknown',
				'voltage_mode'               => '220'
			),
			'scenario_2_known_water_known_diameter_220' => array(
				'total_well_depth_m'          => 60,
				'water_level_mode'           => 'known',
				'water_level_m'              => 45,
				'distance_to_house_m'        => 25,
				'highest_water_point_floor'  => '2',
				'water_points'               => array('sink', 'shower', 'toilet', 'washing_machine'),
				'casing_diameter_mode'       => 'known',
				'casing_diameter_mm'         => 125,
				'voltage_mode'               => '220'
			),
			'scenario_3_known_water_unknown_diameter_380' => array(
				'total_well_depth_m'          => 70,
				'water_level_mode'           => 'known',
				'water_level_m'              => 55,
				'distance_to_house_m'        => 30,
				'highest_water_point_floor'  => '2',
				'water_points'               => array('sink', 'shower', 'toilet', 'washing_machine'),
				'casing_diameter_mode'       => 'unknown',
				'voltage_mode'               => '380'
			)
		);

		$result = array();

		foreach ($scenarios as $name => $input) {
			$errors = $this->model_extension_module_pump_selector->validateInput($input);

			$item = array(
				'input'  => $input,
				'errors' => $errors
			);

			if (!$errors) {
				$requirements = $this->model_extension_module_pump_selector->calculateRequirements($input);
				$products = $this->model_extension_module_pump_selector->getRecommendedProducts($requirements);

				$item['requirements'] = $requirements;
				$item['products'] = $products;
			}

			$result[$name] = $item;
		}

		echo '<pre>';
		print_r($result);
		echo '</pre>';
	}
}
