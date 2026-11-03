<?php

namespace Best\Blueprint\Settings;

/**
 * Converts a value to a setting's declared type, or returns null when it doesn't fit.
 */
final class SettingTypes
{
    public const TYPES = ['string', 'int', 'float', 'bool', 'array'];

    /**
     * @return array{0: bool, 1: mixed} [fits, converted value]
     */
    public static function convert(string $type, mixed $value): array
    {
        return match ($type) {
            'string' => is_string($value) || is_int($value) || is_float($value) ? [true, (string) $value] : [false, null],
            'int' => is_int($value) ? [true, $value]
                : (is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? [true, (int) $value] : [false, null]),
            'float' => (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) ? [true, (float) $value] : [false, null],
            'bool' => match (true) {
                in_array($value, [true, 1, '1', 'true'], true) => [true, true],
                in_array($value, [false, 0, '0', 'false'], true) => [true, false],
                default => [false, null],
            },
            'array' => is_array($value) ? [true, $value] : [false, null],
            default => [false, null],
        };
    }
}
