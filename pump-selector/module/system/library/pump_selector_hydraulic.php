<?php

class PumpSelectorHydraulic
{
    const GATE_PASS = 'PASS';
    const GATE_BORDERLINE = 'BORDERLINE';
    const GATE_FAIL = 'FAIL';

    const GRADE_POOR = 0;
    const GRADE_ACCEPTABLE = 1;
    const GRADE_GOOD = 2;
    const GRADE_IDEAL = 3;

    const HYDRAULIC_MODEL = 'endpoint_parabolic_estimate';
    const CONFIDENCE = 'approximate';

    const TARGET_Q_MIN = 0.35;
    const TARGET_Q_MAX = 0.65;
    const TARGET_H_MIN = 0.45;
    const TARGET_H_MAX = 0.75;

    const GATE_FAIL_RESERVE = -0.10;

    const RESERVE_COMFORT_MIN = 0.10;
    const RESERVE_COMFORT_MAX = 0.25;
    const LOW_RESERVE_MULTIPLIER = 2.0;

    public function decorateCandidate(array $rawProduct, array $requirements)
    {
        $this->assertRequiredKey($rawProduct, 'max_flow_l_min', 'rawProduct');
        $this->assertRequiredKey($rawProduct, 'max_head_m', 'rawProduct');
        $this->assertRequiredKey($requirements, 'required_flow_l_min', 'requirements');
        $this->assertRequiredKey($requirements, 'required_head_m', 'requirements');

        $qMax = (float)$rawProduct['max_flow_l_min'];
        $hMax = (float)$rawProduct['max_head_m'];
        $qReq = (float)$requirements['required_flow_l_min'];
        $hReq = (float)$requirements['required_head_m'];

        if ($qMax <= 0) {
            throw new InvalidArgumentException('max_flow_l_min must be greater than 0.');
        }

        if ($hMax <= 0) {
            throw new InvalidArgumentException('max_head_m must be greater than 0.');
        }

        if ($qReq <= 0) {
            throw new InvalidArgumentException('required_flow_l_min must be greater than 0.');
        }

        if ($hReq <= 0) {
            throw new InvalidArgumentException('required_head_m must be greater than 0.');
        }

        $qRel = $qReq / $qMax;
        $hRel = $hReq / $hMax;
        $hEst = $hMax * (1 - ($qRel * $qRel));
        $reserveRel = ($hEst - $hReq) / $hReq;
        $gateStatus = $this->getHydraulicGate($qRel, $reserveRel);
        $technicalFit = $this->calculateTechnicalFit($qRel, $hRel);
        $reservePenalty = $this->calculateReservePenalty($reserveRel);
        $reserveGrade = $this->getReserveGrade($reserveRel, $gateStatus);
        $fitGrade = $this->getFitGrade($technicalFit);

        return array_merge($rawProduct, array(
            'q_rel' => (float)$qRel,
            'h_rel' => (float)$hRel,
            'h_est' => (float)$hEst,
            'reserve_rel' => (float)$reserveRel,
            'hydraulic_gate' => $gateStatus,
            'technical_fit' => (float)$technicalFit,
            'reserve_penalty' => (float)$reservePenalty,
            'reserve_grade' => $reserveGrade,
            'fit_grade' => (int)$fitGrade,
            'hydraulic_model' => self::HYDRAULIC_MODEL,
            'confidence' => self::CONFIDENCE
        ));
    }

    public function getHydraulicGate($qRel, $reserveRel)
    {
        $qRel = (float)$qRel;
        $reserveRel = (float)$reserveRel;

        if ($qRel > 1.0) {
            return self::GATE_FAIL;
        }

        if ($reserveRel < self::GATE_FAIL_RESERVE) {
            return self::GATE_FAIL;
        }

        if ($reserveRel < 0) {
            return self::GATE_BORDERLINE;
        }

        return self::GATE_PASS;
    }

    public function calculateTechnicalFit($qRel, $hRel)
    {
        $qRel = (float)$qRel;
        $hRel = (float)$hRel;

        $deltaQ = 0.0;
        if ($qRel < self::TARGET_Q_MIN) {
            $deltaQ = self::TARGET_Q_MIN - $qRel;
        } elseif ($qRel > self::TARGET_Q_MAX) {
            $deltaQ = $qRel - self::TARGET_Q_MAX;
        }

        $deltaH = 0.0;
        if ($hRel < self::TARGET_H_MIN) {
            $deltaH = self::TARGET_H_MIN - $hRel;
        } elseif ($hRel > self::TARGET_H_MAX) {
            $deltaH = $hRel - self::TARGET_H_MAX;
        }

        return sqrt(($deltaQ * $deltaQ) + ($deltaH * $deltaH));
    }

    public function calculateReservePenalty($reserveRel)
    {
        $reserveRel = (float)$reserveRel;

        if ($reserveRel < self::RESERVE_COMFORT_MIN) {
            return self::LOW_RESERVE_MULTIPLIER * (self::RESERVE_COMFORT_MIN - $reserveRel);
        }

        if ($reserveRel <= self::RESERVE_COMFORT_MAX) {
            return 0.0;
        }

        return $reserveRel - self::RESERVE_COMFORT_MAX;
    }

    public function getReserveGrade($reserveRel, $gateStatus)
    {
        $reserveRel = (float)$reserveRel;

        if ($gateStatus !== self::GATE_PASS) {
            return null;
        }

        if ($reserveRel >= 0.10 && $reserveRel <= 0.20) {
            return self::GRADE_IDEAL;
        }

        if (($reserveRel >= 0.05 && $reserveRel < 0.10) || ($reserveRel > 0.20 && $reserveRel <= 0.35)) {
            return self::GRADE_GOOD;
        }

        if (($reserveRel >= 0.0 && $reserveRel < 0.05) || ($reserveRel > 0.35 && $reserveRel <= 0.50)) {
            return self::GRADE_ACCEPTABLE;
        }

        return self::GRADE_POOR;
    }

    public function getFitGrade($technicalFit)
    {
        $technicalFit = (float)$technicalFit;

        if ($technicalFit == 0.0) {
            return self::GRADE_IDEAL;
        }

        if ($technicalFit <= 0.10) {
            return self::GRADE_GOOD;
        }

        if ($technicalFit <= 0.22) {
            return self::GRADE_ACCEPTABLE;
        }

        return self::GRADE_POOR;
    }

    private function assertRequiredKey(array $data, $key, $sourceName)
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException($sourceName . ' is missing required key: ' . $key);
        }
    }
}
