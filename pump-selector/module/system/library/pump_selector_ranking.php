<?php

class PumpSelectorRanking {
    const GATE_PASS = 'PASS';
    const GATE_BORDERLINE = 'BORDERLINE';
    const GATE_FAIL = 'FAIL';

    const BRAND_TIER_UNKNOWN = 0;
    const BRAND_TIER_STANDARD = 1;
    const BRAND_TIER_UPPER = 2;
    const BRAND_TIER_PREMIUM = 3;

    /**
     * Select the cheapest physically admissible candidate.
     * PASS candidates always take precedence over BORDERLINE candidates.
     * FAIL candidates never participate.
     *
     * @param array $candidates
     * @return array|null
     */
    public function selectBestPrice(array $candidates) {
        $pass = array();
        $borderline = array();

        foreach ($candidates as $candidate) {
            $this->assertBaseCandidate($candidate);

            if ($candidate['hydraulic_gate'] === self::GATE_PASS) {
                $pass[] = $candidate;
            } elseif ($candidate['hydraulic_gate'] === self::GATE_BORDERLINE) {
                $borderline[] = $candidate;
            }
        }

        if (!empty($pass)) {
            return $this->selectCheapest($pass);
        }

        if (!empty($borderline)) {
            return $this->selectCheapest($borderline);
        }

        return null;
    }

    /**
     * Select Optimal from an already-built GENERAL epsilon-Pareto front.
     * This method intentionally handles PASS candidates only.
     * BORDERLINE fallback ranking is a separate policy and is not frozen yet.
     *
     * Ordering:
     * reserve_grade DESC -> fit_grade DESC -> price ASC ->
     * brand_factor DESC (optional low-order tie-break) -> product_id ASC.
     *
     * @param array $paretoCandidates
     * @return array|null
     */
    public function selectOptimalFromPassPareto(array $paretoCandidates) {
        $pass = array();

        foreach ($paretoCandidates as $candidate) {
            $this->assertRankedCandidate($candidate);

            if ($candidate['hydraulic_gate'] === self::GATE_PASS) {
                $pass[] = $candidate;
            }
        }

        if (empty($pass)) {
            return null;
        }

        usort($pass, array($this, 'compareOptimalCandidates'));

        return $pass[0];
    }

    /**
     * Check the frozen engineering degradation rule for Premium vs Optimal.
     * Both candidates must be PASS and have reserve/fit grades.
     * Improvements are not penalized.
     *
     * @param array $optimal
     * @param array $premiumCandidate
     * @return bool
     */
    public function isPremiumEngineeringEligible(array $optimal, array $premiumCandidate) {
        $this->assertRankedCandidate($optimal);
        $this->assertRankedCandidate($premiumCandidate);

        if ($optimal['hydraulic_gate'] !== self::GATE_PASS) {
            return false;
        }

        if ($premiumCandidate['hydraulic_gate'] !== self::GATE_PASS) {
            return false;
        }

        $metrics = $this->getPremiumComparisonMetrics($optimal, $premiumCandidate);

        return $metrics['reserve_degradation'] <= 1
            && $metrics['fit_degradation'] <= 1
            && $metrics['degradation_total'] <= 1;
    }

    /**
     * Filter Premium candidates by the engineering degradation rule.
     *
     * @param array $premiumParetoCandidates
     * @param array $optimal
     * @return array
     */
    public function filterPremiumEngineeringEligible(array $premiumParetoCandidates, array $optimal) {
        $eligible = array();

        foreach ($premiumParetoCandidates as $candidate) {
            if ($this->isPremiumEngineeringEligible($optimal, $candidate)) {
                $eligible[] = $candidate;
            }
        }

        return $eligible;
    }

