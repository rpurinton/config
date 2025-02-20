<?php

namespace RPurinton\Helpers;

use RPurinton\Exceptions\ConfigException;

class ConfigHelpers
{
    /**
     * Reads and decodes the JSON configuration from the given file.
     *
     * @param string $file Path to the JSON configuration file.
     * @return array Decoded configuration array.
     * @throws ConfigException If file is unreadable or JSON is invalid.
     */
    public static function readConfigFromFile(string $file): array
    {
        if (!is_readable($file)) {
            throw new ConfigException("Configuration file at {$file} is not readable. Please verify file permissions.");
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new ConfigException("Unable to open configuration file at {$file} for reading.");
        }
        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw new ConfigException("Unable to obtain a shared lock while reading the file at {$file}.");
        }

        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($content === false) {
            throw new ConfigException("Unable to read the content from {$file}.");
        }

        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException("Invalid JSON in {$file}: " . $e->getMessage());
        }
    }

    /**
     * Returns the configuration directory path.
     *
     * @return string Directory path.
     * @throws ConfigException If the directory doesn't exist.
     */
    public static function getConfigDir(): string
    {
        // Retain the original directory calculation.
        $dir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            throw new ConfigException("Configuration directory at {$dir} does not exist.");
        }
        return $dir;
    }

    /**
     * Atomically replaces the target file with the temporary file.
     *
     * @param string $tempFile Path to the temporary file.
     * @param string $targetFile Path to the target file.
     * @throws ConfigException If the replacement operation fails.
     */
    public static function atomicFileReplace(string $tempFile, string $targetFile): void
    {
        $fp = null;
        try {
            if (file_exists($targetFile)) {
                $fp = fopen($targetFile, 'c');
                if (!$fp) {
                    throw new ConfigException("Unable to open {$targetFile} for locking.");
                }
                if (!flock($fp, LOCK_EX)) {
                    throw new ConfigException("Unable to obtain an exclusive lock for {$targetFile}.");
                }
            }

            if (!rename($tempFile, $targetFile)) {
                throw new ConfigException("Atomic replacement failed: unable to rename {$tempFile} to {$targetFile}.");
            }
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw $e;
        } finally {
            if ($fp !== null) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    /**
     * Writes the provided JSON string to the target file atomically.
     *
     * @param string $targetFile Path to the target configuration file.
     * @param string $json JSON encoded configuration.
     * @throws ConfigException If any file operation fails.
     */
    public static function writeJsonToFile(string $targetFile, string $json): void
    {
        $tempFile = tempnam(dirname($targetFile), 'tmp_');
        if ($tempFile === false) {
            throw new ConfigException("Failed to create a temporary file in " . dirname($targetFile) . ".");
        }

        if (file_put_contents($tempFile, $json) === false) {
            @unlink($tempFile);
            throw new ConfigException("Failed to write configuration data to temporary file at {$tempFile}.");
        }

        self::atomicFileReplace($tempFile, $targetFile);
    }
}
