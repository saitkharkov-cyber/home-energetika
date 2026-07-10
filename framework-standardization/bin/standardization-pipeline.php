<?php

require dirname(__DIR__) . '/bootstrap.php';

use FrameworkStandardization\Normalizer\NormalizerRegistry;
use FrameworkStandardization\Pipeline\StandardizationJobContractLoader;
use FrameworkStandardization\Pipeline\StandardizationPipeline;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "standardization-pipeline.php must be executed from CLI.\n");
    exit(1);
}

try {
    if (!isset($argv[1]) || trim((string) $argv[1]) === '') {
        throw new \InvalidArgumentException('usage: php bin/standardization-pipeline.php path/to/job.php [--format=markdown] [--output-dir=path] [--dry-run]');
    }

    $options = parseOptions($argv);
    $baseDir = dirname(__DIR__);
    $repoDir = dirname($baseDir);
    $registry = NormalizerRegistry::createDefault();
    $loader = new StandardizationJobContractLoader($repoDir, $registry);
    $job = $loader->load($argv[1]);

    if ($options['output_dir'] !== null) {
        $loader->assertOutputPathAllowed($options['output_dir']);
    }

    $pipeline = new StandardizationPipeline($repoDir, $registry);
    $result = $pipeline->run($job, $options['output_dir'], $options['dry_run']);
    $package = $result['package'];
    $manifest = $package['manifest'];

    if ($options['format'] === 'markdown') {
        printMarkdownSummary($manifest, $result);
    } else {
        printPlainSummary($manifest, $result);
    }

    exit(0);
} catch (\Exception $e) {
    fwrite(STDERR, 'standardization_pipeline_error: ' . $e->getMessage() . "\n");
    exit(1);
}

function parseOptions(array $argv)
{
    $options = array(
        'format' => 'plain',
        'output_dir' => null,
        'dry_run' => false,
    );

    for ($i = 2; $i < count($argv); $i++) {
        $arg = trim((string) $argv[$i]);

        if ($arg === '') {
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
            continue;
        }

        if (strpos($arg, '--format=') === 0) {
            $format = substr($arg, strlen('--format='));

            if ($format !== 'plain' && $format !== 'markdown') {
                throw new \InvalidArgumentException('pipeline_output_format_unknown');
            }

            $options['format'] = $format;
            continue;
        }

        if (strpos($arg, '--output-dir=') === 0) {
            $options['output_dir'] = substr($arg, strlen('--output-dir='));
            continue;
        }

        if ($arg === '--confirm-apply') {
            throw new \InvalidArgumentException('pipeline_confirm_apply_not_supported');
        }

        throw new \InvalidArgumentException('pipeline_unexpected_cli_argument');
    }

    return $options;
}

function printPlainSummary(array $manifest, array $result)
{
    echo "command: standardization_pipeline\n";
    echo "job_key: " . $manifest['job_key'] . "\n";
    echo "runtime_mode: " . $manifest['runtime_mode'] . "\n";
    echo "database_name: " . $manifest['database_name'] . "\n";
    echo "run_id: " . $manifest['run_id'] . "\n";
    echo "review_package: " . ($result['run_directory'] === null ? 'none' : $result['run_directory']) . "\n";
    echo "discovery_candidates: " . $manifest['counts']['discovery_candidates'] . "\n";
    echo "inventory_attributes: " . $manifest['counts']['inventory_attributes'] . "\n";
    echo "proposals_total: " . $manifest['counts']['proposals_total'] . "\n";

    foreach ($manifest['counts']['proposal_statuses'] as $status => $count) {
        echo "proposal_status_" . $status . ": " . $count . "\n";
    }

    foreach ($manifest['safety_markers'] as $key => $value) {
        echo $key . ": " . $value . "\n";
    }
}

function printMarkdownSummary(array $manifest, array $result)
{
    echo "# Standardization pipeline\n\n";
    echo "- command: standardization_pipeline\n";
    echo "- job_key: " . markdownCell($manifest['job_key']) . "\n";
    echo "- runtime_mode: " . markdownCell($manifest['runtime_mode']) . "\n";
    echo "- database_name: " . markdownCell($manifest['database_name']) . "\n";
    echo "- run_id: " . markdownCell($manifest['run_id']) . "\n";
    echo "- review_package: " . markdownCell($result['run_directory'] === null ? 'none' : $result['run_directory']) . "\n";
    echo "- discovery_candidates: " . markdownCell($manifest['counts']['discovery_candidates']) . "\n";
    echo "- inventory_attributes: " . markdownCell($manifest['counts']['inventory_attributes']) . "\n";
    echo "- proposals_total: " . markdownCell($manifest['counts']['proposals_total']) . "\n\n";
    echo "## Proposal status counts\n\n";

    foreach ($manifest['counts']['proposal_statuses'] as $status => $count) {
        echo "- " . markdownCell($status) . ": " . markdownCell($count) . "\n";
    }

    echo "\n## Safety markers\n\n";
    echo "```text\n";

    foreach ($manifest['safety_markers'] as $key => $value) {
        echo $key . ": " . $value . "\n";
    }

    echo "```\n";
}

function markdownCell($value)
{
    $value = str_replace(array("\r\n", "\r", "\n"), ' ', (string) $value);

    if ($value === '') {
        return 'none';
    }

    return str_replace('|', '\\|', $value);
}
