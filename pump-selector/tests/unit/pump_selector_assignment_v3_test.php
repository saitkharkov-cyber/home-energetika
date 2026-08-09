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

    private function candidate($productId, $price, $gate) {
        return array(
            'product_id' => (int)$productId,
            'price' => (float)$price,
            'hydraulic_gate' => $gate,
        );
    }
}
