<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

final class Fields
{
    private const SCALARS = ['string', 'int', 'float', 'bool', 'array'];

    private const RESERVED = ['this'];

    /**
     * Parse "name:type,..." into constructor properties.
     *
     * @return list<array{name: string, type: string, import: ?string}>
     *
     * @throws FieldException
     */
    public static function parse(?string $spec): array
    {
        if ($spec === null || trim($spec) === '') {
            return [];
        }

        $fields = [];
        $imports = [];

        foreach (explode(',', $spec) as $raw) {
            $entry = trim($raw);

            if ($entry === '') {
                throw new FieldException($entry, 'empty');
            }

            if (! str_contains($entry, ':')) {
                throw new FieldException($entry, 'missing type');
            }

            [$rawName, $rawType] = array_map('trim', explode(':', $entry, 2));
            $name = Str::camel($rawName);

            if (preg_match('/^[a-z][A-Za-z0-9]*$/', $name) !== 1) {
                throw new FieldException($entry, 'invalid name');
            }

            if (in_array($name, self::RESERVED, true)) {
                throw new FieldException($entry, "reserved name {$name}");
            }

            if (isset($fields[$name])) {
                throw new FieldException($entry, "duplicate name {$name}");
            }

            $nullable = str_starts_with($rawType, '?') ? '?' : '';
            $type = ltrim(substr($rawType, strlen($nullable)), '\\');
            $import = null;

            if (! in_array($type, self::SCALARS, true)) {
                if (! self::isClassName($type)) {
                    throw new FieldException($entry, "unknown type {$rawType}");
                }

                $import = $type;
                $type = class_basename($type);

                if (PhpNames::isReserved($type)) {
                    throw new FieldException($entry, "reserved class name {$type}");
                }

                if (isset($imports[$type]) && $imports[$type] !== $import) {
                    throw new FieldException($entry, "two class types named {$type}");
                }

                $imports[$type] = $import;
            }

            $fields[$name] = ['name' => $name, 'type' => $nullable.$type, 'import' => $import];
        }

        return array_values($fields);
    }

    private static function isClassName(string $type): bool
    {
        if (! str_contains($type, '\\')) {
            return false;
        }

        foreach (explode('\\', $type) as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $segment) !== 1) {
                return false;
            }
        }

        return true;
    }
}
