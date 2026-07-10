<?php

namespace FrameworkStandardization\OpenCart;

final class OpenCartRuntimeConfig
{
    private $runtimeMode;
    private $database;
    private $safety;

    private function __construct($runtimeMode, array $database, array $safety)
    {
        $this->runtimeMode = $runtimeMode;
        $this->database = $database;
        $this->safety = $safety;
    }

    public static function fromArray(array $config)
    {
        if (!isset($config['runtime_mode']) || $config['runtime_mode'] === '') {
            throw new \InvalidArgumentException('runtime_mode_required');
        }

        if (!isset($config['database']) || !is_array($config['database'])) {
            throw new \InvalidArgumentException('database_config_required');
        }

        $runtimeMode = (string) $config['runtime_mode'];

        if ($runtimeMode !== 'db_readonly' && $runtimeMode !== 'live_db_readonly') {
            throw new \InvalidArgumentException('runtime_mode_not_supported');
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

        self::validateDatabase($database);

        $database['port'] = (int) $database['port'];
        $safety = isset($config['safety']) && is_array($config['safety']) ? $config['safety'] : array();

        if ($runtimeMode === 'db_readonly') {
            self::validateControlledLocalDumpRuntime($database);
        } elseif ($runtimeMode === 'live_db_readonly') {
            self::validateLiveReadOnlyRuntime($database, $safety);
        }

        return new self($runtimeMode, $database, $safety);
    }

    public function getRuntimeMode()
    {
        return $this->runtimeMode;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    public function getSafety()
    {
        return $this->safety;
    }

    public function getDbPrefix()
    {
        return $this->database['db_prefix'];
    }

    private static function validateDatabase(array $database)
    {
        if ($database['driver'] !== 'pdo_mysql') {
            throw new \InvalidArgumentException('runtime_driver_not_supported');
        }

        if (trim((string) $database['host']) === '') {
            throw new \InvalidArgumentException('runtime_host_required');
        }

        if (trim((string) $database['dbname']) === '') {
            throw new \InvalidArgumentException('runtime_dbname_required');
        }

        if (trim((string) $database['username']) === '') {
            throw new \InvalidArgumentException('runtime_username_required');
        }

        if (!is_int($database['port']) && !preg_match('/^[0-9]+$/', (string) $database['port'])) {
            throw new \InvalidArgumentException('runtime_port_invalid');
        }

        $port = (int) $database['port'];

        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('runtime_port_out_of_range');
        }

        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $database['db_prefix'])) {
            throw new \InvalidArgumentException('runtime_db_prefix_invalid');
        }
    }

    private static function validateControlledLocalDumpRuntime(array $database)
    {
        if ((string) $database['host'] !== '127.0.1.19') {
            throw new \InvalidArgumentException('runtime_host_not_allowed');
        }

        if ((string) $database['dbname'] !== 'he_framework_local_dump') {
            throw new \InvalidArgumentException('runtime_dbname_not_allowed');
        }

        if ((string) $database['db_prefix'] !== 'oc_') {
            throw new \InvalidArgumentException('runtime_db_prefix_not_allowed');
        }
    }

    private static function validateLiveReadOnlyRuntime(array $database, array $safety)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', (string) $database['dbname'])) {
            throw new \InvalidArgumentException('runtime_dbname_invalid');
        }

        $requiredSafety = array(
            'read_only' => true,
            'allow_write' => false,
            'allow_confirm_apply' => false,
            'production_ready' => false,
            'cache_rebuild_allowed' => false,
        );

        foreach ($requiredSafety as $key => $expectedValue) {
            if (!array_key_exists($key, $safety)) {
                throw new \InvalidArgumentException('runtime_safety_' . $key . '_required');
            }

            if ($safety[$key] !== $expectedValue) {
                throw new \InvalidArgumentException('runtime_safety_' . $key . '_invalid');
            }
        }
    }
}
