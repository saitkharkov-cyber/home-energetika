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
			'premium'         => 'Премиум'
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
}
