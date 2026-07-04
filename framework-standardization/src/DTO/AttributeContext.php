<?php

namespace FrameworkStandardization\DTO;

final class AttributeContext
{
    public $stageResults = [];
    public $completedStages = [];
    public $errors = [];
    public $warnings = [];
    public $frameworkResult = null;
    private $canonical = [];
    private $scope = [];
    private $rawData = [];
    private $job;

    public function __construct(AttributeJob $job)
    {
        $this->job = $job;
    }

    public function addStageResult($stageName, StageResult $stageResult)
    {
        $this->stageResults[$stageName] = $stageResult->toArray();
        $this->completedStages[] = $stageName;
    }

    public function addError($error)
    {
        $this->errors[] = $error;
    }

    public function addWarning($warning)
    {
        $this->warnings[] = $warning;
    }

    public function hasErrors()
    {
        return $this->errors !== [];
    }

    public function getStageResults()
    {
        return $this->stageResults;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setCanonical(array $canonical)
    {
        $this->canonical = $canonical;
    }

    public function getCanonical()
    {
        return $this->canonical;
    }

    public function setScope(array $scope)
    {
        $this->scope = $scope;
    }

    public function getScope()
    {
        return $this->scope;
    }

    public function setRawDataProducts(array $products)
    {
        $this->rawData['products'] = $products;
    }

    public function getRawData()
    {
        return $this->rawData;
    }
}
