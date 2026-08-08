<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_pareto.php';

class PumpSelectorParetoTest extends PHPUnit_Framework_TestCase {
    public function testDefaultEpsilonPriceIsTwoPercent() {
        $pareto = new PumpSelectorPareto();

        $this->assertEquals(0.02, $pareto->getEpsilonPrice());
    }

    public function testEngineeringImprovementWithinTwoPercentDominates() {
        $pareto = new PumpSelectorPareto();

        $a = $this->candidate(1951, 0.0000, 0.0000, 72700);
        $b = $this->candidate(1938, 0.0167, 0.3481, 72600);

        $this->assertTrue($pareto->epsilonDominates($a, $b));
        $this->assertFalse($pareto->epsilonDominates($b, $a));
    }

    public function testScenario13EngineeringImprovementWithinTwoPercentDominates() {
        $pareto = new PumpSelectorPareto();

        $a = $this->candidate(1951, 0.0000, 0.0000, 72700);
        $b = $this->candidate(1966, 0.1713, 0.2286, 71500);

        $this->assertTrue($pareto->epsilonDominates($a, $b));
    }

    public function testEngineeringImprovementAboveTwoPercentDoesNotDominate() {
        $pareto = new PumpSelectorPareto();

        $better_engineering = $this->candidate(1, 0.0000, 0.0000, 72700);
        $cheaper = $this->candidate(2, 0.0500, 0.0500, 67100);

        $this->assertFalse($pareto->epsilonDominates($better_engineering, $cheaper));
        $this->assertFalse($pareto->epsilonDominates($cheaper, $better_engineering));
    }

    public function testExactTwoPercentPriceBoundaryIsAllowedForEngineeringImprovement() {
        $pareto = new PumpSelectorPareto();

        $a = $this->candidate(1, 0.0000, 0.0000, 102.00);
        $b = $this->candidate(2, 0.0100, 0.0100, 100.00);

        $this->assertTrue($pareto->epsilonDominates($a, $b));
    }

    public function testPriceAboveTwoPercentBoundaryIsNotAllowed() {
        $pareto = new PumpSelectorPareto();

        $a = $this->candidate(1, 0.0000, 0.0000, 102.01);
        $b = $this->candidate(2, 0.0100, 0.0100, 100.00);

        $this->assertFalse($pareto->epsilonDominates($a, $b));
    }

    public function testExactEngineeringEqualityUsesStrictlyLowerPrice() {
        $pareto = new PumpSelectorPareto();

        $cheap = $this->candidate(1, 0.0000, 0.0000, 100.00);
        $expensive = $this->candidate(2, 0.0000, 0.0000, 101.00);
        $same_price = $this->candidate(3, 0.0000, 0.0000, 100.00);

        $this->assertTrue($pareto->epsilonDominates($cheap, $expensive));
        $this->assertFalse($pareto->epsilonDominates($expensive, $cheap));
        $this->assertFalse($pareto->epsilonDominates($cheap, $same_price));
    }

    public function testCandidateCannotDominateWhenOneEngineeringMetricIsWorse() {
        $pareto = new PumpSelectorPareto();

        $a = $this->candidate(1, 0.0000, 0.2000, 90.00);
        $b = $this->candidate(2, 0.1000, 0.1000, 100.00);

        $this->assertFalse($pareto->epsilonDominates($a, $b));
        $this->assertFalse($pareto->epsilonDominates($b, $a));
    }

    public function testBuildFrontRemovesDominatedDyuLikeCandidate() {
        $pareto = new PumpSelectorPareto();

        $vinko = $this->candidate(1951, 0.0000, 0.0000, 72700);
        $dyu = $this->candidate(7316, 0.0000, 0.4093, 97000);

        $front = $pareto->buildFront(array($vinko, $dyu));

        $this->assertCount(1, $front);
        $this->assertSame(1951, $front[0]['product_id']);
    }

    public function testBuildFrontPreservesIncomparableAlternativesAndInputOrder() {
        $pareto = new PumpSelectorPareto();

        $cheap_worse_fit = $this->candidate(10, 0.0800, 0.0000, 70000);
        $better_fit_expensive = $this->candidate(20, 0.0000, 0.0000, 76000);
        $different_tradeoff = $this->candidate(30, 0.0000, 0.0800, 69000);

        $front = $pareto->buildFront(array($cheap_worse_fit, $better_fit_expensive, $different_tradeoff));

        $this->assertCount(3, $front);
        $this->assertSame(10, $front[0]['product_id']);
        $this->assertSame(20, $front[1]['product_id']);
        $this->assertSame(30, $front[2]['product_id']);
    }

    public function testBuildFrontRemovesMultipleDominatedCandidates() {
        $pareto = new PumpSelectorPareto();

        $best = $this->candidate(1, 0.0000, 0.0000, 100.00);
        $same_engineering_more_expensive = $this->candidate(2, 0.0000, 0.0000, 110.00);
        $worse_engineering_same_price = $this->candidate(3, 0.0500, 0.0500, 100.00);

        $front = $pareto->buildFront(array($best, $same_engineering_more_expensive, $worse_engineering_same_price));

        $this->assertCount(1, $front);
        $this->assertSame(1, $front[0]['product_id']);
    }

    public function testMissingMetricThrowsInvalidArgumentException() {
        $pareto = new PumpSelectorPareto();

        $this->setExpectedException('InvalidArgumentException');
        $pareto->buildFront(array(array(
            'product_id' => 1,
            'technical_fit' => 0.0,
            'price' => 100.0
        )));
    }

    public function testInvalidPriceThrowsInvalidArgumentException() {
        $pareto = new PumpSelectorPareto();

        $this->setExpectedException('InvalidArgumentException');
        $pareto->epsilonDominates(
            $this->candidate(1, 0.0, 0.0, 0),
            $this->candidate(2, 0.0, 0.0, 100)
        );
    }

    public function testNegativeEpsilonThrowsInvalidArgumentException() {
        $this->setExpectedException('InvalidArgumentException');
        new PumpSelectorPareto(-0.01);
    }

    private function candidate($product_id, $technical_fit, $reserve_penalty, $price) {
        return array(
            'product_id' => (int)$product_id,
            'technical_fit' => (float)$technical_fit,
            'reserve_penalty' => (float)$reserve_penalty,
            'price' => (float)$price
        );
    }
}
