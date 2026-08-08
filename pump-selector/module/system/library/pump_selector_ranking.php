<?php

class PumpSelectorRanking {
    const GATE_PASS = 'PASS';
    const GATE_BORDERLINE = 'BORDERLINE';
    const GATE_FAIL = 'FAIL';

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
     * Ordering from RANKING_V2_SPEC:
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

        $reserveDegradation = max(0, (int)$optimal['reserve_grade'] - (int)$premiumCandidate['reserve_grade']);
        $fitDegradation = max(0, (int)$optimal['fit_grade'] - (int)$premiumCandidate['fit_grade']);

        return $reserveDegradation <= 1
            && $fitDegradation <= 1
            && ($reserveDegradation + $fitDegradation) <= 1;
    }

    /**
     * Filter an already-built Premium epsilon-Pareto front by the frozen
     * engineering degradation rule relative to Optimal.
     * Brand-tier membership and Premium price/upgrade-strength policy must be
     * applied outside this method until those rules are frozen.
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
}
