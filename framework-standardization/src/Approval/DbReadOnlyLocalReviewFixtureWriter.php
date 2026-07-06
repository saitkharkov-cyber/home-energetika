<?php

namespace FrameworkStandardization\Approval;

class DbReadOnlyLocalReviewFixtureWriter
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

    public function write($fixture, $filename = null)
    {
        $errors = array();
        $warnings = array();
        $source = is_array($fixture) && isset($fixture['source']) ? $fixture['source'] : 'local_review_fixture_writer';
        $fixtureType = is_array($fixture) && isset($fixture['fixture_type']) ? $fixture['fixture_type'] : null;
        $proposalsCount = is_array($fixture) && isset($fixture['proposals']) && is_array($fixture['proposals'])
            ? count($fixture['proposals'])
            : 0;

        $targetFilename = $filename;
        if ($targetFilename === null || $targetFilename === '') {
            $targetFilename = 'review_fixture_' . date('Ymd_His') . '.review.json';
        }

        $result = $this->createResult(
            0,
            $this->baseDir,
            $targetFilename,
            0,
            0,
            $fixtureType,
            $proposalsCount,
            $source,
            $errors,
            $warnings
        );

        if (!is_array($fixture)) {
            $errors[] = 'fixture_must_be_array';
            return $this->withMessages($result, $errors, $warnings);
        }

        $filenameError = $this->validateFilename($targetFilename);
        if ($filenameError !== null) {
            $errors[] = $filenameError;
            return $this->withMessages($result, $errors, $warnings);
        }

        if (!$this->ensureBaseDir()) {
            $errors[] = 'target_dir_not_writable';
            return $this->withMessages($result, $errors, $warnings);
        }

        $targetFile = $this->baseDir . DIRECTORY_SEPARATOR . $targetFilename;
        $result['target_file'] = $targetFile;
        $result['writer_diagnostics']['target_file'] = $targetFile;

        if (file_exists($targetFile)) {
            $errors[] = 'target_file_already_exists';
            return $this->withMessages($result, $errors, $warnings);
        }

        $json = $this->encodeJson($fixture);
        if ($json === false) {
            $errors[] = 'json_encode_failed';
            return $this->withMessages($result, $errors, $warnings);
        }

        $bytes = file_put_contents($targetFile, $json);
        if ($bytes === false) {
            $errors[] = 'write_failed';
            return $this->withMessages($result, $errors, $warnings);
        }

        return $this->createResult(
            1,
            $this->baseDir,
            $targetFilename,
            1,
            $bytes,
            $fixtureType,
            $proposalsCount,
            $source,
            $errors,
            $warnings
        );
    }

    private function validateFilename($filename)
    {
        if (!is_string($filename) || $filename === '') {
            return 'filename_must_be_string';
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

    private function ensureBaseDir()
    {
        if (is_dir($this->baseDir)) {
            return is_writable($this->baseDir);
        }

        return mkdir($this->baseDir, 0777, true);
    }

    private function encodeJson($fixture)
    {
        $options = 0;

        if (defined('JSON_PRETTY_PRINT')) {
            $options = $options | JSON_PRETTY_PRINT;
        }

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $options = $options | JSON_UNESCAPED_UNICODE;
        }

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $options = $options | JSON_UNESCAPED_SLASHES;
        }

        $json = json_encode($fixture, $options);
        if ($json === false) {
            return false;
        }

        return $json . PHP_EOL;
    }

    private function createResult(
        $written,
        $targetDir,
        $targetFilename,
        $wroteFile,
        $bytesWritten,
        $fixtureType,
        $proposalsCount,
        $source,
        $errors,
        $warnings
    ) {
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $targetFilename;
        $writesFiles = $wroteFile ? 1 : 0;

        $diagnostics = array(
            'writer_mode' => 'standalone_local_review_fixture_writer',
            'target_dir' => $targetDir,
            'target_file' => $targetFile,
            'wrote_file' => $wroteFile,
            'bytes_written' => $bytesWritten,
            'fixture_type' => $fixtureType,
            'proposals_count' => $proposalsCount,
            'writes_files' => $writesFiles,
            'sql_generated' => 0,
            'apply_plan_created' => 0,
            'safe_to_apply' => 0,
            'git_ignored_expected' => 1,
        );

        return array(
            'written' => $written,
            'writer_mode' => $diagnostics['writer_mode'],
            'target_dir' => $diagnostics['target_dir'],
            'target_file' => $diagnostics['target_file'],
            'wrote_file' => $diagnostics['wrote_file'],
            'bytes_written' => $diagnostics['bytes_written'],
            'fixture_type' => $diagnostics['fixture_type'],
            'proposals_count' => $diagnostics['proposals_count'],
            'writes_files' => $diagnostics['writes_files'],
            'sql_generated' => $diagnostics['sql_generated'],
            'apply_plan_created' => $diagnostics['apply_plan_created'],
            'safe_to_apply' => $diagnostics['safe_to_apply'],
            'git_ignored_expected' => $diagnostics['git_ignored_expected'],
            'writer_diagnostics' => $diagnostics,
            'errors' => $errors,
            'warnings' => $warnings,
            'source' => $source,
        );
    }

    private function withMessages($result, $errors, $warnings)
    {
        $result['errors'] = $errors;
        $result['warnings'] = $warnings;
        $result['writer_diagnostics']['wrote_file'] = $result['wrote_file'];
        $result['writer_diagnostics']['writes_files'] = $result['writes_files'];
        $result['writer_diagnostics']['bytes_written'] = $result['bytes_written'];

        return $result;
    }
}
