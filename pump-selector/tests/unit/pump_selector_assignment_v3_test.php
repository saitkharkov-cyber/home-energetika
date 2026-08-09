<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_assignment_v3.php';

class PumpSelectorAssignmentV3Test extends PHPUnit_Framework_TestCase {
    private $assignment;

    protected function setUp() {
        $this->assignment = new PumpSelectorAssignmentV3();
    }

    public function testSelectsCheapestPassCandidate() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(3, 150.0, 'PASS'),
            $this->candidate(2, 100.0, 'PASS'),
            $this->candidate(1, 125.0, 'PASS'),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testPassBeatsCheaperBorderlineCandidate() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(1, 100.0, 'PASS'),
            $this->candidate(2, 50.0, 'BORDERLINE'),
        ));

        $this->assertSame(1, $result['product_id']);
    }

    public function testSelectsCheapestBorderlineWhenPassIsAbsent() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(1, 80.0, 'FAIL'),
            $this->candidate(2, 70.0, 'BORDERLINE'),
            $this->candidate(3, 60.0, 'BORDERLINE'),
        ));

        $this->assertSame(3, $result['product_id']);
    }

    public function testFailCandidateIsNeverSelected() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(1, 10.0, 'FAIL'),
            $this->candidate(2, 20.0, 'PASS'),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testReturnsNullWithoutPassOrBorderlineCandidate() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(1, 10.0, 'FAIL'),
            $this->candidate(2, 20.0, 'FAIL'),
        ));

        $this->assertNull($result);
    }

    public function testUsesProductIdAscendingForEqualPrice() {
        $result = $this->assignment->selectBestPrice(array(
            $this->candidate(20, 100.0, 'PASS'),
            $this->candidate(10, 100.0, 'PASS'),
        ));

        $this->assertSame(10, $result['product_id']);
    }

    public function testAddsBestPriceProductIdToExclusionPool() {
        $bestPrice = $this->assignment->selectBestPrice(array(
            $this->candidate(7, 100.0, 'PASS'),
        ));

        $pool = $this->assignment->addToExclusionPool(array(), $bestPrice);

        $this->assertSame(array(7), $pool);
    }

    public function testDoesNotDuplicateProductIdInExclusionPool() {
        $candidate = $this->candidate(7, 100.0, 'PASS');
        $pool = $this->assignment->addToExclusionPool(array(7), $candidate);
        $pool = $this->assignment->addToExclusionPool($pool, $candidate);

        $this->assertSame(array(7), $pool);
    }

    public function testOptimalIgnoresBestPriceInExclusionPool() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 100.0, 'PASS', 2, 3),
            $this->candidate(2, 120.0, 'PASS', 2, 3),
        ), array(1));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesOnlyPassCandidates() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 10.0, 'BORDERLINE', 3, 3),
            $this->candidate(2, 100.0, 'PASS', 1, 1),
            $this->candidate(3, 20.0, 'FAIL', 3, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalExcludesPoorReserve() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 10.0, 'PASS', 0, 3),
            $this->candidate(2, 100.0, 'PASS', 1, 1),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalPrioritizesFitGradeBeforePrice() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 10.0, 'PASS', 2, 2),
            $this->candidate(2, 100.0, 'PASS', 2, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesPriceWithinSameFitGrade() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 120.0, 'PASS', 2, 3),
            $this->candidate(2, 100.0, 'PASS', 1, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesProductIdForSameFitAndPrice() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(20, 100.0, 'PASS', 2, 3),
            $this->candidate(10, 100.0, 'PASS', 1, 3),
        ), array());

        $this->assertSame(10, $result['product_id']);
    }

    public function testOptimalReturnsNullWithoutEligiblePassCandidate() {
        $result = $this->assignment->selectOptimal(array(
            $this->candidate(1, 100.0, 'BORDERLINE', 3, 3),
            $this->candidate(2, 100.0, 'PASS', 0, 3),
        ), array());

        $this->assertNull($result);
    }

    public function testPremiumUsesOnlyPassCandidates() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 10.0, 'BORDERLINE', 3, 3, 3),
            $this->candidate(2, 100.0, 'PASS', 2, 2, 3),
            $this->candidate(3, 20.0, 'FAIL', 3, 3, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumExclusionPoolExcludesBestPriceAndOptimal() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 100.0, 'PASS', 2, 2, 3),
            $this->candidate(2, 110.0, 'PASS', 2, 2, 3),
            $this->candidate(3, 120.0, 'PASS', 2, 2, 3),
        ), array(1, 2));

        $this->assertSame(3, $result['product_id']);
    }

    public function testPremiumRejectsTierTwo() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 10.0, 'PASS', 2, 2, 2),
            $this->candidate(2, 100.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumAcceptsTierThree() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 100.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(1, $result['product_id']);
    }

    public function testPremiumRejectsFitGradeBelowTwo() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 10.0, 'PASS', 2, 1, 3),
            $this->candidate(2, 100.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRejectsReserveGradeBelowTwo() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 10.0, 'PASS', 1, 2, 3),
            $this->candidate(2, 100.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumUsesPriceThenProductIdAfterEligibility() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(20, 120.0, 'PASS', 2, 2, 3),
            $this->candidate(10, 100.0, 'PASS', 3, 3, 3),
            $this->candidate(5, 100.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(5, $result['product_id']);
    }

    public function testPremiumDoesNotRankHigherFitOrReserveAboveLowerPrice() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 100.0, 'PASS', 2, 2, 3),
            $this->candidate(2, 200.0, 'PASS', 3, 3, 3),
        ), array());

        $this->assertSame(1, $result['product_id']);
    }

    public function testScenarioTwoPremiumRejectsCheaperCandidatesBelowEngineeringGates() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 70000.0, 'PASS', 3, 1, 3),
            $this->candidate(2, 80000.0, 'PASS', 1, 3, 3),
            $this->candidate(3, 103000.0, 'PASS', 2, 2, 3),
        ), array());

        $this->assertSame(3, $result['product_id']);
    }

    public function testPremiumReturnsNullWithoutEligibleCandidate() {
        $result = $this->assignment->selectPremium(array(
            $this->candidate(1, 100.0, 'PASS', 2, 2, 2),
            $this->candidate(2, 100.0, 'PASS', 1, 3, 3),
        ), array());

        $this->assertNull($result);
    }

    private function candidate($productId, $price, $gate, $reserveGrade = null, $fitGrade = null, $brandTier = null) {
        $candidate = array(
            'product_id' => (int)$productId,
            'price' => (float)$price,
            'hydraulic_gate' => $gate,
        );

        if ($reserveGrade !== null) {
            $candidate['reserve_grade'] = (int)$reserveGrade;
        }

        if ($fitGrade !== null) {
            $candidate['fit_grade'] = (int)$fitGrade;
        }

        if ($brandTier !== null) {
            $candidate['brand_tier'] = (int)$brandTier;
        }

        return $candidate;
    }
}