    /**
     * Select Premium from the union of tier-scoped Premium epsilon-Pareto fronts.
     *
     * Admission rules:
     * - PASS only;
     * - brand_tier >= UPPER;
     * - engineering degradation vs Optimal is at most one grade on one axis;
     * - candidate has a Premium reason: higher brand tier than Optimal OR at
     *   least one engineering-grade improvement.
     *
     * Price is not an admission gate. It is only a low-order tie-break.
     *
     * Final order:
     * brand_tier DESC -> improvement_total DESC -> degradation_total ASC ->
     * reserve_grade DESC -> fit_grade DESC -> price ASC -> product_id ASC.
     *
     * @param array $premiumParetoCandidates
     * @param array $optimal
     * @return array|null
     */
    public function selectPremium(array $premiumParetoCandidates, array $optimal) {
        $this->assertPremiumReferenceCandidate($optimal);

        if ($optimal['hydraulic_gate'] !== self::GATE_PASS) {
            return null;
        }

        $eligible = array();

        foreach ($premiumParetoCandidates as $candidate) {
            $this->assertPremiumCandidate($candidate);

            if ($candidate['hydraulic_gate'] !== self::GATE_PASS) {
                continue;
            }

            if ((int)$candidate['brand_tier'] < self::BRAND_TIER_UPPER) {
                continue;
            }

            if (!$this->isPremiumEngineeringEligible($optimal, $candidate)) {
                continue;
            }

            $metrics = $this->getPremiumComparisonMetrics($optimal, $candidate);
            $hasBrandUpgrade = (int)$candidate['brand_tier'] > (int)$optimal['brand_tier'];
            $hasEngineeringImprovement = (int)$metrics['improvement_total'] > 0;

            if (!$hasBrandUpgrade && !$hasEngineeringImprovement) {
                continue;
            }

            $candidate['improvement_total'] = $metrics['improvement_total'];
            $candidate['degradation_total'] = $metrics['degradation_total'];
            $candidate['reserve_improvement'] = $metrics['reserve_improvement'];
            $candidate['fit_improvement'] = $metrics['fit_improvement'];
            $candidate['reserve_degradation'] = $metrics['reserve_degradation'];
            $candidate['fit_degradation'] = $metrics['fit_degradation'];

            $eligible[] = $candidate;
        }

        if (empty($eligible)) {
            return null;
        }

        usort($eligible, array($this, 'comparePremiumCandidates'));

        return $eligible[0];
    }

    public function compareOptimalCandidates($a, $b) {
        if ((int)$a['reserve_grade'] !== (int)$b['reserve_grade']) {
            return ((int)$a['reserve_grade'] > (int)$b['reserve_grade']) ? -1 : 1;
        }

        if ((int)$a['fit_grade'] !== (int)$b['fit_grade']) {
            return ((int)$a['fit_grade'] > (int)$b['fit_grade']) ? -1 : 1;
        }

        if ((float)$a['price'] !== (float)$b['price']) {
            return ((float)$a['price'] < (float)$b['price']) ? -1 : 1;
        }

        $aBrandFactor = isset($a['brand_factor']) ? (float)$a['brand_factor'] : 0.0;
        $bBrandFactor = isset($b['brand_factor']) ? (float)$b['brand_factor'] : 0.0;

        if ($aBrandFactor !== $bBrandFactor) {
            return ($aBrandFactor > $bBrandFactor) ? -1 : 1;
        }

        if ((int)$a['product_id'] === (int)$b['product_id']) {
            return 0;
        }

        return ((int)$a['product_id'] < (int)$b['product_id']) ? -1 : 1;
    }

    public function comparePremiumCandidates($a, $b) {
        if ((int)$a['brand_tier'] !== (int)$b['brand_tier']) {
            return ((int)$a['brand_tier'] > (int)$b['brand_tier']) ? -1 : 1;
        }

        if ((int)$a['improvement_total'] !== (int)$b['improvement_total']) {
            return ((int)$a['improvement_total'] > (int)$b['improvement_total']) ? -1 : 1;
        }

        if ((int)$a['degradation_total'] !== (int)$b['degradation_total']) {
            return ((int)$a['degradation_total'] < (int)$b['degradation_total']) ? -1 : 1;
        }

        if ((int)$a['reserve_grade'] !== (int)$b['reserve_grade']) {
            return ((int)$a['reserve_grade'] > (int)$b['reserve_grade']) ? -1 : 1;
        }

        if ((int)$a['fit_grade'] !== (int)$b['fit_grade']) {
            return ((int)$a['fit_grade'] > (int)$b['fit_grade']) ? -1 : 1;
        }

        if ((float)$a['price'] !== (float)$b['price']) {
            return ((float)$a['price'] < (float)$b['price']) ? -1 : 1;
        }

        if ((int)$a['product_id'] === (int)$b['product_id']) {
            return 0;
        }

        return ((int)$a['product_id'] < (int)$b['product_id']) ? -1 : 1;
    }

