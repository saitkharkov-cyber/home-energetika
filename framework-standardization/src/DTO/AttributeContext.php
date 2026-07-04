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
    private $attributeNameStructure = [];
    private $attributeValueStructure = [];
    private $synonymCandidates = [];
    private $valueReport = [];
    private $sqlPreview = [];
    private $report = [];
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

    public function setRawDataAttributes(array $attributes)
    {
        $this->rawData['attributes'] = $attributes;
    }

    public function setRawDataAttributeGroups(array $attributeGroups)
    {
        $this->rawData['attribute_groups'] = $attributeGroups;
    }

    public function setRawDataProductAttributes(array $productAttributes)
    {
        $this->rawData['product_attributes'] = $productAttributes;
    }

    public function getRawData()
    {
        return $this->rawData;
    }

    public function setAttributeNameStructure(array $attributeNameStructure)
    {
        $this->attributeNameStructure = $attributeNameStructure;
    }

    public function getAttributeNameStructure()
    {
        return $this->attributeNameStructure;
    }

    public function setAttributeValueRawValues(array $rawValues)
    {
        $this->attributeValueStructure['raw_values'] = $rawValues;
    }

    public function setAttributeValueStructure(array $attributeValueStructure)
    {
        $this->attributeValueStructure = $attributeValueStructure;
    }

    public function getAttributeValueStructure()
    {
        return $this->attributeValueStructure;
    }

    public function setSynonymCandidates(array $synonymCandidates)
    {
        $this->synonymCandidates = $synonymCandidates;
    }

    public function getSynonymCandidates()
    {
        return $this->synonymCandidates;
    }

    public function setValueReport(array $valueReport)
    {
        $this->valueReport = $valueReport;
    }

    public function getValueReport()
    {
        return $this->valueReport;
    }

    public function setSqlPreview(array $sqlPreview)
    {
        $this->sqlPreview = $sqlPreview;
    }

    public function getSqlPreview()
    {
        return $this->sqlPreview;
    }

    public function setReport(array $report)
    {
        $this->report = $report;
    }

    public function getReport()
    {
        return $this->report;
    }

    public function setFrameworkResult(FrameworkResult $frameworkResult)
    {
        $this->frameworkResult = $frameworkResult;
    }

    public function getFrameworkResult()
    {
        return $this->frameworkResult;
    }
}
