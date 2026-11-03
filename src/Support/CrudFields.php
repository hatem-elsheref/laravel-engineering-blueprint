<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

final class CrudFields
{
    private const AUTOMATIC = ['id', 'created_at', 'updated_at'];

    private const DATE_CLASS = 'Carbon\\CarbonImmutable';

    /**
     * @return list<CrudField>
     *
     * @throws FieldException
     */
    public static function parse(?string $spec): array
    {
        $parsed = Fields::parse($spec);
        $entries = array_values(array_filter(array_map('trim', explode(',', (string) $spec)), fn ($e) => $e !== ''));
        $fields = [];

        foreach ($parsed as $index => $field) {
            $entry = $entries[$index] ?? $field['name'];
            $column = Str::snake($field['name']);

            if ($field['import'] !== null && $field['import'] !== self::DATE_CLASS) {
                throw new FieldException($entry, 'make:crud supports string, int, float, bool, array and Carbon\\CarbonImmutable');
            }

            if (in_array($column, self::AUTOMATIC, true)) {
                throw new FieldException($entry, "{$column} is created automatically");
            }

            $fields[] = new CrudField(
                property: $field['name'],
                column: $column,
                type: $field['type'],
                nullable: str_starts_with($field['type'], '?'),
                import: $field['import'],
            );
        }

        return $fields;
    }
}
