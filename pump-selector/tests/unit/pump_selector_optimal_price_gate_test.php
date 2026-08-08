<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_optimal_price_gate.php';

class PumpSelectorOptimalPriceGateTest extends PHPUnit_Framework_TestCase {
    public function testKeepsCandidateExactlyAtOnePointEightTimesBestPrice() {
        $gate = new PumpSelectorOptimalPriceGate();

        $result = $gate->filter(
            array(
                array('product_id' => 1, 'price' => 180.0),
                array('product_id' => 2, 'price' => 180.01),
            ),
            array('product_id' => 10, 'price' => 100.0)
        );

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['product_id']);
    }

    public function testFiltersExpensiveTechnicallyBetterCandidate() {
        $gate = new PumpSelectorOptimalPriceGate();

        $result = $gate->filter(
            array(
                array('product_id' => 1600, 'price' => 22370.0),
                array('product_id' => 1830, 'price' => 68900.0),
            ),
            array('product_id' => 1569, 'price' => 16380.0)
        );

        $this->assertCount(1, $result);
        $this->assertSame(1600, $result[0]['product_id']);
    }

    public function testReturnsEmptyWhenBestPriceIsMissing() {
        $gate = new PumpSelectorOptimalPriceGate();

        $result = $gate->filter(
            array(array('product_id' => 1, 'price' => 100.0)),
            null
        );

        $this->assertSame(array(), $result);
    }
}
