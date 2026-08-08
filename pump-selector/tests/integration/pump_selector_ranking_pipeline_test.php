<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_hydraulic.php';
require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_pareto.php';
require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_ranking.php';

class PumpSelectorRankingPipelineTest extends PHPUnit_Framework_TestCase
{
    public function testHydraulicParetoAndRoleRankingWorkTogether()
    {
        $hydraulic = new PumpSelectorHydraulic();
        $pareto = new PumpSelectorPareto(0.02);
        $ranking = new PumpSelectorRanking();

        $requirements = array(
            'required_flow_l_min' => 40.0,
            'required_head_m' => 78.0,
        );

        $rawProducts = array(
            // Cheapest PASS: valid Best Price, but lower reserve grade than Optimal.
            array(
                'product_id' => 101,
                'price' => 70.0,
                'max_flow_l_min' => 80.0,
                'max_head_m' => 110.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_STANDARD,
            ),
            // Engineering optimum: IDEAL reserve + IDEAL fit at standard tier.
            array(
                'product_id' => 102,
                'price' => 100.0,
                'max_flow_l_min' => 90.0,
                'max_head_m' => 115.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_STANDARD,
            ),
            // Same engineering as Optimal, higher brand tier and +30% price.
            // It is dominated by 102 on the GENERAL front, but survives its
            // separate PREMIUM pool and is a MEDIUM brand upgrade.
            array(
                'product_id' => 103,
                'price' => 130.0,
                'max_flow_l_min' => 90.0,
                'max_head_m' => 115.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_PREMIUM,
            ),
            // Cheaper than every PASS candidate, but only BORDERLINE.
            array(
                'product_id' => 104,
                'price' => 50.0,
                'max_flow_l_min' => 70.0,
                'max_head_m' => 105.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_UPPER,
            ),
            // Cheapest overall, but hydraulic FAIL.
            array(
                'product_id' => 105,
                'price' => 40.0,
                'max_flow_l_min' => 60.0,
                'max_head_m' => 100.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_PREMIUM,
            ),
        );

        $candidates = array();
        foreach ($rawProducts as $rawProduct) {
            $candidates[] = $hydraulic->decorateCandidate($rawProduct, $requirements);
        }

        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidates[0]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidates[1]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidates[2]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_BORDERLINE, $candidates[3]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_FAIL, $candidates[4]['hydraulic_gate']);

        $bestPrice = $ranking->selectBestPrice($candidates);
        $this->assertSame(101, $bestPrice['product_id']);

        $passCandidates = array();
        foreach ($candidates as $candidate) {
            if ($candidate['hydraulic_gate'] === PumpSelectorHydraulic::GATE_PASS) {
                $passCandidates[] = $candidate;
            }
        }

        $generalFront = $pareto->buildFront($passCandidates);
        $generalFrontIds = $this->productIds($generalFront);

        $this->assertContains(101, $generalFrontIds);
        $this->assertContains(102, $generalFrontIds);
        $this->assertNotContains(103, $generalFrontIds);

        $optimal = $ranking->selectOptimalFromPassPareto($generalFront);
        $this->assertSame(102, $optimal['product_id']);
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $optimal['reserve_grade']);
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $optimal['fit_grade']);

        $premiumPool = array();
        foreach ($candidates as $candidate) {
            if (
                $candidate['hydraulic_gate'] === PumpSelectorHydraulic::GATE_PASS
                && (int)$candidate['brand_tier'] >= PumpSelectorRanking::BRAND_TIER_UPPER
            ) {
                $premiumPool[] = $candidate;
            }
        }

        $premiumFront = $pareto->buildFront($premiumPool);
        $premium = $ranking->selectPremium($premiumFront, $optimal);

        $this->assertNotNull($premium);
        $this->assertSame(103, $premium['product_id']);
        $this->assertSame(PumpSelectorRanking::UPGRADE_MEDIUM, $premium['upgrade_strength']);
        $this->assertEquals(0.30, $premium['price_delta'], '', 0.000001);
    }

    private function productIds(array $candidates)
    {
        $ids = array();

        foreach ($candidates as $candidate) {
            $ids[] = (int)$candidate['product_id'];
        }

        return $ids;
    }
}
