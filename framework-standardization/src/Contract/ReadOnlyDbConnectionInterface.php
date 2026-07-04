<?php

namespace FrameworkStandardization\Contract;

interface ReadOnlyDbConnectionInterface
{
    public function fetchOne($sql, array $params);

    public function fetchAll($sql, array $params);
}
