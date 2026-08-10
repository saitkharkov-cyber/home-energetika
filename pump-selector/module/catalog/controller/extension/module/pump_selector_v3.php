<?php

require_once(DIR_SYSTEM . 'library/pump_selector_consultation_v3.php');

class ControllerExtensionModulePumpSelectorV3 extends Controller {
    public function index() {
        $this->load->model('extension/module/pump_selector');
        $this->load->model('extension/module/pump_selector_v3');

        $this->document->setTitle('Подбор скважинного насоса — Ranking v3');

        $data = array();
        $data['heading_title'] = 'SMART - подбор скважинного насоса (Ranking v3)';
        $data['action'] = $this->url->link('extension/module/pump_selector_v3', '', true);
        $data['errors'] = array();
        $data['requirements'] = null;
        $data['products'] = array();
        $data['warnings'] = array();
        $data['assumptions'] = array();
        $data['submitted'] = false;
        $data['input'] = $this->getDefaultInput();

        if (isset($this->request->server['REQUEST_METHOD']) && $this->request->server['REQUEST_METHOD'] == 'POST') {
            if (!empty($this->request->post['website'])) {
                $this->response->redirect($this->url->link('extension/module/pump_selector_v3'));
                return;
            }

            $data['submitted'] = true;
            $input = $this->buildInputFromPost();
            $data['input'] = $input;

            $errors = $this->model_extension_module_pump_selector->validateInput($input);
            $data['errors'] = $errors;

            if (!$errors) {
                $requirements = $this->model_extension_module_pump_selector->calculateRequirements($input);
                $products = $this->model_extension_module_pump_selector_v3->getRecommendedProducts($requirements);

                $data['requirements'] = $requirements;
                $data['products'] = $products;
                $data['warnings'] = $requirements['warnings'];
                $data['assumptions'] = $requirements['assumptions'];
            }
        }

        $data['water_point_labels'] = array(
            'sink'             => 'Раковина',
            'shower'           => 'Душ',
            'toilet'           => 'Унитаз',
            'washing_machine'  => 'Стиральная машина',
            'dishwasher'       => 'Посудомоечная машина',
            'irrigation'       => 'Полив'
        );

        $data['result_type_labels'] = array(
            'best_price'      => 'Лучшая цена',
            'optimal_choice'  => 'Оптимальный выбор',
            'premium'         => 'Премиум качество'
        );

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => 'Главная',
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => 'Подбор скважинного насоса — Ranking v3',
            'href' => $this->url->link('extension/module/pump_selector_v3')
        );

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/module/pump_selector_v3', $data));
    }

    /**
     * Receives only raw selector inputs and contact data. Product cards are
     * recalculated on the server and are never read from POST.
     */
    public function consult() {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');

        if (!isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] != 'POST') {
            $this->sendConsultJson(array(
                'success' => false,
                'errors' => array('request' => 'Поддерживается только POST-запрос.')
            ));
            return;
        }

        if (!empty($this->request->post['website'])) {
            // Honeypot submissions receive no mail and no useful signal.
            $this->sendConsultJson(array('success' => true, 'client_email_sent' => false));
            return;
        }

        $this->load->model('extension/module/pump_selector');
        $this->load->model('extension/module/pump_selector_v3');

        $consultation = new PumpSelectorConsultationV3();
        $contact_result = $consultation->validateContact($this->request->post);
        $input = $this->buildInputFromPost();
        $errors = $this->model_extension_module_pump_selector->validateInput($input);

        foreach ($contact_result['errors'] as $key => $message) {
            $errors[$key] = $message;
        }

        if ($errors) {
            $this->sendConsultJson(array('success' => false, 'errors' => $errors));
            return;
        }

        try {
            $requirements = $this->model_extension_module_pump_selector->calculateRequirements($input);
            $products = $this->model_extension_module_pump_selector_v3->getRecommendedProducts($requirements);
            $recommendations = $consultation->buildRecommendations($products);
        } catch (Exception $exception) {
            $this->sendConsultJson(array('success' => false, 'error' => 'Не удалось отправить заявку'));
            return;
        }

        try {
            $manager_mail = $this->createConsultMail();
            $manager_mail->setTo($this->config->get('config_email'));
            $manager_mail->setFrom($this->config->get('config_email'));
            $manager_mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
            $manager_mail->setSubject('Заявка на консультацию по подбору скважинного насоса');
            $manager_mail->setText($consultation->buildManagerText(
                $contact_result['contact'],
                $input,
                $requirements,
                $recommendations
            ));
            $manager_mail->send();
        } catch (Exception $exception) {
            $this->sendConsultJson(array('success' => false, 'error' => 'Не удалось отправить заявку'));
            return;
        }

        $client_email_sent = false;

        if ($consultation->shouldSendClientEmail($contact_result['contact'])) {
            try {
                $client_mail = $this->createConsultMail();
                $client_mail->setTo($contact_result['contact']['email']);
                $client_mail->setFrom($this->config->get('config_email'));
                $client_mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
                $client_mail->setSubject('Результаты подбора скважинного насоса');
                $client_mail->setText($consultation->buildClientText($requirements, $recommendations));
                $client_mail->setHtml($consultation->buildClientHtml($requirements, $recommendations));
                $client_mail->send();
                $client_email_sent = true;
            } catch (Exception $exception) {
                // The manager already received the lead; do not resend it.
                $client_email_sent = false;
            }
        }

        $this->sendConsultJson(array('success' => true, 'client_email_sent' => $client_email_sent));
    }

    private function buildInputFromPost() {
        $post = $this->request->post;

        return array(
            'total_well_depth_m'          => $this->getPostValue($post, 'total_well_depth_m', ''),
            'water_level_mode'           => $this->getPostValue($post, 'water_level_mode', 'unknown'),
            'water_level_m'              => $this->getPostValue($post, 'water_level_m', ''),
            'distance_to_house_m'        => $this->getPostValue($post, 'distance_to_house_m', ''),
            'highest_water_point_floor'  => $this->getPostValue($post, 'highest_water_point_floor', '1'),
            'custom_vertical_lift_m'     => $this->getPostValue($post, 'custom_vertical_lift_m', ''),
            'water_points'               => $this->getPostValue($post, 'water_points', array()),
            'casing_diameter_mode'       => $this->getPostValue($post, 'casing_diameter_mode', 'unknown'),
            'casing_diameter_mm'         => $this->getPostValue($post, 'casing_diameter_mm', ''),
            'voltage_mode'               => $this->getPostValue($post, 'voltage_mode', 'unknown')
        );
    }

    private function getDefaultInput() {
        return array(
            'total_well_depth_m'          => '',
            'water_level_mode'           => 'unknown',
            'water_level_m'              => '',
            'distance_to_house_m'        => '',
            'highest_water_point_floor'  => '1',
            'custom_vertical_lift_m'     => '',
            'water_points'               => array(),
            'casing_diameter_mode'       => 'unknown',
            'casing_diameter_mm'         => '',
            'voltage_mode'               => 'unknown'
        );
    }

    private function getPostValue($post, $key, $default) {
        if (isset($post[$key])) {
            return $post[$key];
        }

        return $default;
    }

    private function createConsultMail() {
        $store_email = trim((string)$this->config->get('config_email'));

        if ($store_email === '') {
            throw new Exception('Store email is not configured.');
        }

        $mail = new Mail($this->config->get('config_mail_protocol'));
        $mail->parameter = $this->config->get('config_mail_parameter');
        $mail->smtp_hostname = $this->config->get('config_smtp_hostname');
        $mail->smtp_username = $this->config->get('config_smtp_username');
        $mail->smtp_password = html_entity_decode($this->config->get('config_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $this->config->get('config_smtp_port');
        $mail->smtp_timeout = $this->config->get('config_smtp_timeout');

        return $mail;
    }

    private function sendConsultJson(array $data) {
        $this->response->setOutput(json_encode($data));
    }
}
