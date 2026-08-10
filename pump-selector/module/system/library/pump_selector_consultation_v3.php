<?php

/**
 * Pure presentation and validation helper for the V3 consultation endpoint.
 *
 * Ranking remains in ModelExtensionModulePumpSelectorV3. This class receives
 * only its already server-generated cards and never reads browser product data.
 */
class PumpSelectorConsultationV3 {
    public function validateContact(array $post) {
        $contact = array(
            'name' => $this->getTrimmedValue($post, 'name'),
            'telephone' => $this->getTrimmedValue($post, 'telephone'),
            'email' => $this->getTrimmedValue($post, 'email')
        );
        $errors = array();

        if ($contact['name'] === '') {
            $errors['name'] = 'Укажите имя.';
        }

        if ($contact['telephone'] === '') {
            $errors['telephone'] = 'Укажите телефон.';
        }

        if ($contact['email'] !== '' && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Укажите корректный email.';
        }

        return array(
            'contact' => $contact,
            'errors' => $errors
        );
    }

    public function buildRecommendations(array $products) {
        $recommendations = array();

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $recommendations[] = array(
                'role' => $this->getRoleLabel($this->getProductRole($product)),
                'name' => $this->getProductValue($product, 'name'),
                'manufacturer' => $this->getProductValue($product, 'manufacturer'),
                'price' => $this->getDisplayedPrice($product),
                'href' => $this->getProductValue($product, 'href')
            );
        }

