<?php

require_once dirname(__FILE__) . '/../../module/system/library/pump_selector_consultation_v3.php';

class PumpSelectorConsultationV3Test extends PHPUnit_Framework_TestCase {
    private $consultation;

    protected function setUp() {
        $this->consultation = new PumpSelectorConsultationV3();
    }

    public function testNameIsRequired() {
        $result = $this->consultation->validateContact(array('telephone' => '+380501234567'));
        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function testTelephoneIsRequired() {
        $result = $this->consultation->validateContact(array('name' => 'Анна'));
        $this->assertArrayHasKey('telephone', $result['errors']);
    }

    public function testOptionalEmailMustBeValidWhenProvided() {
        $result = $this->consultation->validateContact(array('name' => 'Анна', 'telephone' => '+380501234567', 'email' => 'invalid'));
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testOptionalEmailMayBeEmpty() {
        $result = $this->consultation->validateContact(array('name' => 'Анна', 'telephone' => '+380501234567', 'email' => ''));
        $this->assertEmpty($result['errors']);
        $this->assertFalse($this->consultation->shouldSendClientEmail($result['contact']));
    }

    public function testClientEmailIsSentOnlyWhenValidatedEmailWasProvided() {
        $result = $this->consultation->validateContact(array('name' => 'Анна', 'telephone' => '+380501234567', 'email' => 'anna@example.test'));
        $this->assertEmpty($result['errors']);
        $this->assertTrue($this->consultation->shouldSendClientEmail($result['contact']));
    }

    public function testRecommendationsUseOnlyServerGeneratedProducts() {
        $server_products = array($this->product('alternative', 'Серверный насос', 'VINKO', '95 500 грн', 'https://example.test/product'));
        $recommendations = $this->consultation->buildRecommendations($server_products);
        $html = $this->consultation->buildClientHtml($this->requirements(), $recommendations);

        $this->assertTrue(strpos($html, 'Серверный насос') !== false);
        $this->assertTrue(strpos($html, 'Поддельный насос') === false);
        $this->assertSame('Альтернатива', $recommendations[0]['role']);
    }

    public function testOneTwoAndThreeRecommendationsAreNotArtificiallyFilled() {
        $one = $this->consultation->buildRecommendations(array($this->product('best_price')));
        $two = $this->consultation->buildRecommendations(array($this->product('best_price'), $this->product('optimal_choice')));
        $three = $this->consultation->buildRecommendations(array($this->product('best_price'), $this->product('optimal_choice'), $this->product('premium')));

        $this->assertCount(1, $one);
        $this->assertCount(2, $two);
        $this->assertCount(3, $three);
    }

    public function testZeroRecommendationsAreAllowed() {
        $html = $this->consultation->buildClientHtml($this->requirements(), array());
        $this->assertTrue(strpos($html, 'Автоматический подбор не нашёл подходящего варианта') !== false);
        $this->assertTrue(strpos($html, '<th align=') === false);
    }

    public function testClientHtmlEscapesServerGeneratedProductData() {
        $recommendations = $this->consultation->buildRecommendations(array($this->product('premium', '<script>alert(1)</script>', 'Grundfos', '10 < 20', 'https://example.test/?a=1&b=2')));
        $html = $this->consultation->buildClientHtml($this->requirements(), $recommendations);

        $this->assertTrue(strpos($html, '<script>') === false);
        $this->assertTrue(strpos($html, 'https://example.test/?a=1&amp;b=2') !== false);
    }

    private function product($role, $name = 'Насос', $manufacturer = 'Производитель', $price = '10 000 грн', $href = 'https://example.test/product') {
        return array(
            'result_type' => $role,
            'name' => $name,
            'manufacturer' => $manufacturer,
            'price' => $price,
            'href' => $href
        );
    }

    private function requirements() {
        return array('required_head_m' => 75, 'required_flow_l_min' => 40);
    }
}
