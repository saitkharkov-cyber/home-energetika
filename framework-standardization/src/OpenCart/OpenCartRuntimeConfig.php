<?php

namespace FrameworkStandardization\OpenCart;

final class OpenCartRuntimeConfig
{
    private $runtimeMode;
    private $database;

    private function __construct($runtimeMode, array $database)
    {
        $this->runtimeMode = $runtimeMode;
        $this->database = $database;
    }

    public static function fromArray(array $config)
    {
        if (!isset($config['runtime_mode']) || $config['runtime_mode'] === '') {
            throw new \InvalidArgumentException('runtime_mode_required');
        }

        if (!isset($config['database']) || !is_array($config['database'])) {
            throw new \InvalidArgumentException('database_config_required');
        }

        $database = $config['database'];
        $requiredFields = array(
            'driver',
            'host',
            'port',
            'dbname',
            'username',
            'password',
            'charset',
            'db_prefix',
        );

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $database)) {
                throw new \InvalidArgumentException('database_' . $field . '_required');
            }
        }

        return new self($config['runtime_mode'], $database);
    }

    public function getRuntimeMode()
    {
        return $this->runtimeMode;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getDbPrefix()
    {
        return $this->database['db_prefix'];
    }
}
