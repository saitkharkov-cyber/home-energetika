<?php

namespace FrameworkStandardization\OpenCart;

use FrameworkStandardization\Contract\ReadOnlyDbConnectionInterface;
use PDO;

final class PdoReadOnlyDbConnection implements ReadOnlyDbConnectionInterface
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function fetchOne($sql, array $params)
    {
        $this->assertReadOnlySql($sql);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return array();
        }

        return $row;
    }

    public function fetchAll($sql, array $params)
    {
        $this->assertReadOnlySql($sql);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertReadOnlySql($sql)
    {
        $trimmedSql = trim($sql);

        if (!preg_match('/^(SELECT|SHOW)\b/i', $trimmedSql)) {
            throw new \InvalidArgumentException('read_only_sql_required');
        }

        if (strpos($trimmedSql, ';') !== false) {
            throw new \InvalidArgumentException('multi_statement_sql_not_allowed');
        }

        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE)\b/i', $trimmedSql)) {
            throw new \InvalidArgumentException('file_write_sql_not_allowed');
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|CREATE)\b/i', $trimmedSql)) {
            throw new \InvalidArgumentException('write_sql_not_allowed');
        }
    }
}
