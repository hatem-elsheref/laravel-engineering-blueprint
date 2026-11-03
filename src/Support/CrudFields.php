<?php

namespace Best\Blueprint\Support;

use Best\Blueprint\Schema\EntitySchemaLoader;
use Best\Blueprint\Schema\EntitySchemaMeta;
use Best\Blueprint\Schema\SchemaException;
use Illuminate\Support\Str;

final class CrudFields
{
    private const AUTOMATIC = ['id', 'created_at', 'updated_at', 'deleted_at'];

    private const DATE_CLASS = 'Carbon\\CarbonImmutable';

    /**
     * @return array{0: list<CrudField>, 1: EntitySchemaMeta}
     */
    public static function fromSchema(string $schemaPath, string $module, string $entity, Modules $modules): array
    {
        return (new EntitySchemaLoader)->load($schemaPath, $module, $entity, $modules);
    }

    /**
     * @return list<CrudField>
     *
     * @throws FieldException|SchemaException
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
                throw new FieldException($entry, 'make:crud supports string, int, float, bool, array and Carbon\\CarbonImmutable (use --schema= for enums and foreign keys)');
            }

            $fields[] = new CrudField(
                property: $field['name'],
                column: $column,
                type: $field['type'],
                nullable: str_starts_with($field['type'], '?'),
                import: $field['import'],
            );
        }

        self::assertNoAutomaticColumns($fields);

        return $fields;
    }

    /**
     * @param  list<CrudField>  $fields
     */
    public static function assertNoAutomaticColumns(array $fields): void
    {
        foreach ($fields as $field) {
            if (in_array($field->column, self::AUTOMATIC, true)) {
                throw new FieldException($field->column, 'is created automatically');
            }
        }
    }
}
