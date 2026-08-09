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
}
