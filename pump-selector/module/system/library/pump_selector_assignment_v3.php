<?php

/**
 * Isolated helper for the future Ranking V3 sequential assignment pipeline.
 *
 * This class is intentionally not wired into the current Ranking V2 runtime.
 */
class PumpSelectorAssignmentV3 {
    const GATE_PASS = 'PASS';
    const GATE_BORDERLINE = 'BORDERLINE';
    const GATE_FAIL = 'FAIL';

    /**
     * Select Best Price from already hydraulic-decorated candidates.
     *
     * PASS candidates always take precedence over BORDERLINE candidates.
     * FAIL candidates never participate. Within the selected gate, the
     * deterministic order is price ASC, then product_id ASC.
     *
     * @param array $candidates
     * @return array|null
     */
    public function selectBestPrice(array $candidates) {
        $passCandidates = array();
        $borderlineCandidates = array();

        foreach ($candidates as $candidate) {
            $this->assertCandidate($candidate);

            if ($candidate['hydraulic_gate'] === self::GATE_PASS) {
                $passCandidates[] = $candidate;
            } elseif ($candidate['hydraulic_gate'] === self::GATE_BORDERLINE) {
                $borderlineCandidates[] = $candidate;
            }
        }

        if (!empty($passCandidates)) {
            return $this->selectCheapest($passCandidates);
        }

        if (!empty($borderlineCandidates)) {
            return $this->selectCheapest($borderlineCandidates);
        }

        return null;
    }

    /**
     * Select Optimal from PASS candidates outside the Exclusion Pool.
     *
     * Eligible candidates must have reserve_grade >= 1. The deterministic
     * order is fit_grade DESC, then price ASC, then product_id ASC.
     *
     * @param array $candidates
     * @param array $excludedProductIds
     * @return array|null
     */
    public function selectOptimal(array $candidates, array $excludedProductIds) {
        $eligibleCandidates = array();

        foreach ($candidates as $candidate) {
            $this->assertCandidate($candidate);

            if ($candidate['hydraulic_gate'] !== self::GATE_PASS) {
                continue;
            }

            if (in_array((int)$candidate['product_id'], $excludedProductIds, true)) {
                continue;
            }

            $this->assertOptimalCandidate($candidate);

            if ((int)$candidate['reserve_grade'] < 1) {
                continue;
            }

            $eligibleCandidates[] = $candidate;
        }

        if (empty($eligibleCandidates)) {
            return null;
        }

        usort($eligibleCandidates, array($this, 'compareOptimalCandidates'));

        return $eligibleCandidates[0];
    }

    /**
     * Select Premium from tier-3 PASS candidates outside the Exclusion Pool.
     *
     * Eligible candidates require fit_grade >= 2 and reserve_grade >= 2.
     * After those gates, the deterministic order is price ASC, then
     * product_id ASC.
     *
     * @param array $candidates
     * @param array $excludedProductIds
     * @return array|null
     */
    public function selectPremium(array $candidates, array $excludedProductIds) {
        $eligibleCandidates = array();

        foreach ($candidates as $candidate) {
            $this->assertCandidate($candidate);

            if ($candidate['hydraulic_gate'] !== self::GATE_PASS) {
                continue;
            }

            if (in_array((int)$candidate['product_id'], $excludedProductIds, true)) {
                continue;
            }

            $this->assertPremiumCandidate($candidate);

            if ((int)$candidate['brand_tier'] !== 3) {
                continue;
            }

            if ((int)$candidate['fit_grade'] < 2 || (int)$candidate['reserve_grade'] < 2) {
                continue;
            }

            $eligibleCandidates[] = $candidate;
        }

        if (empty($eligibleCandidates)) {
            return null;
        }

        return $this->selectCheapest($eligibleCandidates);
    }

    /**
     * Add an assigned candidate product_id to a simple unique Exclusion Pool.
     * A null assignment leaves the pool unchanged.
     *
     * @param array $excludedProductIds
     * @param array|null $assignedCandidate
     * @return array
     */
    public function addToExclusionPool(array $excludedProductIds, $assignedCandidate) {
        $pool = array();

        foreach ($excludedProductIds as $productId) {
            $productId = (int)$productId;

            if (!in_array($productId, $pool, true)) {
                $pool[] = $productId;
            }
        }

        if ($assignedCandidate === null) {
            return $pool;
        }

        if (!is_array($assignedCandidate)) {
            throw new InvalidArgumentException('Assigned candidate must be an array or null.');
        }

        $this->assertCandidate($assignedCandidate);
        $productId = (int)$assignedCandidate['product_id'];

        if (!in_array($productId, $pool, true)) {
            $pool[] = $productId;
        }

        return $pool;
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

    public function compareOptimalCandidates($a, $b) {
        if ((int)$a['fit_grade'] !== (int)$b['fit_grade']) {
            return ((int)$a['fit_grade'] > (int)$b['fit_grade']) ? -1 : 1;
        }

        return $this->comparePriceCandidates($a, $b);
    }

    private function assertCandidate(array $candidate) {
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

    private function assertOptimalCandidate(array $candidate) {
        if (!isset($candidate['reserve_grade']) || !is_numeric($candidate['reserve_grade'])) {
            throw new InvalidArgumentException('Candidate reserve_grade is required for Optimal selection.');
        }

        if (!isset($candidate['fit_grade']) || !is_numeric($candidate['fit_grade'])) {
            throw new InvalidArgumentException('Candidate fit_grade is required for Optimal selection.');
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

    private function assertPremiumCandidate(array $candidate) {
        $this->assertOptimalCandidate($candidate);

        if (!isset($candidate['brand_tier']) || !is_numeric($candidate['brand_tier'])) {
            throw new InvalidArgumentException('Candidate brand_tier is required for Premium selection.');
        }

        $brandTier = (int)$candidate['brand_tier'];

        if ($brandTier < 0 || $brandTier > 3) {
            throw new InvalidArgumentException('Candidate brand_tier must be between 0 and 3.');
        }
    }
}
