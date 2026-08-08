<?php

/**
 * Price guard for the Ranking v2 Optimal role.
 *
 * PHP 5.6 compatible. No OpenCart bootstrap or database dependencies.
 */
class PumpSelectorOptimalPriceGate {
    const DEFAULT_MAX_PRICE_RATIO = 1.8;

    private $max_price_ratio;

    public function __construct($max_price_ratio = self::DEFAULT_MAX_PRICE_RATIO) {
        $max_price_ratio = (float)$max_price_ratio;

        if ($max_price_ratio < 1.0) {
            throw new InvalidArgumentException('max_price_ratio must be at least 1.0.');
        }

        $this->max_price_ratio = $max_price_ratio;
    }

    /**
     * Keep only candidates whose price is not greater than Best Price × ratio.
     *
     * @param array $candidates
     * @param array|null $best_price
     * @return array
     */
    public function filter(array $candidates, $best_price) {
        if (!$best_price || !is_array($best_price)) {
            return array();
        }

        if (!isset($best_price['price']) || !is_numeric($best_price['price']) || (float)$best_price['price'] <= 0) {
            throw new InvalidArgumentException('Best Price candidate must have a positive numeric price.');
        }

        $price_limit = (float)$best_price['price'] * $this->max_price_ratio;
        $eligible = array();

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                throw new InvalidArgumentException('Each candidate must be an array.');
            }

            if (!isset($candidate['price']) || !is_numeric($candidate['price']) || (float)$candidate['price'] <= 0) {
                throw new InvalidArgumentException('Candidate price must be positive and numeric.');
            }

            if ((float)$candidate['price'] <= $price_limit) {
                $eligible[] = $candidate;
            }
        }

        return $eligible;
    }

    public function getMaxPriceRatio() {
        return $this->max_price_ratio;
    }
}
