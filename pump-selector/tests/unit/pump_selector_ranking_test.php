<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_ranking.php';

class PumpSelectorRankingTest extends PHPUnit_Framework_TestCase {
    private $ranking;

    protected function setUp() {
        $this->ranking = new PumpSelectorRanking();
    }

    public function testBestPricePrefersPassOverCheaperBorderline() {
        $result = $this->ranking->selectBestPrice(array(
            $this->candidate(1, 100.0, 'PASS', 2, 2),
            $this->candidate(2, 50.0, 'BORDERLINE', null, null),
        ));

        $this->assertSame(1, $result['product_id']);
    }

    public function testBestPriceFallsBackToBorderlineWhenNoPassExists() {
        $result = $this->ranking->selectBestPrice(array(
            $this->candidate(1, 90.0, 'FAIL', null, null),
            $this->candidate(2, 80.0, 'BORDERLINE', null, null),
            $this->candidate(3, 70.0, 'BORDERLINE', null, null),
        ));

        $this->assertSame(3, $result['product_id']);
    }

    public function testBestPriceReturnsNullWhenOnlyFailCandidatesExist() {
        $result = $this->ranking->selectBestPrice(array(
            $this->candidate(1, 90.0, 'FAIL', null, null),
            $this->candidate(2, 80.0, 'FAIL', null, null),
        ));

        $this->assertNull($result);
    }

    public function testBestPriceUsesProductIdAsStableTieBreak() {
        $result = $this->ranking->selectBestPrice(array(
            $this->candidate(20, 100.0, 'PASS', 2, 2),
            $this->candidate(10, 100.0, 'PASS', 2, 2),
        ));

        $this->assertSame(10, $result['product_id']);
    }

    public function testOptimalPrioritizesReserveGradeBeforeFitAndPrice() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 50.0, 'PASS', 2, 3),
            $this->candidate(2, 100.0, 'PASS', 3, 1),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalPrioritizesFitGradeWithinSameReserveGrade() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 50.0, 'PASS', 3, 2),
            $this->candidate(2, 100.0, 'PASS', 3, 3),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesPriceWithinSameEngineeringGrades() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 100.0, 'PASS', 3, 3),
            $this->candidate(2, 90.0, 'PASS', 3, 3),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesBrandFactorOnlyAfterEngineeringAndPriceTie() {
        $a = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $a['brand_factor'] = 5;
        $b = $this->candidate(2, 100.0, 'PASS', 3, 3);
        $b['brand_factor'] = 8;

        $result = $this->ranking->selectOptimalFromPassPareto(array($a, $b));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalUsesProductIdAsFinalStableTieBreak() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(20, 100.0, 'PASS', 3, 3),
            $this->candidate(10, 100.0, 'PASS', 3, 3),
        ));

        $this->assertSame(10, $result['product_id']);
    }

    public function testOptimalIgnoresBorderlineCandidates() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 50.0, 'BORDERLINE', null, null),
            $this->candidate(2, 100.0, 'PASS', 2, 2),
        ));

        $this->assertSame(2, $result['product_id']);
    }

    public function testOptimalReturnsNullWhenThereAreNoPassCandidates() {
        $result = $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 50.0, 'BORDERLINE', null, null),
            $this->candidate(2, 40.0, 'FAIL', null, null),
        ));

        $this->assertNull($result);
    }

    public function testPremiumAllowsOneStepReserveDegradation() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $premium = $this->candidate(2, 120.0, 'PASS', 2, 3);

        $this->assertTrue($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumAllowsOneStepFitDegradation() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $premium = $this->candidate(2, 120.0, 'PASS', 3, 2);

        $this->assertTrue($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumRejectsDoubleOneStepDegradation() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $premium = $this->candidate(2, 120.0, 'PASS', 2, 2);

        $this->assertFalse($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumDoesNotPenalizeEngineeringImprovement() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2);
        $premium = $this->candidate(2, 120.0, 'PASS', 3, 3);

        $this->assertTrue($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumAllowsTradeoffWhenImprovementOffsetsOnlyByRuleDefinition() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 3);
        $premium = $this->candidate(2, 120.0, 'PASS', 3, 2);

        $this->assertTrue($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumRejectsBorderlineCandidate() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $premium = $this->candidate(2, 120.0, 'BORDERLINE', null, null);

        $this->assertFalse($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumFilterKeepsOnlyEngineeringEligibleCandidates() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $eligible = $this->candidate(2, 120.0, 'PASS', 2, 3);
        $doubleDrop = $this->candidate(3, 130.0, 'PASS', 2, 2);
        $borderline = $this->candidate(4, 110.0, 'BORDERLINE', null, null);

        $result = $this->ranking->filterPremiumEngineeringEligible(
            array($eligible, $doubleDrop, $borderline),
            $optimal
        );

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['product_id']);
    }

    public function testInvalidPassGradeIsRejected() {
        $this->setExpectedException('InvalidArgumentException');

        $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 100.0, 'PASS', 4, 3),
        ));
    }

    private function candidate($productId, $price, $gate, $reserveGrade, $fitGrade) {
        return array(
            'product_id' => (int)$productId,
            'price' => (float)$price,
            'hydraulic_gate' => $gate,
            'reserve_grade' => $reserveGrade,
            'fit_grade' => $fitGrade,
        );
    }
}
