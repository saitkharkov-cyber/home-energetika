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
     * Returns true when candidate A epsilon-dominates candidate B using the
     * original continuous Pareto policy.
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
     * Grade-aware epsilon dominance for GENERAL Pareto / Optimal only.
     *
     * The ordinary continuous Pareto rule is preserved when A is not more
     * expensive than B. If A is more expensive, the epsilon price concession
     * is allowed only when A is not worse in either discrete engineering grade
     * and is strictly better in at least one grade.
     *
     * Required additional keys:
     * - reserve_grade
     * - fit_grade
     */
    public function gradeAwareEpsilonDominates(array $candidate_a, array $candidate_b) {
        $this->validateGradeAwareCandidate($candidate_a);
        $this->validateGradeAwareCandidate($candidate_b);

        $a_fit = (float)$candidate_a['technical_fit'];
        $b_fit = (float)$candidate_b['technical_fit'];
        $a_reserve = (float)$candidate_a['reserve_penalty'];
        $b_reserve = (float)$candidate_b['reserve_penalty'];
        $a_price = (float)$candidate_a['price'];
        $b_price = (float)$candidate_b['price'];

        $engineering_not_worse = ($a_fit <= $b_fit && $a_reserve <= $b_reserve);
        $engineering_strictly_better = ($a_fit < $b_fit || $a_reserve < $b_reserve);

        if (!$engineering_not_worse) {
            return false;
        }

        if (!$engineering_strictly_better) {
            return $a_price < $b_price;
        }

        if ($a_price <= $b_price) {
            return true;
        }

        if ($a_price > $b_price * (1 + $this->epsilon_price)) {
            return false;
        }

        $a_reserve_grade = (int)$candidate_a['reserve_grade'];
        $b_reserve_grade = (int)$candidate_b['reserve_grade'];
        $a_fit_grade = (int)$candidate_a['fit_grade'];
        $b_fit_grade = (int)$candidate_b['fit_grade'];

        $grades_not_worse = (
            $a_reserve_grade >= $b_reserve_grade
            && $a_fit_grade >= $b_fit_grade
        );
        $grade_strictly_better = (
            $a_reserve_grade > $b_reserve_grade
            || $a_fit_grade > $b_fit_grade
        );

        return $grades_not_worse && $grade_strictly_better;
    }

    /**
     * Builds the original continuous non-dominated epsilon-Pareto front.
     * Used by tier-scoped Premium Pareto.
     * Input order is preserved for deterministic downstream ranking.
     */
    public function buildFront(array $candidates) {
        return $this->buildFrontWithComparator($candidates, 'epsilonDominates', false);
    }

    /**
     * Builds the grade-aware GENERAL epsilon-Pareto front used by Optimal.
     * Input order is preserved for deterministic downstream ranking.
     */
    public function buildGradeAwareFront(array $candidates) {
        return $this->buildFrontWithComparator($candidates, 'gradeAwareEpsilonDominates', true);
    }

    public function getEpsilonPrice() {
        return $this->epsilon_price;
    }

    private function buildFrontWithComparator(array $candidates, $comparator, $grade_aware) {
        $front = array();
        $count = count($candidates);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($candidates[$i])) {
                throw new InvalidArgumentException('Each candidate must be an array.');
            }

            if ($grade_aware) {
                $this->validateGradeAwareCandidate($candidates[$i]);
            } else {
                $this->validateCandidate($candidates[$i]);
            }

            $is_dominated = false;

            for ($j = 0; $j < $count; $j++) {
                if ($i === $j) {
                    continue;
                }

                if (!is_array($candidates[$j])) {
                    throw new InvalidArgumentException('Each candidate must be an array.');
                }

                if ($this->$comparator($candidates[$j], $candidates[$i])) {
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

    private function validateGradeAwareCandidate(array $candidate) {
        $this->validateCandidate($candidate);

        $required_grade_keys = array('reserve_grade', 'fit_grade');

        foreach ($required_grade_keys as $key) {
            if (!array_key_exists($key, $candidate)) {
                throw new InvalidArgumentException('Missing candidate key: ' . $key);
            }

            if (!is_numeric($candidate[$key])) {
                throw new InvalidArgumentException('Candidate key must be numeric: ' . $key);
            }

            $grade = (int)$candidate[$key];

            if ($grade < 0 || $grade > 3 || (float)$candidate[$key] != $grade) {
                throw new InvalidArgumentException('Candidate grade must be an integer from 0 to 3: ' . $key);
            }
        }
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
