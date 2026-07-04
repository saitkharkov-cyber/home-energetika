<?php

namespace FrameworkStandardization\Contract;

use FrameworkStandardization\DTO\AttributeContext;

interface StageInterface
{
    public function getName();

    public function run(AttributeContext $context);
}
