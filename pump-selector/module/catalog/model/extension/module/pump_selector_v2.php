<?php

require_once(DIR_SYSTEM . 'library/pump_selector_hydraulic.php');
require_once(DIR_SYSTEM . 'library/pump_selector_pareto.php');
require_once(DIR_SYSTEM . 'library/pump_selector_ranking.php');

class ModelExtensionModulePumpSelectorV2 extends Model {
    public function getRecommendedProducts($requirements) {
        $raw_candidates = $this->loadCandidates($requirements);

        if (!$raw_candidates) {
            return array();
        }

        $hydraulic = new PumpSelectorHydraulic();
        $pareto = new PumpSelectorPareto(0.02);
        $ranking = new PumpSelectorRanking();

        $decorated = array();
        $pass_candidates = array();
        $premium_candidates = array();

        foreach ($raw_candidates as $candidate) {
            $candidate = $hydraulic->decorateCandidate($candidate, $requirements);
            $decorated[] = $candidate;

            if ($candidate['hydraulic_gate'] === PumpSelectorHydraulic::GATE_PASS) {
                $pass_candidates[] = $candidate;

                if ((int)$candidate['brand_tier'] >= PumpSelectorRanking::BRAND_TIER_UPPER) {
                    $premium_candidates[] = $candidate;
                }
            }
        }

        $best_price = $ranking->selectBestPrice($decorated);
        $optimal = null;
        $premium = null;

        if ($pass_candidates) {
            $general_front = $pareto->buildFront($pass_candidates);
            $optimal = $ranking->selectOptimalFromPassPareto($general_front);
        }

        if ($optimal && $premium_candidates) {
            $premium_front = $pareto->buildFront($premium_candidates);
            $premium = $ranking->selectPremium($premium_front, $optimal);
        }

        $candidates = array(
            $this->withResultType($best_price, 'best_price'),
            $this->withResultType($optimal, 'optimal_choice'),
            $this->withResultType($premium, 'premium')
        );

        return $this->prepareResultCards($candidates);
    }

    private function loadCandidates($requirements) {
        $selected_voltage = $this->db->escape((string)$this->getValue($requirements, 'selected_voltage', '220'));

        $where = array();
        $where[] = "psp.is_eligible = 1";
        $where[] = "psp.product_price > 0";
        $where[] = "psp.quantity > 0";
        $where[] = "psp.status = 1";
        $where[] = "psp.max_head_m > 0";
        $where[] = "psp.max_flow_l_min > 0";
        $where[] = "psp.voltage = '" . $selected_voltage . "'";

        if (isset($requirements['casing_diameter_mm']) && $requirements['casing_diameter_mm'] !== null && $requirements['casing_diameter_mm'] !== '') {
            $where[] = "psp.min_casing_inner_diameter_mm <= " . (float)$requirements['casing_diameter_mm'];
        }

        $sql = "SELECT";
        $sql .= " psp.product_id,";
        $sql .= " psp.max_head_m,";
        $sql .= " psp.max_flow_l_min,";
        $sql .= " psp.pump_diameter_mm,";
        $sql .= " psp.min_casing_inner_diameter_mm,";
        $sql .= " psp.voltage,";
        $sql .= " psp.brand_priority,";
        $sql .= " psp.product_price AS price";
        $sql .= " FROM " . DB_PREFIX . "pump_selector_product psp";
        $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " ORDER BY psp.product_id ASC";

        $query = $this->db->query($sql);

        if (!$query->num_rows) {
            return array();
        }

        $candidates = array();

        foreach ($query->rows as $row) {
            $brand_tier = $this->getBrandTierFromPriority((int)$row['brand_priority']);

            $candidates[] = array(
                'product_id' => (int)$row['product_id'],
                'max_head_m' => (float)$row['max_head_m'],
                'max_flow_l_min' => (float)$row['max_flow_l_min'],
                'pump_diameter_mm' => $row['pump_diameter_mm'],
                'min_casing_inner_diameter_mm' => (float)$row['min_casing_inner_diameter_mm'],
                'voltage' => (string)$row['voltage'],
                'brand_priority' => (int)$row['brand_priority'],
                'brand_tier' => $brand_tier,
                'brand_factor' => (float)$brand_tier,
                'price' => (float)$row['price']
            );
        }

        return $candidates;
    }

