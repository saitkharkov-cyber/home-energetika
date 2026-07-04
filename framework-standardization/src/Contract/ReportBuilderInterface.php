<?php

namespace FrameworkStandardization\Contract;

use FrameworkStandardization\DTO\AttributeContext;

interface ReportBuilderInterface
{
    public function build(AttributeContext $context);
}