    private function getPremiumComparisonMetrics(array $optimal, array $premiumCandidate) {
        $reserveImprovement = max(0, (int)$premiumCandidate['reserve_grade'] - (int)$optimal['reserve_grade']);
        $fitImprovement = max(0, (int)$premiumCandidate['fit_grade'] - (int)$optimal['fit_grade']);
        $reserveDegradation = max(0, (int)$optimal['reserve_grade'] - (int)$premiumCandidate['reserve_grade']);
        $fitDegradation = max(0, (int)$optimal['fit_grade'] - (int)$premiumCandidate['fit_grade']);

        return array(
            'reserve_improvement' => $reserveImprovement,
            'fit_improvement' => $fitImprovement,
            'improvement_total' => $reserveImprovement + $fitImprovement,
            'reserve_degradation' => $reserveDegradation,
            'fit_degradation' => $fitDegradation,
            'degradation_total' => $reserveDegradation + $fitDegradation,
        );
    }

    private function selectCheapest(array $candidates) {
        usort($candidates, array($this, 'comparePriceCandidates'));
        return $candidates[0];
    }

    public function comparePriceCandidates($a, $b) {
        if ((float)$a['price'] !== (float)$b['price']) {
            return ((float)$a['price'] < (float)$b['price']) ? -1 : 1;
        }

        if ((int)$a['product_id'] === (int)$b['product_id']) {
            return 0;
        }

        return ((int)$a['product_id'] < (int)$b['product_id']) ? -1 : 1;
    }

    private function assertBaseCandidate(array $candidate) {
        if (!isset($candidate['product_id'])) {
            throw new InvalidArgumentException('Candidate product_id is required.');
        }

        if (!isset($candidate['price']) || !is_numeric($candidate['price']) || (float)$candidate['price'] <= 0) {
            throw new InvalidArgumentException('Candidate price must be numeric and greater than zero.');
        }

        if (!isset($candidate['hydraulic_gate'])) {
            throw new InvalidArgumentException('Candidate hydraulic_gate is required.');
        }

        if (!in_array($candidate['hydraulic_gate'], array(self::GATE_PASS, self::GATE_BORDERLINE, self::GATE_FAIL), true)) {
            throw new InvalidArgumentException('Candidate hydraulic_gate is invalid.');
        }
    }

    private function assertRankedCandidate(array $candidate) {
        $this->assertBaseCandidate($candidate);

        if ($candidate['hydraulic_gate'] === self::GATE_PASS) {
            if (!isset($candidate['reserve_grade']) || !is_numeric($candidate['reserve_grade'])) {
                throw new InvalidArgumentException('PASS candidate reserve_grade is required.');
            }

            if (!isset($candidate['fit_grade']) || !is_numeric($candidate['fit_grade'])) {
                throw new InvalidArgumentException('PASS candidate fit_grade is required.');
            }

            $reserveGrade = (int)$candidate['reserve_grade'];
            $fitGrade = (int)$candidate['fit_grade'];

            if ($reserveGrade < 0 || $reserveGrade > 3) {
                throw new InvalidArgumentException('Candidate reserve_grade must be between 0 and 3.');
            }

            if ($fitGrade < 0 || $fitGrade > 3) {
                throw new InvalidArgumentException('Candidate fit_grade must be between 0 and 3.');
            }
        }
    }

    private function assertPremiumReferenceCandidate(array $candidate) {
        $this->assertRankedCandidate($candidate);
        $this->assertBrandTier($candidate);
    }

    private function assertPremiumCandidate(array $candidate) {
        $this->assertRankedCandidate($candidate);
        $this->assertBrandTier($candidate);
    }

    private function assertBrandTier(array $candidate) {
        if (!isset($candidate['brand_tier']) || !is_numeric($candidate['brand_tier'])) {
            throw new InvalidArgumentException('Candidate brand_tier is required for Premium ranking.');
        }

        $brandTier = (int)$candidate['brand_tier'];

        if ($brandTier < self::BRAND_TIER_UNKNOWN || $brandTier > self::BRAND_TIER_PREMIUM) {
            throw new InvalidArgumentException('Candidate brand_tier must be between 0 and 3.');
        }
    }
}
