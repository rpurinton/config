<?php

namespace RPurinton\Validators;

use RPurinton\Exceptions\ConfigValidationException;

class ConfigValidators
{
    public static function validateRequired(array $keys, array &$config): void
    {
        foreach ($keys as $key => $expected) {
            $normalizedKey = self::normalizeKey($key, $config);
            if ($normalizedKey === null) {
                throw new ConfigValidationException("Missing key '{$key}'.");
            }

            $value = $config[$normalizedKey];

            if (is_callable($expected)) {
                try {
                    $result = $expected($value);
                    if ($result !== true) {
                        throw new ConfigValidationException("Config validation failed for key '{$key}'.");
                    }
                } catch (\Throwable $e) {
                    throw new ConfigValidationException("Config validation exception for key '{$key}': " . $e->getMessage());
                }
            } elseif (is_array($expected)) {
                if (!is_array($value)) {
                    $got = gettype($value);
                    throw new ConfigValidationException("Invalid type for '{$key}': expected array, got {$got}.");
                }
                self::validateRequired($expected, $value);
            } else {
                $normalizedExpected = self::normalizeType($expected);
                $actualType = gettype($value);
                if ($normalizedExpected !== $actualType) {
                    throw new ConfigValidationException("Invalid type for '{$key}': expected {$expected} ({$normalizedExpected}), got {$actualType}.");
                }
            }
        }
    }

    private static function normalizeType(string $type): string
    {
        $map = [
            'bool'    => 'boolean',
            'boolean' => 'boolean',
            'int'     => 'integer',
            'integer' => 'integer',
            'float'   => 'double',
            'double'  => 'double',
            'string'  => 'string',
            'array'   => 'array'
        ];
        return $map[$type] ?? $type;
    }

    private static function normalizeKey(string $key, array &$config): ?string
    {
        $possibleKeys = explode('|', $key);
        $first_key = $possibleKeys[0];
        if (array_key_exists($first_key, $config)) {
            return $first_key;
        }
        foreach ($possibleKeys as $possibleKey) {
            if (array_key_exists($possibleKey, $config)) {
                $config[$first_key] = $config[$possibleKey];
                unset($config[$possibleKey]);
                return $first_key;
            }
        }
        return null;
    }
}
