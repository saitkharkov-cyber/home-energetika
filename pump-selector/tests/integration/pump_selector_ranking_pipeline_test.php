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
            array(
                'product_id' => 101,
                'price' => 70.0,
                'max_flow_l_min' => 80.0,
                'max_head_m' => 110.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_STANDARD,
            ),
            array(
                'product_id' => 102,
                'price' => 100.0,
                'max_flow_l_min' => 90.0,
                'max_head_m' => 115.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_STANDARD,
            ),
            array(
                'product_id' => 103,
                'price' => 300.0,
                'max_flow_l_min' => 90.0,
                'max_head_m' => 115.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_PREMIUM,
            ),
            array(
                'product_id' => 106,
                'price' => 120.0,
                'max_flow_l_min' => 90.0,
                'max_head_m' => 115.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_UPPER,
            ),
            array(
                'product_id' => 104,
                'price' => 50.0,
                'max_flow_l_min' => 70.0,
                'max_head_m' => 105.0,
                'brand_tier' => PumpSelectorRanking::BRAND_TIER_UPPER,
            ),
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
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidates[3]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_BORDERLINE, $candidates[4]['hydraulic_gate']);
        $this->assertSame(PumpSelectorHydraulic::GATE_FAIL, $candidates[5]['hydraulic_gate']);

        $bestPrice = $ranking->selectBestPrice($candidates);
        $this->assertSame(101, $bestPrice['product_id']);

        $passCandidates = array();
        foreach ($candidates as $candidate) {
            if ($candidate['hydraulic_gate'] === PumpSelectorHydraulic::GATE_PASS) {
                $passCandidates[] = $candidate;
            }
        }

        $generalFront = $pareto->buildFront($passCandidates);
        $optimal = $ranking->selectOptimalFromPassPareto($generalFront);
        $this->assertSame(102, $optimal['product_id']);

        $premiumByTier = array();
        foreach ($candidates as $candidate) {
            if (
                $candidate['hydraulic_gate'] === PumpSelectorHydraulic::GATE_PASS
                && (int)$candidate['brand_tier'] >= PumpSelectorRanking::BRAND_TIER_UPPER
            ) {
                $tier = (int)$candidate['brand_tier'];
                if (!isset($premiumByTier[$tier])) {
                    $premiumByTier[$tier] = array();
                }
                $premiumByTier[$tier][] = $candidate;
            }
        }

        $premiumFront = array();
        foreach ($premiumByTier as $tierCandidates) {
            foreach ($pareto->buildFront($tierCandidates) as $candidate) {
                $premiumFront[] = $candidate;
            }
        }

        $premiumIds = $this->productIds($premiumFront);
        $this->assertContains(103, $premiumIds);
        $this->assertContains(106, $premiumIds);

        $premium = $ranking->selectPremium($premiumFront, $optimal);
        $this->assertNotNull($premium);
        $this->assertSame(103, $premium['product_id']);
        $this->assertSame(PumpSelectorRanking::BRAND_TIER_PREMIUM, $premium['brand_tier']);
        $this->assertArrayNotHasKey('price_delta', $premium);
        $this->assertArrayNotHasKey('upgrade_strength', $premium);
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
