<?php

require_once(DIR_SYSTEM . 'library/pump_selector_hydraulic.php');
require_once(DIR_SYSTEM . 'library/pump_selector_assignment_v3.php');

/**
 * Isolated runtime model for the future Ranking V3 pipeline.
 *
 * It is intentionally not wired into the current selector route. This first
 * step loads eligible candidates, evaluates hydraulics, assigns Best Price and
 * records the selected product in the Exclusion Pool only.
 */
class ModelExtensionModulePumpSelectorV3 extends Model {
    const BRAND_TIER_UNKNOWN = 0;
    const BRAND_TIER_STANDARD = 1;
    const BRAND_TIER_UPPER = 2;
    const BRAND_TIER_PREMIUM = 3;

    public function getRecommendedProducts($requirements) {
        $raw_candidates = $this->loadCandidates($requirements);

        if (!$raw_candidates) {
            return array();
        }

        $hydraulic = new PumpSelectorHydraulic();
        $assignment = new PumpSelectorAssignmentV3();
        $decorated = array();

        foreach ($raw_candidates as $candidate) {
            $decorated[] = $hydraulic->decorateCandidate($candidate, $requirements);
        }

        $assignment_result = $this->assignBestPrice($decorated, $assignment);

        if ($assignment_result === null) {
            return array();
        }

        $best_price = $assignment_result['candidate'];
        $best_price['result_type'] = 'best_price';
        $best_price['result_types'] = array('best_price');

        return array($this->prepareProductCardData($best_price));
    }

    /**
     * Keep sequential assignment state internal to the V3 orchestration.
     *
     * @param array $decorated_candidates
     * @param PumpSelectorAssignmentV3 $assignment
     * @return array|null
     */
    protected function assignBestPrice(array $decorated_candidates, PumpSelectorAssignmentV3 $assignment) {
        $best_price = $assignment->selectBestPrice($decorated_candidates);

        if ($best_price === null) {
            return null;
        }

        return array(
            'candidate' => $best_price,
            'excluded_product_ids' => $assignment->addToExclusionPool(array(), $best_price)
        );
    }

    protected function loadCandidates($requirements) {
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
                'price' => (float)$row['price']
            );
        }

        return $candidates;
    }

    protected function prepareProductCardData($product) {
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

    protected function getBrandTierFromPriority($brand_priority) {
        $brand_priority = (int)$brand_priority;

        if ($brand_priority >= 10) {
            return self::BRAND_TIER_PREMIUM;
        }

        if ($brand_priority >= 8) {
            return self::BRAND_TIER_UPPER;
        }

        if ($brand_priority >= 5) {
            return self::BRAND_TIER_STANDARD;
        }

        return self::BRAND_TIER_UNKNOWN;
    }

    protected function getCustomerGroupId() {
        if ($this->customer->isLogged()) {
            return $this->customer->getGroupId();
        }

        return $this->config->get('config_customer_group_id');
    }

    protected function getValue($array, $key, $default) {
        if (is_array($array) && isset($array[$key])) {
            return $array[$key];
        }

        return $default;
    }
}
