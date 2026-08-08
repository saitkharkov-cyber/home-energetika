<?php

class PumpSelectorRanking {
    const GATE_PASS = 'PASS';
    const GATE_BORDERLINE = 'BORDERLINE';
    const GATE_FAIL = 'FAIL';

    const BRAND_TIER_UNKNOWN = 0;
    const BRAND_TIER_STANDARD = 1;
    const BRAND_TIER_UPPER = 2;
    const BRAND_TIER_PREMIUM = 3;

    const UPGRADE_NONE = 'NONE';
    const UPGRADE_WEAK = 'WEAK';
    const UPGRADE_MEDIUM = 'MEDIUM';
    const UPGRADE_STRONG = 'STRONG';

    const PREMIUM_MAX_PRICE_DELTA_WEAK = 0.15;
    const PREMIUM_MAX_PRICE_DELTA_MEDIUM = 0.35;
    const PREMIUM_MAX_PRICE_DELTA_STRONG = 0.60;

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

        $metrics = $this->getPremiumComparisonMetrics($optimal, $premiumCandidate);

        return $metrics['reserve_degradation'] <= 1
            && $metrics['fit_degradation'] <= 1
            && $metrics['degradation_total'] <= 1;
    }

    /**
     * Filter an already-built Premium epsilon-Pareto front by the frozen
     * engineering degradation rule relative to Optimal.
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
     * Select Premium from an already-built Premium epsilon-Pareto front.
     * Candidates must belong to UPPER/PREMIUM brand tiers, pass the engineering
     * degradation rule relative to Optimal, have a justified upgrade strength,
     * and fit the frozen v1 price-delta gate for that strength.
     *
     * Final order:
     * upgrade strength DESC -> engineering improvement DESC ->
     * engineering degradation ASC -> price_delta ASC -> brand_tier DESC ->
     * product_id ASC.
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
            $upgradeStrength = $this->getUpgradeStrength($optimal, $candidate, $metrics);

            if ($upgradeStrength === self::UPGRADE_NONE) {
                continue;
            }

            $priceDelta = $this->getPriceDelta($optimal, $candidate);

            if (!$this->isPremiumPriceEligible($upgradeStrength, $priceDelta)) {
                continue;
            }

            $candidate['upgrade_strength'] = $upgradeStrength;
            $candidate['improvement_total'] = $metrics['improvement_total'];
            $candidate['degradation_total'] = $metrics['degradation_total'];
            $candidate['reserve_improvement'] = $metrics['reserve_improvement'];
            $candidate['fit_improvement'] = $metrics['fit_improvement'];
            $candidate['reserve_degradation'] = $metrics['reserve_degradation'];
            $candidate['fit_degradation'] = $metrics['fit_degradation'];
            $candidate['price_delta'] = $priceDelta;

            $eligible[] = $candidate;
        }

        if (empty($eligible)) {
            return null;
        }

        usort($eligible, array($this, 'comparePremiumCandidates'));

        return $eligible[0];
    }

    /**
     * Return STRONG / MEDIUM / WEAK / NONE for a Premium candidate relative
     * to Optimal according to RANKING_V2_SPEC v1.
     *
     * @param array $optimal
     * @param array $premiumCandidate
     * @param array|null $metrics
     * @return string
     */
    public function getUpgradeStrength(array $optimal, array $premiumCandidate, $metrics = null) {
        $this->assertPremiumReferenceCandidate($optimal);
        $this->assertPremiumCandidate($premiumCandidate);

        if ($metrics === null) {
            $metrics = $this->getPremiumComparisonMetrics($optimal, $premiumCandidate);
        }

        $improvementTotal = (int)$metrics['improvement_total'];
        $degradationTotal = (int)$metrics['degradation_total'];
        $brandUpgrade = (int)$premiumCandidate['brand_tier'] > (int)$optimal['brand_tier'];

        if ($improvementTotal >= 1 && $degradationTotal === 0) {
            return self::UPGRADE_STRONG;
        }

        if ($improvementTotal >= 1 && $degradationTotal === 1) {
            return self::UPGRADE_MEDIUM;
        }

        if ($improvementTotal === 0 && $degradationTotal === 0 && $brandUpgrade) {
            return self::UPGRADE_MEDIUM;
        }

        if ($improvementTotal === 0 && $degradationTotal === 1 && $brandUpgrade) {
            return self::UPGRADE_WEAK;
        }

        return self::UPGRADE_NONE;
    }

    /**
     * Return relative Premium price difference vs Optimal.
     * Negative values mean Premium is cheaper than Optimal.
     *
     * @param array $optimal
     * @param array $premiumCandidate
     * @return float
     */
    public function getPriceDelta(array $optimal, array $premiumCandidate) {
        $this->assertBaseCandidate($optimal);
        $this->assertBaseCandidate($premiumCandidate);

        return ((float)$premiumCandidate['price'] - (float)$optimal['price']) / (float)$optimal['price'];
    }

    /**
     * Check frozen v1 Premium price gates.
     *
     * @param string $upgradeStrength
     * @param float $priceDelta
     * @return bool
     */
    public function isPremiumPriceEligible($upgradeStrength, $priceDelta) {
        $priceDelta = (float)$priceDelta;

        if ($upgradeStrength === self::UPGRADE_STRONG) {
            return $priceDelta <= self::PREMIUM_MAX_PRICE_DELTA_STRONG;
        }

        if ($upgradeStrength === self::UPGRADE_MEDIUM) {
            return $priceDelta <= self::PREMIUM_MAX_PRICE_DELTA_MEDIUM;
        }

        if ($upgradeStrength === self::UPGRADE_WEAK) {
            return $priceDelta <= self::PREMIUM_MAX_PRICE_DELTA_WEAK;
        }

        return false;
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
        $aStrength = $this->getUpgradeStrengthRank($a['upgrade_strength']);
        $bStrength = $this->getUpgradeStrengthRank($b['upgrade_strength']);

        if ($aStrength !== $bStrength) {
            return ($aStrength > $bStrength) ? -1 : 1;
        }

        if ((int)$a['improvement_total'] !== (int)$b['improvement_total']) {
            return ((int)$a['improvement_total'] > (int)$b['improvement_total']) ? -1 : 1;
        }

        if ((int)$a['degradation_total'] !== (int)$b['degradation_total']) {
            return ((int)$a['degradation_total'] < (int)$b['degradation_total']) ? -1 : 1;
        }

        if ((float)$a['price_delta'] !== (float)$b['price_delta']) {
            return ((float)$a['price_delta'] < (float)$b['price_delta']) ? -1 : 1;
        }

        if ((int)$a['brand_tier'] !== (int)$b['brand_tier']) {
            return ((int)$a['brand_tier'] > (int)$b['brand_tier']) ? -1 : 1;
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

    private function getUpgradeStrengthRank($upgradeStrength) {
        if ($upgradeStrength === self::UPGRADE_STRONG) {
            return 3;
        }

        if ($upgradeStrength === self::UPGRADE_MEDIUM) {
            return 2;
        }

        if ($upgradeStrength === self::UPGRADE_WEAK) {
            return 1;
        }

        return 0;
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
