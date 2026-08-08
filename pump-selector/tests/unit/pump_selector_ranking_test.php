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

    public function testPremiumStrongUpgradeAllowsExactlySixtyPercentPriceDelta() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $premium = $this->candidate(2, 160.0, 'PASS', 3, 2, 2);

        $result = $this->ranking->selectPremium(array($premium), $optimal);

        $this->assertSame(2, $result['product_id']);
        $this->assertSame('STRONG', $result['upgrade_strength']);
    }

    public function testPremiumStrongUpgradeRejectsPriceAboveSixtyPercent() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $premium = $this->candidate(2, 160.01, 'PASS', 3, 2, 2);

        $this->assertNull($this->ranking->selectPremium(array($premium), $optimal));
    }

    public function testPremiumMediumBrandUpgradeAllowsExactlyThirtyFivePercent() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 135.0, 'PASS', 3, 3, 2);

        $result = $this->ranking->selectPremium(array($premium), $optimal);

        $this->assertSame(2, $result['product_id']);
        $this->assertSame('MEDIUM', $result['upgrade_strength']);
    }

    public function testPremiumWeakBrandUpgradeAllowsExactlyFifteenPercent() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 115.0, 'PASS', 2, 3, 2);

        $result = $this->ranking->selectPremium(array($premium), $optimal);

        $this->assertSame(2, $result['product_id']);
        $this->assertSame('WEAK', $result['upgrade_strength']);
    }

    public function testPremiumRejectsNoUpgradeAtSameBrandTier() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 2);
        $premium = $this->candidate(2, 100.0, 'PASS', 3, 3, 2);

        $this->assertNull($this->ranking->selectPremium(array($premium), $optimal));
    }

    public function testPremiumRejectsStandardTierEvenWithEngineeringImprovement() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $standard = $this->candidate(2, 90.0, 'PASS', 3, 3, 1);

        $this->assertNull($this->ranking->selectPremium(array($standard), $optimal));
    }

    public function testPremiumRejectsDoubleEngineeringDegradationBeforePriceCheck() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 90.0, 'PASS', 2, 2, 3);

        $this->assertNull($this->ranking->selectPremium(array($premium), $optimal));
    }

    public function testPremiumRankingPrefersStrongerUpgradeBeforeLowerPrice() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $medium = $this->candidate(2, 105.0, 'PASS', 3, 1, 3);
        $strong = $this->candidate(3, 150.0, 'PASS', 3, 2, 2);

        $result = $this->ranking->selectPremium(array($medium, $strong), $optimal);

        $this->assertSame(3, $result['product_id']);
        $this->assertSame('STRONG', $result['upgrade_strength']);
    }

    public function testPremiumRankingUsesImprovementBeforePriceWithinSameStrength() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 1, 1, 1);
        $oneStep = $this->candidate(2, 105.0, 'PASS', 2, 1, 2);
        $twoSteps = $this->candidate(3, 140.0, 'PASS', 2, 2, 2);

        $result = $this->ranking->selectPremium(array($oneStep, $twoSteps), $optimal);

        $this->assertSame(3, $result['product_id']);
        $this->assertSame(2, $result['improvement_total']);
    }

    public function testPremiumRankingUsesPriceBeforeBrandTier() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $upper = $this->candidate(2, 120.0, 'PASS', 3, 2, 2);
        $premium = $this->candidate(3, 130.0, 'PASS', 3, 2, 3);

        $result = $this->ranking->selectPremium(array($premium, $upper), $optimal);

        $this->assertSame(2, $result['product_id']);
    }

    public function testPremiumRankingUsesBrandTierAfterPriceTie() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $upper = $this->candidate(2, 120.0, 'PASS', 3, 2, 2);
        $premium = $this->candidate(3, 120.0, 'PASS', 3, 2, 3);

        $result = $this->ranking->selectPremium(array($upper, $premium), $optimal);

        $this->assertSame(3, $result['product_id']);
    }

    public function testPremiumRankingUsesProductIdAsFinalTieBreak() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 2, 2, 1);
        $higherId = $this->candidate(20, 120.0, 'PASS', 3, 2, 2);
        $lowerId = $this->candidate(10, 120.0, 'PASS', 3, 2, 2);

        $result = $this->ranking->selectPremium(array($higherId, $lowerId), $optimal);

        $this->assertSame(10, $result['product_id']);
    }

    public function testPremiumCheaperCandidatePassesPriceGate() {
        $optimal = $this->candidate(1, 100.0, 'PASS', 3, 3, 1);
        $premium = $this->candidate(2, 90.0, 'PASS', 3, 3, 2);

        $result = $this->ranking->selectPremium(array($premium), $optimal);

        $this->assertSame(2, $result['product_id']);
        $this->assertLessThan(0, $result['price_delta']);
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
