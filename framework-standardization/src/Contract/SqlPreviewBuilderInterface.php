<?php

namespace FrameworkStandardization\Contract;

interface SqlPreviewBuilderInterface
{
    public function build(array $canonical, array $scope, array $attributeNameStructure, array $synonymCandidates, array $attributeValueStructure, array $valueReport, array $outputRules, array $valueRules);
}
