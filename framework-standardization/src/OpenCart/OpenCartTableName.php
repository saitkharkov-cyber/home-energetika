<?php

namespace FrameworkStandardization\OpenCart;

final class OpenCartTableName
{
    private $dbPrefix;

    public function __construct($dbPrefix)
    {
        if (!preg_match('/^[A-Za-z0-9_]*$/', $dbPrefix)) {
            throw new \InvalidArgumentException('db_prefix_invalid');
        }

        $this->dbPrefix = $dbPrefix;
    }

    public function name($baseName)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $baseName)) {
            throw new \InvalidArgumentException('table_name_invalid');
        }

        return $this->dbPrefix . $baseName;
    }
}
