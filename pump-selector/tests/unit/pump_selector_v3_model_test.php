<?php

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', dirname(__FILE__) . '/../../module/system/');
}

if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', 'oc_');
}

if (!class_exists('Model', false)) {
    class Model {}
}

require_once dirname(__FILE__) . '/../../module/catalog/model/extension/module/pump_selector_v3.php';

class PumpSelectorV3ModelTestDouble extends ModelExtensionModulePumpSelectorV3 {
    public $testCandidates = array();

    protected function loadCandidates($requirements) {
        return $this->testCandidates;
    }

    protected function prepareProductCardData($product) {
        return $product;
    }

    public function assignBestPriceForTest(array $decorated_candidates) {
        return $this->assignBestPrice($decorated_candidates, new PumpSelectorAssignmentV3());
    }
}

class PumpSelectorV3ModelTest extends PHPUnit_Framework_TestCase {
    public function testModelDoesNotLoadParetoOrOptimalPriceGate() {
        $this->assertFalse(class_exists('PumpSelectorPareto', false));
        $this->assertFalse(class_exists('PumpSelectorOptimalPriceGate', false));
    }

    public function testPassBestPriceBeatsCheaperBorderline() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 50.0, 100.0, 70.0)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(1, $products);
        $this->assertSame(101, $products[0]['product_id']);
    }

    public function testBorderlineBestPriceIsUsedWhenPassIsAbsent() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 70.0),
            $this->candidate(102, 50.0, 100.0, 70.0)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(1, $products);
        $this->assertSame(102, $products[0]['product_id']);
    }

    public function testReturnsAtMostOneBestPriceCardWithV3Role() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(1, $products);
        $this->assertSame('best_price', $products[0]['result_type']);
        $this->assertSame(array('best_price'), $products[0]['result_types']);
    }

    public function testBestPriceProductIsAddedToExclusionPool() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0)
        ));
        $hydraulic = new PumpSelectorHydraulic();
        $decorated_candidates = array();

        foreach ($model->testCandidates as $candidate) {
            $decorated_candidates[] = $hydraulic->decorateCandidate($candidate, $this->requirements());
        }

        $assignment_result = $model->assignBestPriceForTest($decorated_candidates);

        $this->assertSame(101, $assignment_result['candidate']['product_id']);
        $this->assertSame(array(101), $assignment_result['excluded_product_ids']);
    }

    private function modelWithCandidates(array $candidates) {
        $model = new PumpSelectorV3ModelTestDouble();
        $model->testCandidates = $candidates;

        return $model;
    }

    private function requirements() {
        return array(
            'required_flow_l_min' => 40.0,
            'required_head_m' => 70.0,
            'selected_voltage' => '220'
        );
    }

    private function candidate($product_id, $price, $max_head_m, $max_flow_l_min) {
        return array(
            'product_id' => (int)$product_id,
            'max_head_m' => (float)$max_head_m,
            'max_flow_l_min' => (float)$max_flow_l_min,
            'pump_diameter_mm' => 100.0,
            'min_casing_inner_diameter_mm' => 100.0,
            'voltage' => '220',
            'brand_priority' => 5,
            'brand_tier' => 1,
            'price' => (float)$price
        );
    }
}
