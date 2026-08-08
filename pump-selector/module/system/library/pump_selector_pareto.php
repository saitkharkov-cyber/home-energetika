<?php

/**
 * Pure epsilon-Pareto helper for pump selector ranking v2.
 *
 * PHP 5.6 compatible. No OpenCart bootstrap or database dependencies.
 */
class PumpSelectorPareto {
    private $epsilon_price;

    public function __construct($epsilon_price = 0.02) {
        $epsilon_price = (float)$epsilon_price;

        if ($epsilon_price < 0) {
            throw new InvalidArgumentException('epsilon_price must be zero or greater.');
        }

        $this->epsilon_price = $epsilon_price;
    }

    /**
     * Returns true when candidate A epsilon-dominates candidate B.
     *
     * Required candidate keys:
     * - technical_fit
     * - reserve_penalty
     * - price
     */
    public function epsilonDominates(array $candidate_a, array $candidate_b) {
        $this->validateCandidate($candidate_a);
        $this->validateCandidate($candidate_b);

        $a_fit = (float)$candidate_a['technical_fit'];
        $b_fit = (float)$candidate_b['technical_fit'];
        $a_reserve = (float)$candidate_a['reserve_penalty'];
        $b_reserve = (float)$candidate_b['reserve_penalty'];
        $a_price = (float)$candidate_a['price'];
        $b_price = (float)$candidate_b['price'];

        // Exact engineering equality: the strictly cheaper candidate dominates.
        if ($a_fit == $b_fit && $a_reserve == $b_reserve) {
            return $a_price < $b_price;
        }

        $engineering_not_worse = ($a_fit <= $b_fit && $a_reserve <= $b_reserve);
        $engineering_strictly_better = ($a_fit < $b_fit || $a_reserve < $b_reserve);
        $price_within_epsilon = ($a_price <= $b_price * (1 + $this->epsilon_price));

        return $engineering_not_worse && $engineering_strictly_better && $price_within_epsilon;
    }

    /**
     * Builds a non-dominated epsilon-Pareto front.
     * Input order is preserved for deterministic downstream ranking.
     */
    public function buildFront(array $candidates) {
        $front = array();
        $count = count($candidates);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($candidates[$i])) {
                throw new InvalidArgumentException('Each candidate must be an array.');
            }

            $this->validateCandidate($candidates[$i]);
            $is_dominated = false;

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }

                if (!is_array($candidates[$j])) {
                    throw new InvalidArgumentException('Each candidate must be an array.');
                }

                if ($this->epsilonDominates($candidates[$j], $candidates[$i])) {
                    $is_dominated = true;
                    break;
                }
            }

            if (!$is_dominated) {
                $front[] = $candidates[$i];
            }
        }

        return $front;
    }

    public function getEpsilonPrice() {
        return $this->epsilon_price;
    }

    private function validateCandidate(array $candidate) {
        $required_keys = array('technical_fit', 'reserve_penalty', 'price');

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $candidate)) {
                throw new InvalidArgumentException('Missing candidate key: ' . $key);
            }

            if (!is_numeric($candidate[$key])) {
                throw new InvalidArgumentException('Candidate key must be numeric: ' . $key);
            }
        }

        if ((float)$candidate['technical_fit'] < 0) {
            throw new InvalidArgumentException('technical_fit must be zero or greater.');
        }

        if ((float)$candidate['reserve_penalty'] < 0) {
            throw new InvalidArgumentException('reserve_penalty must be zero or greater.');
        }

        if ((float)$candidate['price'] <= 0) {
            throw new InvalidArgumentException('price must be greater than zero.');
        }
    }
}
