<?php

namespace FrameworkStandardization\Contract;

interface AttributeExporterInterface
{
    public function export(array $canonical, array $scope, array $products);
}
