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

    public function assignOptimalForTest(array $decorated_candidates, array $excluded_product_ids) {
        return $this->assignOptimal($decorated_candidates, $excluded_product_ids, new PumpSelectorAssignmentV3());
    }

    public function assignPremiumForTest(array $decorated_candidates, array $excluded_product_ids) {
        return $this->assignPremium($decorated_candidates, $excluded_product_ids, new PumpSelectorAssignmentV3());
    }
}

class PumpSelectorV3ModelTest extends PHPUnit_Framework_TestCase {
    public function testModelDoesNotLoadParetoOrOptimalPriceGate() {
        $this->assertFalse(class_exists('PumpSelectorPareto', false));
        $this->assertFalse(class_exists('PumpSelectorOptimalPriceGate', false));
        $this->assertFalse(class_exists('PumpSelectorRanking', false));
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

    public function testPipelineReturnsDistinctRolesInSequentialOrder() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0),
            $this->candidate(103, 150.0, 100.0, 80.0, 3)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(3, $products);
        $this->assertSame(101, $products[0]['product_id']);
        $this->assertSame('best_price', $products[0]['result_type']);
        $this->assertSame(array('best_price'), $products[0]['result_types']);
        $this->assertSame(102, $products[1]['product_id']);
        $this->assertSame('optimal_choice', $products[1]['result_type']);
        $this->assertSame(array('optimal_choice'), $products[1]['result_types']);
        $this->assertNotSame($products[0]['product_id'], $products[1]['product_id']);
        $this->assertSame(103, $products[2]['product_id']);
        $this->assertSame('premium', $products[2]['result_type']);
        $this->assertSame(array('premium'), $products[2]['result_types']);
        $this->assertNotSame($products[0]['product_id'], $products[2]['product_id']);
        $this->assertNotSame($products[1]['product_id'], $products[2]['product_id']);
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

    public function testOptimalProductIsAddedToInternalExclusionPool() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0)
        ));
        $hydraulic = new PumpSelectorHydraulic();
        $decorated_candidates = array();

        foreach ($model->testCandidates as $candidate) {
            $decorated_candidates[] = $hydraulic->decorateCandidate($candidate, $this->requirements());
        }

        $best_price_assignment = $model->assignBestPriceForTest($decorated_candidates);
        $optimal_assignment = $model->assignOptimalForTest(
            $decorated_candidates,
            $best_price_assignment['excluded_product_ids']
        );

        $this->assertSame(102, $optimal_assignment['candidate']['product_id']);
        $this->assertSame(array(101, 102), $optimal_assignment['excluded_product_ids']);
    }

    public function testPremiumProductIsAddedToInternalExclusionPool() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0),
            $this->candidate(103, 150.0, 100.0, 80.0, 3)
        ));
        $decorated_candidates = $this->decorateCandidates($model->testCandidates, $this->requirements());
        $best_price_assignment = $model->assignBestPriceForTest($decorated_candidates);
        $optimal_assignment = $model->assignOptimalForTest(
            $decorated_candidates,
            $best_price_assignment['excluded_product_ids']
        );
        $premium_assignment = $model->assignPremiumForTest(
            $decorated_candidates,
            $optimal_assignment['excluded_product_ids']
        );

        $this->assertSame(103, $premium_assignment['candidate']['product_id']);
        $this->assertSame(array(101, 102, 103), $premium_assignment['excluded_product_ids']);
    }

    public function testPipelineReturnsOnlyBestPriceWhenOptimalIsAbsent() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(1, $products);
        $this->assertSame('best_price', $products[0]['result_type']);
    }

    public function testPipelineReturnsBestPriceAndOptimalWhenPremiumIsAbsent() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0),
            $this->candidate(103, 130.0, 100.0, 80.0, 2)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertCount(2, $products);
        $this->assertSame('best_price', $products[0]['result_type']);
        $this->assertSame('optimal_choice', $products[1]['result_type']);
    }

    public function testExclusionPoolRemainsInternalToResultCards() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 100.0, 100.0, 80.0),
            $this->candidate(102, 120.0, 100.0, 80.0),
            $this->candidate(103, 150.0, 100.0, 80.0, 3)
        ));

        $products = $model->getRecommendedProducts($this->requirements());

        $this->assertArrayNotHasKey('excluded_product_ids', $products[0]);
        $this->assertArrayNotHasKey('excluded_product_ids', $products[1]);
        $this->assertArrayNotHasKey('excluded_product_ids', $products[2]);
    }

    public function testScenarioOneSelectionPrioritizesFitGradeOverLowerPrice() {
        $model = $this->modelWithCandidates(array(
            $this->candidate(101, 16380.0, 100.0, 150.0),
            $this->candidate(102, 22370.0, 100.0, 60.0),
            $this->candidate(103, 18000.0, 100.0, 100.0),
            $this->candidate(104, 54440.0, 95.0, 100.0, 3),
            $this->candidate(105, 70000.0, 100.0, 60.0, 3)
        ));
        $requirements = array(
            'required_flow_l_min' => 30.0,
            'required_head_m' => 65.0,
            'selected_voltage' => '220'
        );

        $products = $model->getRecommendedProducts($requirements);

        $this->assertSame(101, $products[0]['product_id']);
        $this->assertSame(102, $products[1]['product_id']);
        $this->assertSame(104, $products[2]['product_id']);
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

    private function decorateCandidates(array $candidates, array $requirements) {
        $hydraulic = new PumpSelectorHydraulic();
        $decorated_candidates = array();

        foreach ($candidates as $candidate) {
            $decorated_candidates[] = $hydraulic->decorateCandidate($candidate, $requirements);
        }

        return $decorated_candidates;
    }

    private function candidate($product_id, $price, $max_head_m, $max_flow_l_min, $brand_tier = 1) {
        $brand_priority = 0;

        if ($brand_tier === 3) {
            $brand_priority = 10;
        } elseif ($brand_tier === 2) {
            $brand_priority = 8;
        } elseif ($brand_tier === 1) {
            $brand_priority = 5;
        }

        return array(
            'product_id' => (int)$product_id,
            'max_head_m' => (float)$max_head_m,
            'max_flow_l_min' => (float)$max_flow_l_min,
            'pump_diameter_mm' => 100.0,
            'min_casing_inner_diameter_mm' => 100.0,
            'voltage' => '220',
            'brand_priority' => $brand_priority,
            'brand_tier' => $brand_tier,
            'price' => (float)$price
        );
    }
}
