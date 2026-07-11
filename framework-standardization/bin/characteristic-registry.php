<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\Registry\CharacteristicRegistryBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "characteristic-registry.php must be executed from CLI.\n");
    exit(1);
}

try {
    $options = parseCharacteristicRegistryArgs($argv);
    $input = loadCharacteristicRegistryInput($options['input_path']);

    $normalizerRegistry = NormalizerRegistry::createDefault();
    $builder = new CharacteristicRegistryBuilder($normalizerRegistry);
    $result = $builder->build(
        $input['scope'],
        $input['discovered_attributes'],
        $input['legacy_decisions']
    );

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($options['pretty']) {
        $jsonFlags = $jsonFlags | JSON_PRETTY_PRINT;
    }

    $json = json_encode($result, $jsonFlags);
    if ($json === false) {
        throw new \RuntimeException('characteristic_registry_cli_output_json_failed');
    }

    echo $json . "\n";
    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'characteristic_registry_cli_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseCharacteristicRegistryArgs(array $argv)
{
    $inputPath = null;
    $pretty = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = trim((string) $argv[$i]);

        if ($arg === '') {
            continue;
        }

        if ($arg === '--pretty') {
            $pretty = true;
            continue;
        }

        if (strpos($arg, '--') === 0) {
            throw new \InvalidArgumentException('characteristic_registry_cli_unexpected_argument');
        }

        if ($inputPath !== null) {
            throw new \InvalidArgumentException('characteristic_registry_cli_unexpected_argument');
        }

        $inputPath = $arg;
    }

    if ($inputPath === null) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_required');
    }

    return array(
        'input_path' => $inputPath,
        'pretty' => $pretty,
    );
}

function loadCharacteristicRegistryInput($path)
{
    if (!is_file($path)) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_file_not_found');
    }

    if (!is_readable($path)) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_file_unreadable');
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_file_unreadable');
    }

    $trimmed = ltrim($contents);
    if ($trimmed !== '' && substr($trimmed, 0, 1) === '[') {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_root_invalid');
    }

    $input = json_decode($contents, true);
    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_json_invalid');
    }

    if (!is_array($input)) {
        throw new \InvalidArgumentException('characteristic_registry_cli_input_root_invalid');
    }

    if (!isset($input['scope']) || !is_array($input['scope'])) {
        throw new \InvalidArgumentException('characteristic_registry_cli_scope_required');
    }

    if (!isset($input['discovered_attributes']) || !is_array($input['discovered_attributes'])) {
        throw new \InvalidArgumentException('characteristic_registry_cli_discovered_attributes_required');
    }

    if (!isset($input['legacy_decisions']) || !is_array($input['legacy_decisions'])) {
        throw new \InvalidArgumentException('characteristic_registry_cli_legacy_decisions_required');
    }

    return $input;
}
