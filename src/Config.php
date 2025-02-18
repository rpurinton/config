<?php

namespace RPurinton;

/**
 * Class Config
 *
 * This class provides functionality to manage configuration data stored in a JSON file.
 * It supports reading from an existing file or creating a new one with an empty configuration.
 *
 * @package RPurinton
 */
class Config
{
    /**
     * Path to the configuration file.
     *
     * @var string
     */
    private string $file;

    /**
     * Parsed configuration data.
     *
     * @var array
     */
    public array $config;

    /**
     * Constructor.
     *
     * Initializes the configuration by attempting to read from an existing JSON file.
     * If the file does not exist, it creates an initial empty configuration file.
     *
     * @param string $name The base name of the configuration file (without extension).
     *
     * @throws ConfigException If file operations or JSON encoding/decoding fails.
     */
    public function __construct(private string $name, array $required = [])
    {
        $this->file = $this->dir() . $this->name . '.json';

        if (!file_exists($this->file)) {
            $this->createInitialConfig();
        } else {
            $this->readConfigFromFile();
        }

        if (!empty($required)) {
            $this->required($required);
        }
    }

    /**
     * Creates an initial configuration file with an empty configuration.
     *
     * This method initializes the configuration as an empty array, encodes it into a JSON string
     * using JSON_PRETTY_PRINT, JSON_UNESCAPED_SLASHES, and JSON_THROW_ON_ERROR options, and writes
     * the JSON to the configuration file. If encoding or file writing fails, a ConfigException is thrown.
     *
     * @throws ConfigException If JSON encoding fails or writing to the configuration file fails.
     */
    private function createInitialConfig(): void
    {
        $this->config = [];
        try {
            $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException("Failed to encode initial configuration data into JSON. Error: " . $e->getMessage() . ". Ensure that the configuration structure is correct.");
        }
        if (file_put_contents($this->file, $json) === false) {
            throw new ConfigException("Failed to create initial configuration file at {$this->file}. Please check file permissions and available disk space.");
        }
    }

    /**
     * Reads and decodes the configuration data from the JSON file.
     *
     * This method performs the following steps:
     * 1. Verifies that the configuration file is readable.
     * 2. Opens the file in read mode and acquires a shared lock to ensure the file isn't modified during reading.
     * 3. Reads the entire file content.
     * 4. Releases the lock and closes the file handle.
     * 5. Decodes the JSON content into an associative array.
     *
     * @return void
     *
     * @throws ConfigException If:
     *         - The file is not readable.
     *         - The file cannot be opened.
     *         - A shared lock cannot be obtained.
     *         - The file content cannot be read.
     *         - The JSON content is invalid.
     */
    private function readConfigFromFile(): void
    {
        if (!is_readable($this->file)) {
            throw new ConfigException("Configuration file at {$this->file} is not readable. Please verify file permissions.");
        }

        $handle = fopen($this->file, 'r');
        if (!$handle) {
            throw new ConfigException("Unable to open configuration file at {$this->file} for reading. Please check if the file exists and is accessible.");
        }
        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw new ConfigException("Unable to obtain a shared lock while reading the configuration file at {$this->file}. Try closing any applications that might be using it.");
        }

        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($content === false) {
            throw new ConfigException("Unable to read the content from configuration file at {$this->file}. The file may be corrupted or locked by another process.");
        }

