<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyLocalReviewFixtureLoader
{
    private $baseDir;

    public function __construct($baseDir = null)
    {
        if ($baseDir === null) {
            $projectDir = dirname(dirname(__DIR__));
            $baseDir = $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'review-fixtures';
        }

        $this->baseDir = rtrim($baseDir, "\\/");
    }

    public function load($filename)
    {
        $errors = array();
        $warnings = array();
        $source = 'local_review_fixture_loader';
        $targetFile = is_string($filename) ? $this->baseDir . DIRECTORY_SEPARATOR . $filename : '';

        $result = $this->createResult(
            0,
            null,
            $this->baseDir,
            $targetFile,
            0,
            0,
            null,
            0,
            0,
            $source,
            $errors,
            $warnings
        );

        $filenameError = $this->validateFilename($filename);
        if ($filenameError !== null) {
            $errors[] = $filenameError;
            return $this->withMessages($result, $errors, $warnings);
        }

        $targetFile = $this->baseDir . DIRECTORY_SEPARATOR . $filename;
        $result['target_file'] = $targetFile;
        $result['loader_diagnostics']['target_file'] = $targetFile;

        if (!is_file($targetFile)) {
            $errors[] = 'fixture_file_not_found';
            return $this->withMessages($result, $errors, $warnings);
        }

        $content = file_get_contents($targetFile);
        if ($content === false) {
            $errors[] = 'read_failed';
            return $this->withMessages($result, $errors, $warnings);
        }

        $bytesRead = strlen($content);
        $decoded = json_decode($content, true);
        if ($decoded === null && $this->jsonHasError()) {
            $errors[] = 'json_decode_failed';
            return $this->createResult(
                0,
                null,
                $this->baseDir,
                $targetFile,
                1,
                $bytesRead,
                null,
                0,
                1,
                $source,
                $errors,
                $warnings
            );
        }

        if (!is_array($decoded)) {
            $errors[] = 'decoded_fixture_must_be_array';
            return $this->createResult(
                0,
                null,
                $this->baseDir,
                $targetFile,
                1,
                $bytesRead,
                null,
                0,
                1,
                $source,
                $errors,
                $warnings
            );
        }

        $fixtureType = isset($decoded['fixture_type']) ? $decoded['fixture_type'] : null;
        $proposalsCount = isset($decoded['proposals']) && is_array($decoded['proposals'])
            ? count($decoded['proposals'])
            : 0;
        $source = isset($decoded['source']) ? $decoded['source'] : $source;

        if ($fixtureType === null || !isset($decoded['proposals']) || !is_array($decoded['proposals'])) {
            $errors[] = 'fixture_structure_invalid';
            return $this->createResult(
                0,
                null,
                $this->baseDir,
                $targetFile,
                1,
                $bytesRead,
                $fixtureType,
                $proposalsCount,
                1,
                $source,
                $errors,
                $warnings
            );
        }

        return $this->createResult(
            1,
            $decoded,
            $this->baseDir,
            $targetFile,
            1,
            $bytesRead,
            $fixtureType,
            $proposalsCount,
            1,
            $source,
            $errors,
            $warnings
        );
    }

    private function validateFilename($filename)
    {
        if (!is_string($filename) || $filename === '') {
            return 'filename_must_be_non_empty_string';
        }

        if ($this->isAbsolutePath($filename)) {
            return 'absolute_path_not_allowed';
        }

        if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            return 'path_separator_not_allowed';
        }

        if (strpos($filename, '..') !== false) {
            return 'path_traversal_not_allowed';
        }

        $lower = strtolower($filename);
        if (substr($lower, -5) !== '.json') {
            return 'only_json_extension_allowed';
        }

        $unsafeTokens = array('.sql', 'apply', 'production', 'migration', 'patch');
        foreach ($unsafeTokens as $token) {
            if (strpos($lower, $token) !== false) {
                return 'unsafe_filename_token';
            }
        }

        return null;
    }

    private function isAbsolutePath($filename)
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $filename)) {
            return true;
        }

        if (strpos($filename, '/') === 0 || strpos($filename, '\\') === 0) {
            return true;
        }

        return false;
    }

    private function jsonHasError()
    {
        if (!function_exists('json_last_error')) {
            return false;
        }

        return json_last_error() !== JSON_ERROR_NONE;
    }

    private function createResult(
        $loaded,
        $fixture,
        $targetDir,
        $targetFile,
        $loadedFile,
        $bytesRead,
        $fixtureType,
        $proposalsCount,
        $readsFiles,
        $source,
        $errors,
        $warnings
    ) {
        $diagnostics = array(
            'loader_mode' => 'standalone_local_review_fixture_loader',
            'target_dir' => $targetDir,
            'target_file' => $targetFile,
            'loaded_file' => $loadedFile,
            'bytes_read' => $bytesRead,
            'fixture_type' => $fixtureType,
            'proposals_count' => $proposalsCount,
            'reads_files' => $readsFiles,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'git_ignored_expected' => 1,
        );

        return array(
            'loaded' => $loaded,
            'fixture' => $fixture,
            'loader_diagnostics' => $diagnostics,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $source,
        );
    }

    private function withMessages($result, $errors, $warnings)
    {
        $result['errors'] = $errors;
        $result['warnings'] = $warnings;

        return $result;
    }
}
