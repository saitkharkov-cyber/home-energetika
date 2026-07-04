<?php

namespace FrameworkStandardization\Contract;

interface AttributeNameAnalyzerInterface
{
    public function analyze(array $canonical, array $rawData, array $attributeNameStructure);
}
