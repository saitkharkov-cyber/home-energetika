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

    public function testPremiumRejectsBorderlineCandidate() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3);
        $premium = $this->candidate(2, 120.0, 'BORDERLINE', null, null);
        $this->assertFalse($this->ranking->isPremiumEngineeringEligible($optimal, $premium));
    }

    public function testPremiumHasNoPriceHardGate() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 3, 1);
        $premium = $this->candidate(2, 1000.0, 'PASS', 2, 3, 3);
        $result = $this->ranking->selectPremium(array($premium), $optimal);
        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRejectsStandardTierEvenWithEngineeringImprovement() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $standard = $this->candidate(2, 90.0, 'PASS', 3, 3, 1);
        $this->assertNull($this->ranking->selectPremium(array($standard), $optimal));
    }

    public function testPremiumRejectsDoubleEngineeringDegradation() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 1000.0, 'PASS', 2, 2, 3);
        $this->assertNull($this->ranking->selectPremium(array($premium), $optimal));
    }

    public function testPremiumRequiresBrandUpgradeOrEngineeringImprovement() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 2);
        $same = $this->candidate(2, 90.0, 'PASS', 3, 3, 2);
        $this->assertNull($this->ranking->selectPremium(array($same), $optimal));
    }

    public function testPremiumAllowsEngineeringImprovementAtSameTier() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 2);
        $better = $this->candidate(2, 200.0, 'PASS', 3, 2, 2);
        $result = $this->ranking->selectPremium(array($better), $optimal);
        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRankingPrefersHigherBrandTierBeforePrice() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 3, 1);
        $upper = $this->candidate(2, 120.0, 'PASS', 3, 2, 2);
        $premium = $this->candidate(3, 1000.0, 'PASS', 2, 3, 3);
        $result = $this->ranking->selectPremium(array($upper, $premium), $optimal);
        $this->assertSame(3, $result['product_id']);
    }

    public function testPremiumRankingUsesImprovementWithinSameTier() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 1, 1, 1);
        $oneStep = $this->candidate(2, 100.0, 'PASS', 2, 1, 3);
        $twoSteps = $this->candidate(3, 200.0, 'PASS', 2, 2, 3);
        $result = $this->ranking->selectPremium(array($oneStep, $twoSteps), $optimal);
        $this->assertSame(3, $result['product_id']);
        $this->assertSame(2, $result['improvement_total']);
    }

    public function testPremiumRankingUsesLowerDegradationWithinSameTierAndImprovement() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $noDrop = $this->candidate(2, 200.0, 'PASS', 3, 2, 3);
        $tradeoff = $this->candidate(3, 100.0, 'PASS', 3, 1, 3);
        $result = $this->ranking->selectPremium(array($tradeoff, $noDrop), $optimal);
        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRankingUsesReserveThenFitThenPrice() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 1, 1, 1);
        $a = $this->candidate(2, 300.0, 'PASS', 3, 1, 3);
        $b = $this->candidate(3, 100.0, 'PASS', 2, 2, 3);
        $result = $this->ranking->selectPremium(array($b, $a), $optimal);
        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRankingUsesPriceAfterAllHigherOrderFactorsTie() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $expensive = $this->candidate(2, 300.0, 'PASS', 2, 2, 3);
        $cheap = $this->candidate(3, 200.0, 'PASS', 2, 2, 3);
        $result = $this->ranking->selectPremium(array($expensive, $cheap), $optimal);
        $this->assertSame(3, $result['product_id']);
    }

    public function testPremiumRankingUsesProductIdAsFinalTieBreak() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $higherId = $this->candidate(20, 120.0, 'PASS', 2, 2, 3);
        $lowerId = $this->candidate(10, 120.0, 'PASS', 2, 2, 3);
        $result = $this->ranking->selectPremium(array($higherId, $lowerId), $optimal);
        $this->assertSame(10, $result['product_id']);
    }

    public function testPremiumScenarioTwoChoosesGrundfosTierThree() {
        $optimal = $this->candidate(1600, 22370.0, 'PASS', 2, 3, 1);
        $sumoto = $this->candidate(1825, 76900.0, 'PASS', 3, 2, 2);
        $grundfos = $this->candidate(3318, 103000.0, 'PASS', 2, 3, 3);
        $result = $this->ranking->selectPremium(array($sumoto, $grundfos), $optimal);
        $this->assertSame(3318, $result['product_id']);
    }

    public function testPremiumRequiresBrandTierForFinalSelection() {
        $this->setExpectedException('InvalidArgumentException');
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 110.0, 'PASS', 3, 3);
        $this->ranking->selectPremium(array($premium), $optimal);
    }

    public function testInvalidPassGradeIsRejected() {
        $this->setExpectedException('InvalidArgumentException');
        $this->ranking->selectOptimalFromPassPareto(array(
            $this->candidate(1, 100.0, 'PASS', 4, 3),
        ));
    }

    private function candidate($productId, $price, $gate, $reserveGrade, $fitGrade, $brandTier = null) {
        $candidate = array(
            'product_id' => (int)$productId,
            'price' => (float)$price,
            'hydraulic_gate' => $gate,
            'reserve_grade' => $reserveGrade,
            'fit_grade' => $fitGrade,
        );

        if ($brandTier !== null) {
            $candidate['brand_tier'] = (int)$brandTier;
        }

        return $candidate;
    }
}