    private function getBrandTierFromPriority($brand_priority) {
        $brand_priority = (int)$brand_priority;

        if ($brand_priority >= 10) {
            return PumpSelectorRanking::BRAND_TIER_PREMIUM;
        }

        if ($brand_priority >= 8) {
            return PumpSelectorRanking::BRAND_TIER_UPPER;
        }

        if ($brand_priority >= 5) {
            return PumpSelectorRanking::BRAND_TIER_STANDARD;
        }

        return PumpSelectorRanking::BRAND_TIER_UNKNOWN;
    }

    private function withResultType($candidate, $result_type) {
        if (!$candidate || !isset($candidate['product_id'])) {
            return null;
        }

        $candidate['result_type'] = $result_type;
        return $candidate;
    }

    private function prepareResultCards($candidates) {
        $products = array();

        foreach ($candidates as $candidate) {
            if (!$candidate || !isset($candidate['product_id'])) {
                continue;
            }

            $product_id = (int)$candidate['product_id'];

            if (!isset($products[$product_id])) {
                $candidate['result_types'] = array($candidate['result_type']);
                unset($candidate['result_type']);
                $products[$product_id] = $candidate;
            } elseif (!in_array($candidate['result_type'], $products[$product_id]['result_types'])) {
                $products[$product_id]['result_types'][] = $candidate['result_type'];
            }
        }

        $prepared = array();

        foreach ($products as $product) {
            $prepared[] = $this->prepareProductCardData($product);
        }

        return $prepared;
    }

    private function prepareProductCardData($product) {
        $this->load->model('tool/image');

        $product_id = (int)$product['product_id'];
        $language_id = (int)$this->config->get('config_language_id');

        $query = $this->db->query("SELECT p.product_id, p.image, p.price, p.quantity, p.minimum, p.tax_class_id, pd.name, m.name AS manufacturer, ss.name AS stock_status FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (pd.product_id = p.product_id AND pd.language_id = " . $language_id . ") LEFT JOIN " . DB_PREFIX . "manufacturer m ON (m.manufacturer_id = p.manufacturer_id) LEFT JOIN " . DB_PREFIX . "stock_status ss ON (ss.stock_status_id = p.stock_status_id AND ss.language_id = " . $language_id . ") WHERE p.product_id = " . $product_id . " LIMIT 1");

        if (!$query->num_rows) {
            return $product;
        }

        $product_info = $query->row;
        $width = (int)$this->config->get($this->config->get('config_theme') . '_image_product_width');
        $height = (int)$this->config->get($this->config->get('config_theme') . '_image_product_height');

        if ($width <= 0) {
            $width = 200;
        }

        if ($height <= 0) {
            $height = 200;
        }

        if ($product_info['image']) {
            $thumb = $this->model_tool_image->resize($product_info['image'], $width, $height);
        } else {
            $thumb = $this->model_tool_image->resize('placeholder.png', $width, $height);
        }

        if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
            $price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        } else {
            $price = false;
        }

        $customer_group_id = $this->getCustomerGroupId();
        $special_query = $this->db->query("SELECT price FROM " . DB_PREFIX . "product_special WHERE product_id = " . $product_id . " AND customer_group_id = " . (int)$customer_group_id . " AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) ORDER BY priority ASC, price ASC LIMIT 1");

        if ($special_query->num_rows) {
            $special = $this->currency->format($this->tax->calculate($special_query->row['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
        } else {
            $special = false;
        }

        if ($this->config->get('config_tax')) {
            $tax_price = $special_query->num_rows ? $special_query->row['price'] : $product_info['price'];
            $tax = $this->currency->format((float)$tax_price, $this->session->data['currency']);
        } else {
            $tax = false;
        }

        if ((int)$product_info['quantity'] <= 0) {
            $stock_status = $product_info['stock_status'];
        } else {
            $stock_status = 'В наличии';
        }

        $minimum = (int)$product_info['minimum'];
        if ($minimum <= 0) {
            $minimum = 1;
        }

        $product['name'] = $product_info['name'];
        $product['href'] = $this->url->link('product/product', 'product_id=' . $product_id);
        $product['image'] = $product_info['image'];
        $product['thumb'] = $thumb;
        $product['manufacturer'] = $product_info['manufacturer'];
        $product['price'] = $price;
        $product['special'] = $special;
        $product['stock_status'] = $stock_status;
        $product['minimum'] = $minimum;
        $product['tax'] = $tax;
        $product['price_raw'] = (float)$product_info['price'];

        return $product;
    }

    private function getCustomerGroupId() {
        if ($this->customer->isLogged()) {
            return $this->customer->getGroupId();
        }

        return $this->config->get('config_customer_group_id');
    }

    private function getValue($array, $key, $default) {
        if (is_array($array) && isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }
}
