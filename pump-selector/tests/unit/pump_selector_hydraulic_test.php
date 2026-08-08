<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_hydraulic.php';

class PumpSelectorHydraulicTest extends PHPUnit_Framework_TestCase
{
    private $hydraulic;

    protected function setUp()
    {
        $this->hydraulic = new PumpSelectorHydraulic();
    }

    public function testDecorateCandidateScenario02Vinko7602()
    {
        $candidate = $this->hydraulic->decorateCandidate(
            array(
                'product_id' => 7602,
                'max_head_m' => 94.0,
                'max_flow_l_min' => 100.0,
                'price' => 81400.0
            ),
            array(
                'required_head_m' => 78.0,
                'required_flow_l_min' => 40.0
            )
        );

        $this->assertEquals(0.4, $candidate['q_rel'], '', 0.000001);
        $this->assertEquals(78.0 / 94.0, $candidate['h_rel'], '', 0.000001);
        $this->assertEquals(78.96, $candidate['h_est'], '', 0.000001);
        $this->assertEquals((78.96 - 78.0) / 78.0, $candidate['reserve_rel'], '', 0.000001);
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidate['hydraulic_gate']);
        $this->assertEquals((78.0 / 94.0) - 0.75, $candidate['technical_fit'], '', 0.000001);
        $this->assertEquals(2.0 * (0.10 - $candidate['reserve_rel']), $candidate['reserve_penalty'], '', 0.000001);
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $candidate['reserve_grade']);
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $candidate['fit_grade']);
        $this->assertSame('endpoint_parabolic_estimate', $candidate['hydraulic_model']);
        $this->assertSame('approximate', $candidate['confidence']);
    }

    public function testDecorateCandidateScenario02Grundfos3326()
    {
        $candidate = $this->hydraulic->decorateCandidate(
            array(
                'product_id' => 3326,
                'max_head_m' => 145.0,
                'max_flow_l_min' => 73.0,
                'price' => 97900.0
            ),
            array(
                'required_head_m' => 78.0,
                'required_flow_l_min' => 40.0
            )
        );

        $this->assertEquals(40.0 / 73.0, $candidate['q_rel'], '', 0.000001);
        $this->assertEquals(78.0 / 145.0, $candidate['h_rel'], '', 0.000001);
        $this->assertEquals(101.46, $candidate['h_est'], '', 0.01);
        $this->assertEquals(0.3008, $candidate['reserve_rel'], '', 0.0002);
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $candidate['hydraulic_gate']);
        $this->assertEquals(0.0, $candidate['technical_fit'], '', 0.000001);
        $this->assertEquals(0.0508, $candidate['reserve_penalty'], '', 0.0002);
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $candidate['reserve_grade']);
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $candidate['fit_grade']);
    }

    public function testHydraulicGateBoundaries()
    {
        $this->assertSame(PumpSelectorHydraulic::GATE_FAIL, $this->hydraulic->getHydraulicGate(0.5, -0.1001));
        $this->assertSame(PumpSelectorHydraulic::GATE_BORDERLINE, $this->hydraulic->getHydraulicGate(0.5, -0.10));
        $this->assertSame(PumpSelectorHydraulic::GATE_BORDERLINE, $this->hydraulic->getHydraulicGate(0.5, -0.0001));
        $this->assertSame(PumpSelectorHydraulic::GATE_PASS, $this->hydraulic->getHydraulicGate(0.5, 0.0));
        $this->assertSame(PumpSelectorHydraulic::GATE_FAIL, $this->hydraulic->getHydraulicGate(1.0001, 0.20));
    }

    public function testReservePenaltyBoundaries()
    {
        $this->assertEquals(0.02, $this->hydraulic->calculateReservePenalty(0.09), '', 0.000001);
        $this->assertEquals(0.0, $this->hydraulic->calculateReservePenalty(0.10), '', 0.000001);
        $this->assertEquals(0.0, $this->hydraulic->calculateReservePenalty(0.25), '', 0.000001);
        $this->assertEquals(0.05, $this->hydraulic->calculateReservePenalty(0.30), '', 0.000001);
        $this->assertEquals(0.40, $this->hydraulic->calculateReservePenalty(-0.10), '', 0.000001);
    }

    public function testReserveGradeBoundaries()
    {
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getReserveGrade(0.0, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getReserveGrade(0.0499, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getReserveGrade(0.05, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getReserveGrade(0.0999, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $this->hydraulic->getReserveGrade(0.10, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $this->hydraulic->getReserveGrade(0.20, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getReserveGrade(0.2001, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getReserveGrade(0.35, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getReserveGrade(0.3501, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getReserveGrade(0.50, PumpSelectorHydraulic::GATE_PASS));
        $this->assertSame(PumpSelectorHydraulic::GRADE_POOR, $this->hydraulic->getReserveGrade(0.5001, PumpSelectorHydraulic::GATE_PASS));
        $this->assertNull($this->hydraulic->getReserveGrade(-0.01, PumpSelectorHydraulic::GATE_BORDERLINE));
        $this->assertNull($this->hydraulic->getReserveGrade(-0.20, PumpSelectorHydraulic::GATE_FAIL));
    }

    public function testFitGradeBoundaries()
    {
        $this->assertSame(PumpSelectorHydraulic::GRADE_IDEAL, $this->hydraulic->getFitGrade(0.0));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getFitGrade(0.0001));
        $this->assertSame(PumpSelectorHydraulic::GRADE_GOOD, $this->hydraulic->getFitGrade(0.10));
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getFitGrade(0.1001));
        $this->assertSame(PumpSelectorHydraulic::GRADE_ACCEPTABLE, $this->hydraulic->getFitGrade(0.22));
        $this->assertSame(PumpSelectorHydraulic::GRADE_POOR, $this->hydraulic->getFitGrade(0.2201));
    }

    public function testTechnicalFitIsZeroInsideTargetBox()
    {
        $this->assertEquals(0.0, $this->hydraulic->calculateTechnicalFit(0.35, 0.45), '', 0.000001);
        $this->assertEquals(0.0, $this->hydraulic->calculateTechnicalFit(0.50, 0.60), '', 0.000001);
        $this->assertEquals(0.0, $this->hydraulic->calculateTechnicalFit(0.65, 0.75), '', 0.000001);
    }

    public function testTechnicalFitMeasuresDistanceOutsideTargetBox()
    {
        $expected = sqrt((0.05 * 0.05) + (0.05 * 0.05));
        $this->assertEquals($expected, $this->hydraulic->calculateTechnicalFit(0.70, 0.80), '', 0.000001);
    }

    public function testDecorateCandidateRejectsZeroMaxFlow()
    {
        $this->setExpectedException('InvalidArgumentException');

        $this->hydraulic->decorateCandidate(
            array(
                'max_head_m' => 100.0,
                'max_flow_l_min' => 0.0
            ),
            array(
                'required_head_m' => 70.0,
                'required_flow_l_min' => 40.0
            )
        );
    }

    public function testDecorateCandidateRejectsMissingRequiredKey()
    {
        $this->setExpectedException('InvalidArgumentException');

        $this->hydraulic->decorateCandidate(
            array(
                'max_head_m' => 100.0
            ),
            array(
                'required_head_m' => 70.0,
                'required_flow_l_min' => 40.0
            )
        );
    }
}
