<?php
class ControllerExtensionModulePumpSelector extends Controller {
	public function index() {
		$this->load->model('extension/module/pump_selector');

		$this->document->setTitle('Подбор скважинного насоса');

		$data = array();
		$data['heading_title'] = 'SMART - подбор скважинного насоса';
		$data['action'] = $this->url->link('extension/module/pump_selector', '', true);
		$data['errors'] = array();
		$data['requirements'] = null;
		$data['products'] = array();
		$data['warnings'] = array();
		$data['assumptions'] = array();
		$data['submitted'] = false;
		$data['input'] = $this->getDefaultInput();

		if (isset($this->request->server['REQUEST_METHOD']) && $this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!empty($this->request->post['website'])) {
				$this->response->redirect($this->url->link('extension/module/pump_selector'));
				return;
			}
			
			$data['submitted'] = true;
			$input = $this->buildInputFromPost();
			$data['input'] = $input;

			$errors = $this->model_extension_module_pump_selector->validateInput($input);
			$data['errors'] = $errors;

			if (!$errors) {
				$requirements = $this->model_extension_module_pump_selector->calculateRequirements($input);
				$products = $this->model_extension_module_pump_selector->getRecommendedProducts($requirements);

				$data['requirements'] = $requirements;
				$data['products'] = $products;
				$data['warnings'] = $requirements['warnings'];
				$data['assumptions'] = $requirements['assumptions'];
			}
		}

		$data['water_point_labels'] = array(
			'sink'             => 'Раковина',
			'shower'           => 'Душ',
			'toilet'           => 'Унитаз',
			'washing_machine'  => 'Стиральная машина',
			'dishwasher'       => 'Посудомоечная машина',
			'irrigation'       => 'Полив'
		);

		$data['result_type_labels'] = array(
			'best_price'      => 'Лучшая цена',
			'optimal_choice'  => 'Оптимальный выбор',
			'premium'         => 'Премиум качество'
		);

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => 'Главная',
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => 'Подбор скважинного насоса',
			'href' => $this->url->link('extension/module/pump_selector')
		);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/pump_selector', $data));
	}

	public function rebuildCache() {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');

		$token = isset($this->request->get['token']) ? (string)$this->request->get['token'] : '';

		if (!hash_equals($this->getRebuildToken(), $token)) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput(json_encode(array(
				'error' => 'forbidden'
			)));
			return;
		}

		try {
			$this->load->model('extension/module/pump_selector_cache_builder');
			$result = $this->model_extension_module_pump_selector_cache_builder->rebuild();

			$this->response->addHeader('HTTP/1.1 200 OK');
			$this->response->setOutput(json_encode($result));
		} catch (Exception $e) {
			$this->response->addHeader('HTTP/1.1 500 Internal Server Error');
			$this->response->setOutput(json_encode(array(
				'exception_message' => $e->getMessage(),
				'exception_trace' => $e->getTraceAsString()
			)));
		}
	}

	private function buildInputFromPost() {
		$post = $this->request->post;

		return array(
			'total_well_depth_m'          => $this->getPostValue($post, 'total_well_depth_m', ''),
			'water_level_mode'           => $this->getPostValue($post, 'water_level_mode', 'unknown'),
			'water_level_m'              => $this->getPostValue($post, 'water_level_m', ''),
			'distance_to_house_m'        => $this->getPostValue($post, 'distance_to_house_m', ''),
			'highest_water_point_floor'  => $this->getPostValue($post, 'highest_water_point_floor', '1'),
			'custom_vertical_lift_m'     => $this->getPostValue($post, 'custom_vertical_lift_m', ''),
			'water_points'               => $this->getPostValue($post, 'water_points', array()),
			'casing_diameter_mode'       => $this->getPostValue($post, 'casing_diameter_mode', 'unknown'),
			'casing_diameter_mm'         => $this->getPostValue($post, 'casing_diameter_mm', ''),
			'voltage_mode'               => $this->getPostValue($post, 'voltage_mode', 'unknown')
		);
	}

	private function getDefaultInput() {
		return array(
			'total_well_depth_m'          => '',
			'water_level_mode'           => 'unknown',
			'water_level_m'              => '',
			'distance_to_house_m'        => '',
			'highest_water_point_floor'  => '1',
			'custom_vertical_lift_m'     => '',
			'water_points'               => array(),
			'casing_diameter_mode'       => 'unknown',
			'casing_diameter_mm'         => '',
			'voltage_mode'               => 'unknown'
		);
	}

	private function getPostValue($post, $key, $default) {
		if (isset($post[$key])) {
			return $post[$key];
		}

		return $default;
	}

	private function getRebuildToken() {
		return 'd7f3c91a4b6e8d0f2c5a1e9b8f6d4c3a7e9f1b2c4d6a8e0f3c5b7d9a1e2f4c6';
	}
}
