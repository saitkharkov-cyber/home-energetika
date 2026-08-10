<?php

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', dirname(__FILE__) . '/../../module/system/');
}

if (!class_exists('Controller', false)) {
    class Controller {}
}

require_once dirname(__FILE__) . '/../../module/catalog/controller/extension/module/pump_selector_v3.php';

class PumpSelectorV3ConsultResponseDouble {
    public $headers = array();
    public $output = '';

    public function addHeader($header) {
        $this->headers[] = $header;
    }

    public function setOutput($output) {
        $this->output = $output;
    }
}

class PumpSelectorV3ConsultLoaderDouble {
    public function model($route) {}
}

class PumpSelectorV3ConsultSelectorDouble {
    public function validateInput($input) {
        return array();
    }

    public function calculateRequirements($input) {
        throw new Exception('Internal calculation failure with hidden path');
    }
}

class PumpSelectorV3ConsultV3ModelDouble {
    public function getRecommendedProducts($requirements) {
        throw new Exception('This method must not be called after calculation failure');
    }
}

class PumpSelectorV3ConsultControllerDouble extends ControllerExtensionModulePumpSelectorV3 {
    public $request;
    public $response;
    public $load;
    public $model_extension_module_pump_selector;
    public $model_extension_module_pump_selector_v3;
}

class PumpSelectorV3ConsultTest extends PHPUnit_Framework_TestCase {
    public function testCalculationExceptionReturnsOnlyGenericJsonError() {
        $controller = new PumpSelectorV3ConsultControllerDouble();
        $controller->request = new stdClass();
        $controller->request->server = array('REQUEST_METHOD' => 'POST');
        $controller->request->post = array(
            'name' => 'Анна',
            'telephone' => '+380501234567',
            'total_well_depth_m' => '30',
            'water_level_mode' => 'known',
            'water_level_m' => '20',
            'distance_to_house_m' => '10',
            'highest_water_point_floor' => '1',
            'custom_vertical_lift_m' => '',
            'water_points' => array('sink'),
            'casing_diameter_mode' => 'unknown',
            'casing_diameter_mm' => '',
            'voltage_mode' => '220',
            'website' => ''
        );
        $controller->response = new PumpSelectorV3ConsultResponseDouble();
        $controller->load = new PumpSelectorV3ConsultLoaderDouble();
        $controller->model_extension_module_pump_selector = new PumpSelectorV3ConsultSelectorDouble();
        $controller->model_extension_module_pump_selector_v3 = new PumpSelectorV3ConsultV3ModelDouble();

        $controller->consult();

        $this->assertContains('Content-Type: application/json; charset=utf-8', $controller->response->headers);
        $payload = json_decode($controller->response->output, true);
        $this->assertSame(array('success' => false, 'error' => 'Не удалось отправить заявку'), $payload);
        $this->assertFalse(strpos($controller->response->output, 'hidden path') !== false);
    }
}