        try {
            $this->config = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException("Invalid JSON in configuration file at {$this->file}. Error: " . $e->getMessage() . ". Please verify the file format.");
        }
    }

    /**
     * Static factory method to create a new Config instance and return the configuration array only.
     * This method is useful when you only need to read the configuration data without saving it back.
     * If you need to save the configuration, use the constructor and call the save() method.
     * 
     * @param string $name The base name of the configuration file (without extension).
     * @param array $required An associative array of required keys and their expected types.
     * 
     * @return array The configuration data as an associative array.
     */
    public static function get(string $name, array $required = []): array
    {
        return (new Config($name, $required))->config;
    }

    /**
     * Saves the current configuration to a JSON file atomically.
     *
     * This method performs the following steps:
     * 1. Encodes the current configuration data into a JSON string.
     * 2. Writes the JSON to a temporary file in the same directory as the target.
     * 3. If the target configuration file exists, obtains an exclusive lock on it to prevent race conditions.
     * 4. Replaces the original configuration file with the temporary file atomically using rename().
     * 5. Releases the lock if one was acquired.
     *
     * Using a temporary file and atomic replacement ensures that the configuration file is always in a consistent state,
     * even in the event of a write or system failure.
     *
     * @throws ConfigException If encoding the JSON fails, if writing to the temporary file fails,
     *                         if obtaining an exclusive lock fails, or if the atomic replacement fails.
     */
    public function save(): void
    {
        try {
            $json = json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException("Failed to encode configuration data into JSON. Error: " . $e->getMessage() . ". Make sure that the configuration array has valid data.");
        }

        $tempFile = tempnam(dirname($this->file), 'tmp_');
        if ($tempFile === false) {
            throw new ConfigException("Failed to create a temporary file in directory " . dirname($this->file) . ". Please check permissions on the target directory.");
        }

        if (file_put_contents($tempFile, $json) === false) {
            @unlink($tempFile);
            throw new ConfigException("Failed to write configuration data to temporary file at {$tempFile}. Verify disk space and file permissions.");
        }

        $fp = null;
        try {
            // Obtain an exclusive lock on the original file if it exists
            if (file_exists($this->file)) {
                $fp = fopen($this->file, 'c');
                if (!$fp) {
                    throw new ConfigException("Unable to open configuration file at {$this->file} for locking. Ensure the file exists and is writable.");
                }
                if (!flock($fp, LOCK_EX)) {
                    throw new ConfigException("Unable to obtain an exclusive lock for configuration file at {$this->file}. Try closing other programs that may be accessing the file.");
                }
            }

            if (!rename($tempFile, $this->file)) {
                throw new ConfigException("Atomic replacement failed. Unable to rename temporary file {$tempFile} to configuration file {$this->file}. Ensure that both files are on the same filesystem and that you have the needed permissions.");
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
     * Retrieves or creates the configuration directory.
     *
     * This method determines the absolute path for storing configuration files
     * and ensures that the directory exists by attempting to create it if it's missing.
     * The returned path always ends with a trailing slash.
     *
     * @return string The absolute path to the configuration directory.
     *
     * @throws ConfigException If the configuration directory does not exist and cannot be created.
     */
    private function dir(): string
    {
        $dir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new ConfigException("Failed to create configuration directory at {$dir}. Please verify write permissions and that the path is correct.");
        }
        return $dir;
    }

    /**
     * Validates that all required keys are present in the configuration.
     *
     * This method supports both flat keys (where the value is a string defining the expected type)
     * and nested keys (where the value is an array defining a set of subkeys and their expected types).
     * The function will recursively traverse the configuration array to ensure all keys are present
     * and that each key’s value matches the expected type.
     *
     * The allowed primitive types are:
     * - 'string'
     * - 'int'
     * - 'bool'
     * - 'float'
     * - 'array'
     *
     * When a nested key is defined (i.e. its expected type is an array), this method will validate that:
     * - The corresponding configuration entry is an array.
     * - All the required subkeys exist and have the correct types as defined in the nested array.
     *
     * @param array $keys An associative array where each key is expected in the configuration. The value can either be:
     *                    - A string representing a basic type (e.g., 'string', 'int', etc.), or
     *                    - An array which recursively defines subkeys and their expected types.
     *
     * @throws ConfigException If a required key is missing or a key's value does not match the expected type.
     */
    public function required(array $keys): void
    {
        $this->validateRequired($keys, $this->config, $this->file);
    }

    /**
     * Recursively validates the configuration array against the required keys definition.
     *
     * This is a helper method used by the required() method and works as follows:
     * 1. For each key defined in the $keys array:
     *    a. Verify that the key exists in the provided $config array.
     *    b. If the expected type is itself an array, confirm that the corresponding configuration value is an array
     *       and recursively validate its subkeys.
     *    c. If the expected type is a string, validate that the configuration value is of the specified type.
     * 2. If a key is missing or a type mismatch is detected, a ConfigException is thrown with a descriptive error message.
     *
     * The $context parameter provides additional information about where in the configuration chain the error occurred.
     *
     * @param array  $keys    The associative array of required keys and their expected types (or nested definitions).
     * @param array  $config  The portion of the configuration array to validate.
     * @param string $context A context string (typically the configuration file path or parent key chain)
     *                        used to produce detailed error messages indicating where the error was encountered.
     *
     * @throws ConfigException If a required key is missing, or if a configuration value does not match the expected type.
     */
    private function validateRequired(array $keys, array $config, string $context): void
    {
        foreach ($keys as $key => $type) {
            if (!array_key_exists($key, $config)) {
                throw new ConfigException("Missing required {$this->name} configuration key '{$key}' in {$context}. Please add this key to the configuration.");
            }

            // If $type is an array, assume nested configuration and validate recursively
            if (is_array($type)) {
                if (!is_array($config[$key])) {
                    $got = gettype($config[$key]);
                    throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected array, got {$got}. Please update the configuration.");
                }
                $this->validateRequired($type, $config[$key], $context . "->{$key}");
            } else {
                // Validate the configuration value against the expected primitive type
                switch ($type) {
                    case 'string':
                        if (!is_string($config[$key])) {
                            $got = gettype($config[$key]);
                            throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected string, got {$got}. Please update the configuration.");
                        }
                        break;
                    case 'int':
                        if (!is_int($config[$key])) {
                            $got = gettype($config[$key]);
                            throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected integer, got {$got}. Please update the configuration.");
                        }
                        break;
                    case 'bool':
                        if (!is_bool($config[$key])) {
                            $got = gettype($config[$key]);
                            throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected boolean, got {$got}. Please update the configuration.");
                        }
                        break;
                    case 'array':
                        if (!is_array($config[$key])) {
                            $got = gettype($config[$key]);
                            throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected array, got {$got}. Please update the configuration.");
                        }
                        break;
                    case 'float':
                        if (!is_float($config[$key])) {
                            $got = gettype($config[$key]);
                            throw new ConfigException("Invalid type for configuration key '{$key}' in {$context}: expected float, got {$got}. Please update the configuration.");
                        }
                        break;
                    default:
                        $got = gettype($config[$key]);
                        throw new ConfigException("Invalid configuration type for key '{$key}' in {$context}: expected {$type}, got {$got}. Please update the configuration accordingly.");
                }
            }
        }
    }
}