        return $recommendations;
    }

    public function shouldSendClientEmail(array $contact) {
        return isset($contact['email']) && $contact['email'] !== '';
    }

    public function buildManagerText(array $contact, array $input, array $requirements, array $recommendations) {
        $lines = array(
            'Заявка на консультацию по подбору скважинного насоса',
            '',
            'Клиент: ' . $contact['name'],
            'Телефон: ' . $contact['telephone'],
            'Email: ' . ($contact['email'] !== '' ? $contact['email'] : 'не указан'),
            '',
            'Исходные параметры selector:',
            $this->buildSelectorInputText($input),
            '',
            'Расчётные требования:',
            'Напор: ' . $this->getRequirementValue($requirements, 'required_head_m') . ' м',
            'Расход: ' . $this->getRequirementValue($requirements, 'required_flow_l_min') . ' л/мин',
            'Напряжение: ' . $this->getRequirementValue($requirements, 'selected_voltage') . ' В',
            '',
            'Результаты V3:'
        );

        if (!$recommendations) {
            $lines[] = 'Автоматический подбор не нашёл подходящих рекомендаций.';
        } else {
            foreach ($recommendations as $index => $recommendation) {
                $lines[] = ($index + 1) . '. ' . $recommendation['role'];
                $lines[] = '   Насос: ' . $recommendation['name'];
                $lines[] = '   Производитель: ' . $recommendation['manufacturer'];
                $lines[] = '   Цена: ' . $recommendation['price'];
                $lines[] = '   Ссылка: ' . $recommendation['href'];
            }
        }

        return implode("\n", $lines);
    }

    public function buildClientText(array $requirements, array $recommendations) {
        $lines = array(
            'Результаты подбора скважинного насоса',
            '',
            'Расчётный напор: ' . $this->getRequirementValue($requirements, 'required_head_m') . ' м',
            'Расчётный расход: ' . $this->getRequirementValue($requirements, 'required_flow_l_min') . ' л/мин',
            ''
        );

        if (!$recommendations) {
            $lines[] = 'Автоматический подбор не нашёл подходящего варианта. Менеджер свяжется с вами для уточнения условий скважины.';
        } else {
            $lines[] = 'Подходящие варианты:';
            foreach ($recommendations as $index => $recommendation) {
                $lines[] = ($index + 1) . '. ' . $recommendation['role'] . ': ' . $recommendation['name'] . ' (' . $recommendation['manufacturer'] . '), ' . $recommendation['price'];
                $lines[] = $recommendation['href'];
            }
            $lines[] = '';
            $lines[] = 'Подбор предварительный. Специалист может уточнить модель с учётом фактических условий скважины.';
        }

        return implode("\n", $lines);
    }

    public function buildClientHtml(array $requirements, array $recommendations) {
        $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,sans-serif;color:#222;">';
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f5f5f5;"><tr><td style="padding:20px;">';
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-collapse:collapse;">';
        $html .= '<tr><td style="padding:24px;border-bottom:1px solid #e5e5e5;"><h1 style="margin:0;font-size:22px;line-height:28px;">Результаты подбора скважинного насоса</h1></td></tr>';
        $html .= '<tr><td style="padding:24px;"><p style="margin:0 0 16px;line-height:22px;">Мы повторно рассчитали рекомендации по указанным параметрам.</p>';
        $html .= '<p style="margin:0 0 20px;line-height:22px;"><strong>Расчётный напор:</strong> ' . $this->escapeHtml($this->getRequirementValue($requirements, 'required_head_m')) . ' м<br><strong>Расчётный расход:</strong> ' . $this->escapeHtml($this->getRequirementValue($requirements, 'required_flow_l_min')) . ' л/мин</p>';

        if (!$recommendations) {
            $html .= '<p style="margin:0;line-height:22px;">Автоматический подбор не нашёл подходящего варианта. Менеджер свяжется с вами для уточнения условий скважины.</p>';
        } else {
            $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;font-size:14px;border:1px solid #dddddd;">';
            $html .= '<tr><th align="left" style="padding:10px;border:1px solid #dddddd;background:#f1f1f1;">Роль</th><th align="left" style="padding:10px;border:1px solid #dddddd;background:#f1f1f1;">Насос</th><th align="left" style="padding:10px;border:1px solid #dddddd;background:#f1f1f1;">Производитель</th><th align="left" style="padding:10px;border:1px solid #dddddd;background:#f1f1f1;">Цена</th><th align="left" style="padding:10px;border:1px solid #dddddd;background:#f1f1f1;">Ссылка</th></tr>';
            foreach ($recommendations as $recommendation) {
                $href = $this->escapeHtml($recommendation['href']);
                $html .= '<tr><td style="padding:10px;border:1px solid #dddddd;">' . $this->escapeHtml($recommendation['role']) . '</td><td style="padding:10px;border:1px solid #dddddd;">' . $this->escapeHtml($recommendation['name']) . '</td><td style="padding:10px;border:1px solid #dddddd;">' . $this->escapeHtml($recommendation['manufacturer']) . '</td><td style="padding:10px;border:1px solid #dddddd;">' . $this->escapeHtml($recommendation['price']) . '</td><td style="padding:10px;border:1px solid #dddddd;"><a href="' . $href . '" style="color:#1a73e8;">Посмотреть товар</a></td></tr>';
            }
            $html .= '</table><p style="margin:20px 0 0;line-height:22px;">Подбор предварительный. Специалист может уточнить модель с учётом фактических условий скважины.</p>';
        }

        $html .= '</td></tr></table></td></tr></table></body></html>';

        return $html;
    }

    public function getRoleLabel($role) {
        $labels = array(
            'best_price' => 'Лучшая цена',
            'optimal_choice' => 'Оптимальный выбор',
            'premium' => 'Премиум качество',
            'alternative' => 'Альтернатива'
        );

        return isset($labels[$role]) ? $labels[$role] : 'Рекомендация';
    }

    private function buildSelectorInputText(array $input) {
        $points = isset($input['water_points']) && is_array($input['water_points']) ? implode(', ', $input['water_points']) : '';
        $casing = isset($input['casing_diameter_mode']) && $input['casing_diameter_mode'] == 'known' ? $this->getInputValue($input, 'casing_diameter_mm') . ' мм' : 'не указан';
        $water_level = isset($input['water_level_mode']) && $input['water_level_mode'] == 'known' ? $this->getInputValue($input, 'water_level_m') . ' м' : 'не указан';

        return 'Глубина скважины: ' . $this->getInputValue($input, 'total_well_depth_m') . ' м' . "\n"
            . 'Уровень воды: ' . $water_level . "\n"
            . 'Расстояние до дома: ' . $this->getInputValue($input, 'distance_to_house_m') . ' м' . "\n"
            . 'Высшая точка водоразбора: ' . $this->getInputValue($input, 'highest_water_point_floor') . "\n"
            . 'Пользовательский вертикальный подъём: ' . ($this->getInputValue($input, 'custom_vertical_lift_m') !== '' ? $this->getInputValue($input, 'custom_vertical_lift_m') . ' м' : 'не указан') . "\n"
            . 'Точки водоразбора: ' . ($points !== '' ? $points : 'не выбраны') . "\n"
            . 'Обсадная труба: ' . $casing . "\n"
            . 'Напряжение: ' . $this->getInputValue($input, 'voltage_mode');
    }

    private function getProductRole(array $product) {
        if (isset($product['result_type']) && $product['result_type'] !== '') {
            return (string)$product['result_type'];
        }
        if (isset($product['result_types']) && is_array($product['result_types']) && isset($product['result_types'][0])) {
            return (string)$product['result_types'][0];
        }

        return '';
    }

    private function getDisplayedPrice(array $product) {
        if (isset($product['special']) && $product['special'] !== false && $product['special'] !== '') {
            return $this->toPlainText($product['special']);
        }

        return $this->toPlainText($this->getProductValue($product, 'price'));
    }

    private function getProductValue(array $product, $key) {
        return isset($product[$key]) ? $this->toPlainText($product[$key]) : '';
    }

    private function getRequirementValue(array $requirements, $key) {
        return isset($requirements[$key]) ? $this->toPlainText($requirements[$key]) : '';
    }

    private function getInputValue(array $input, $key) {
        return isset($input[$key]) ? $this->toPlainText($input[$key]) : '';
    }

    private function getTrimmedValue(array $post, $key) {
        return isset($post[$key]) && !is_array($post[$key]) ? trim((string)$post[$key]) : '';
    }

    private function toPlainText($value) {
        return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8'));
    }

    private function escapeHtml($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
