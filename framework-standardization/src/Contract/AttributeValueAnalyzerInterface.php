<?php

namespace FrameworkStandardization\Contract;

interface AttributeValueAnalyzerInterface
{
    public function analyze(array $canonical, array $rawValues, array $valueRules);
}
