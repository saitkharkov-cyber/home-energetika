<?php

namespace FrameworkStandardization\Contract;

use FrameworkStandardization\DTO\AttributeContext;

interface FrameworkResultBuilderInterface
{
    public function build(AttributeContext $context);
}
