<?php

declare(strict_types=1);

namespace Best\Blueprint\Schema;

use Best\Blueprint\Support\CrudField;
use Best\Blueprint\Support\CrudFields;
use Best\Blueprint\Support\ModuleName;
use Best\Blueprint\Support\Modules;
use Best\Blueprint\Support\PhpNames;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

final class EntitySchemaLoader
{
    /**
     * @return array{0: list<CrudField>, 1: EntitySchemaMeta}
     */
    public function load(string $schemaPath, string $module, string $entity, Modules $modules): array
    {
        $path = $this->resolvePath($schemaPath);

        if (! is_file($path)) {
            throw SchemaException::invalid("schema file not found: {$schemaPath}");
        }

        /** @var mixed $raw */
        $raw = Yaml::parseFile($path);

        if (! is_array($raw)) {
            throw SchemaException::invalid('schema root must be a mapping');
        }

        $raw = $this->mergeProfile($raw);

        $fieldsRaw = $raw['fields'] ?? null;

        if (! is_array($fieldsRaw) || $fieldsRaw === []) {
            throw SchemaException::invalid('fields must be a non-empty list');
        }

        $moduleNamespace = $modules->namespace().'\\'.$module;
        $fields = [];
        $enumFiles = [];
        $modelImports = [];
        $relations = [];

        foreach ($fieldsRaw as $index => $item) {
            if (! is_array($item)) {
                throw SchemaException::invalid('each field must be a mapping (index '.$index.')');
            }

            $fields[] = $this->parseField($item, $index, $module, $moduleNamespace, $entity, $enumFiles, $modelImports, $relations);
        }

        CrudFields::assertNoAutomaticColumns($fields);

        $fields = $this->applyEntityResourceHidden($fields, $raw);
        [$queryFilterLines, $hasQueryFilters] = $this->renderFilters($raw['filters'] ?? [], $fields);

        $softDeletes = (bool) ($raw['softDeletes'] ?? false);
        $indexes = $this->columnGroups($raw['indexes'] ?? [], 'indexes');
        $unique = $this->columnGroups($raw['unique'] ?? [], 'unique');
        $modelScopes = $this->renderScopes($raw['scopes'] ?? [], $fields);
        $hasMany = $this->renderSchemaRelations($raw['relations'] ?? [], $module, $entity, $relations, $modelImports);
        $primaryKey = $this->parsePrimaryKey($raw, $fields);

        return [$fields, new EntitySchemaMeta(
            softDeletes: $softDeletes,
            indexes: $indexes,
            unique: $unique,
            modelRelations: implode("\n", $relations),
            modelScopes: $modelScopes,
            enumFiles: $enumFiles,
            modelImports: array_values(array_unique(array_merge($modelImports, $primaryKey['imports']))),
            usesHasMany: $hasMany,
            hasQueryFilters: $hasQueryFilters,
            queryFilterLines: $queryFilterLines,
            primaryKeyMigrationLine: $primaryKey['migrationLine'],
            modelPrimaryKeyBlock: $primaryKey['modelBlock'],
            listLatestColumn: $primaryKey['listLatestColumn'],
            compositePrimaryKeyColumns: $primaryKey['compositeColumns'],
        )];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function mergeProfile(array $raw): array
    {
        $profile = $raw['profile'] ?? null;

        if ($profile === null || $profile === '') {
            return $raw;
        }

        if (! is_string($profile)) {
            throw SchemaException::invalid('profile must be a string');
        }

        $profilePath = $this->resolveProfilePath($profile);

        if (! is_file($profilePath)) {
            throw SchemaException::invalid("schema profile not found: {$profile}");
        }

        /** @var mixed $base */
        $base = Yaml::parseFile($profilePath);

        if (! is_array($base)) {
            throw SchemaException::invalid("schema profile {$profile} must be a mapping");
        }

        $baseFields = $base['fields'] ?? [];
        $entityFields = $raw['fields'] ?? [];

        if (! is_array($baseFields) || ! is_array($entityFields)) {
            throw SchemaException::invalid('profile and entity fields must be lists');
        }

        $raw['fields'] = [...$baseFields, ...$entityFields];
        unset($raw['profile']);

        foreach (['softDeletes', 'indexes', 'unique', 'id', 'primaryKey'] as $key) {
            if (! array_key_exists($key, $raw) && array_key_exists($key, $base)) {
                $raw[$key] = $base[$key];
            }
        }

        return $raw;
    }

    private function resolveProfilePath(string $profile): string
    {
        $name = trim($profile, '/');
        $candidates = [base_path('schemas/profiles/'.$name.'.yaml')];

        foreach ((array) config('blueprint.schema_paths', ['schemas']) as $root) {
            $root = (string) $root;

            if ($root === '') {
                continue;
            }

            $absolute = str_starts_with($root, DIRECTORY_SEPARATOR)
                || (strlen($root) > 1 && $root[1] === ':');

            $candidates[] = ($absolute ? rtrim($root, '/\\') : base_path(trim($root, '/'))).'/profiles/'.$name.'.yaml';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }

    /**
     * @param  list<CrudField>  $fields
     * @param  array<string, mixed>  $raw
     * @return list<CrudField>
     */
    private function applyEntityResourceHidden(array $fields, array $raw): array
    {
        $resource = $raw['resource'] ?? null;

        if ($resource === null) {
            return $fields;
        }

        if (! is_array($resource)) {
            throw SchemaException::invalid('resource must be a mapping');
        }

        $hidden = $resource['hidden'] ?? [];

        if (! is_array($hidden)) {
            throw SchemaException::invalid('resource.hidden must be a list of column names');
        }

        $columns = [];

        foreach ($hidden as $name) {
            if (! is_string($name) || $name === '') {
                throw SchemaException::invalid('resource.hidden entries must be non-empty strings');
            }

            $columns[] = Str::snake($name);
        }

        if ($columns === []) {
            return $fields;
        }

        return array_map(function (CrudField $field) use ($columns): CrudField {
            if (! in_array($field->column, $columns, true)) {
                return $field;
            }

            return new CrudField(
                property: $field->property,
                column: $field->column,
                type: $field->type,
                nullable: $field->nullable,
                import: $field->import,
                extendedKind: $field->extendedKind,
                referencesTable: $field->referencesTable,
                onDelete: $field->onDelete,
                relatedModelFqn: $field->relatedModelFqn,
                relatedModelShort: $field->relatedModelShort,
                decimalPrecision: $field->decimalPrecision,
                decimalScale: $field->decimalScale,
                enumCases: $field->enumCases,
                validationMin: $field->validationMin,
                validationMax: $field->validationMax,
                validationRegex: $field->validationRegex,
                columnUnique: $field->columnUnique,
                castOverride: $field->castOverride,
                schemaDefault: $field->schemaDefault,
                hasSchemaDefault: $field->hasSchemaDefault,
                resourceHidden: true,
                customRuleClasses: $field->customRuleClasses,
            );
        }, $fields);
    }

    /**
     * @param  list<CrudField>  $fields
     * @return array{0: string, 1: bool}
     */
    private function renderFilters(mixed $raw, array $fields): array
    {
        if ($raw === null || $raw === []) {
            return ['', false];
        }

        if (! is_array($raw)) {
            throw SchemaException::invalid('filters must be a list');
        }

        $columns = [];

        foreach ($fields as $field) {
            $columns[$field->column] = $field;
        }

        $lines = [];

        foreach ($raw as $index => $item) {
            if (! is_array($item)) {
                throw SchemaException::invalid('each filter must be a mapping (index '.$index.')');
            }

            $column = $item['column'] ?? null;

            if (! is_string($column) || $column === '') {
                throw SchemaException::invalid('filter index '.$index.': column is required');
            }

            $column = Str::snake($column);

            if (! isset($columns[$column])) {
                throw SchemaException::invalid("filter index {$index}: unknown column {$column}");
            }

            $query = $item['query'] ?? $column;

            if (! is_string($query) || $query === '') {
                throw SchemaException::invalid("filter index {$index}: query must be a non-empty string");
            }

            $type = $item['type'] ?? 'equals';

            if (! is_string($type)) {
                throw SchemaException::invalid("filter index {$index}: type must be a string");
            }

            $lines[] = match ($type) {
                'equals' => "        if (\$request->filled('{$query}')) {\n"
                    ."            \$query->where('{$column}', \$request->string('{$query}')->toString());\n"
                    ."        }\n",
                'like' => "        if (\$request->filled('{$query}')) {\n"
                    ."            \$query->where('{$column}', 'like', '%'.\$request->string('{$query}')->toString().'%');\n"
                    ."        }\n",
                'date_from' => "        if (\$request->filled('{$query}')) {\n"
                    ."            \$query->whereDate('{$column}', '>=', \$request->string('{$query}')->toString());\n"
                    ."        }\n",
                'date_to' => "        if (\$request->filled('{$query}')) {\n"
                    ."            \$query->whereDate('{$column}', '<=', \$request->string('{$query}')->toString());\n"
                    ."        }\n",
                'in' => "        if (\$request->has('{$query}')) {\n"
                    ."            \$values = array_values(array_filter((array) \$request->input('{$query}'), fn (\$v) => \$v !== null && \$v !== ''));\n"
                    ."            if (\$values !== []) {\n"
                    ."                \$query->whereIn('{$column}', \$values);\n"
                    ."            }\n"
                    ."        }\n",
                'between' => $this->renderBetweenFilter($item, $index, $column),
                default => throw SchemaException::invalid("filter index {$index}: unknown type {$type}"),
            };
        }

        return [implode("\n", $lines), true];
    }

    private function resolvePath(string $schemaPath): string
    {
        if (is_file($schemaPath)) {
            return $schemaPath;
        }

        $relative = base_path($schemaPath);

        if (is_file($relative)) {
            return $relative;
        }

        foreach ((array) config('blueprint.schema_paths', ['schemas']) as $root) {
            $candidate = base_path(trim((string) $root, '/').'/'.ltrim($schemaPath, '/'));

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $relative;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array{path: string, contents: string}>  $enumFiles
     * @param  list<string>  $modelImports
     * @param  list<string>  $relations
     */
    private function parseField(
        array $item,
        int $index,
        string $module,
        string $moduleNamespace,
        string $entity,
        array &$enumFiles,
        array &$modelImports,
        array &$relations,
    ): CrudField {
        $name = $item['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw SchemaException::invalid('field name is required (index '.$index.')');
        }

        $property = Str::camel($name);
        $column = Str::snake($name);
        $type = $item['type'] ?? 'string';

        if (! is_string($type)) {
            throw SchemaException::invalid("field {$name}: type must be a string");
        }

        $nullable = (bool) ($item['nullable'] ?? false);

        $field = match ($type) {
            'string', 'text', 'int', 'float', 'bool', 'array' => new CrudField(
                property: $property,
                column: $column,
                type: ($nullable ? '?' : '').$type,
                nullable: $nullable,
                import: null,
                extendedKind: $type === 'text' ? 'text' : null,
            ),
            'datetime', 'timestamp' => new CrudField(
                property: $property,
                column: $column,
                type: $nullable ? '?CarbonImmutable' : 'CarbonImmutable',
                nullable: $nullable,
                import: 'Carbon\\CarbonImmutable',
            ),
            'decimal' => new CrudField(
                property: $property,
                column: $column,
                type: ($nullable ? '?' : '').'float',
                nullable: $nullable,
                import: null,
                extendedKind: 'decimal',
                decimalPrecision: (int) ($item['precision'] ?? 12),
                decimalScale: (int) ($item['scale'] ?? 2),
            ),
            'foreignId' => $this->parseForeignId($item, $property, $column, $nullable, $module, $moduleNamespace, $modelImports, $relations),
            'enum' => $this->parseEnum($item, $property, $column, $nullable, $module, $moduleNamespace, $entity, $enumFiles, $modelImports),
            default => throw SchemaException::invalid("field {$name}: unknown type {$type}"),
        };

        return $this->applyFieldConstraints($field, $item, $name);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $modelImports
     * @param  list<string>  $relations
     */
    private function parseForeignId(
        array $item,
        string $property,
        string $column,
        bool $nullable,
        string $module,
        string $moduleNamespace,
        array &$modelImports,
        array &$relations,
    ): CrudField {
        $references = $item['references'] ?? null;

        if (! is_string($references) || $references === '') {
            throw SchemaException::invalid("field {$column}: foreignId requires references (table name)");
        }

        $related = $item['model'] ?? Str::studly(Str::singular($references));

        if (! is_string($related)) {
            throw SchemaException::invalid("field {$column}: model must be a string");
        }

        $relatedModule = $module;
        $relatedEntity = $related;

        if (str_contains($related, '.')) {
            [$relatedModule, $relatedEntity] = explode('.', $related, 2);
            $relatedModule = ModuleName::normalize($relatedModule) ?? $relatedModule;
            $relatedEntity = ModuleName::normalize($relatedEntity) ?? $relatedEntity;
        }

        if (PhpNames::isReserved($relatedEntity)) {
            throw SchemaException::invalid("field {$column}: invalid related model name");
        }

        $relatedFqn = "App\\Modules\\{$relatedModule}\\Models\\{$relatedEntity}";
        $modelImports[] = $relatedFqn;
        $relationMethod = Str::camel(Str::beforeLast($column, '_id') ?: Str::singular($references));
        $relations[] = "    public function {$relationMethod}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo\n"
            ."    {\n"
            ."        return \$this->belongsTo({$relatedEntity}::class);\n"
            ."    }\n";

        return new CrudField(
            property: $property,
            column: $column,
            type: $nullable ? '?int' : 'int',
            nullable: $nullable,
            import: null,
            extendedKind: 'foreignId',
            referencesTable: $references,
            onDelete: is_string($item['onDelete'] ?? null) ? $item['onDelete'] : 'restrict',
            relatedModelFqn: $relatedFqn,
            relatedModelShort: $relatedEntity,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<array{path: string, contents: string}>  $enumFiles
     * @param  list<string>  $modelImports
     */
    private function parseEnum(
        array $item,
        string $property,
        string $column,
        bool $nullable,
        string $module,
        string $moduleNamespace,
        string $entity,
        array &$enumFiles,
        array &$modelImports,
    ): CrudField {
        $cases = $item['cases'] ?? null;
        $enumName = $item['enum'] ?? Str::studly($entity).Str::studly($property);

        if (! is_string($enumName)) {
            throw SchemaException::invalid("field {$column}: enum name must be a string");
        }

        if (! is_array($cases) || $cases === []) {
            throw SchemaException::invalid("field {$column}: enum requires a non-empty cases list");
        }

        $caseStrings = [];

        foreach ($cases as $case) {
            if (! is_string($case) || $case === '') {
                throw SchemaException::invalid("field {$column}: each enum case must be a non-empty string");
            }

            $caseStrings[] = $case;
        }

        $enumFqn = "{$moduleNamespace}\\Enums\\{$enumName}";
        $modelImports[] = $enumFqn;
        $enumFiles[] = [
            'path' => 'app/'.str_replace('\\', '/', substr($moduleNamespace, strlen('App\\')))."/Enums/{$enumName}.php",
            'contents' => $this->renderEnum($enumFqn, $enumName, $caseStrings),
        ];

        return new CrudField(
            property: $property,
            column: $column,
            type: ($nullable ? '?' : '').$enumName,
            nullable: $nullable,
            import: $enumFqn,
            extendedKind: 'enum',
            enumCases: $caseStrings,
        );
    }

    /**
     * @param  list<string>  $cases
     */
    private function renderEnum(string $fqn, string $shortName, array $cases): string
    {
        $namespace = substr($fqn, 0, (int) strrpos($fqn, '\\'));
        $lines = array_map(function (string $case): string {
            $label = Str::studly($case);
            $value = addslashes($case);

            return "    case {$label} = '{$value}';";
        }, $cases);

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$namespace};\n\nenum {$shortName}: string\n{\n"
            .implode("\n", $lines)."\n}\n";
    }

    /**
     * @param  list<CrudField>  $fields
     */
    private function renderScopes(mixed $raw, array $fields): string
    {
        if ($raw === null || $raw === []) {
            return '';
        }

        if (! is_array($raw)) {
            throw SchemaException::invalid('scopes must be a list');
        }

        $columns = [];

        foreach ($fields as $field) {
            $columns[$field->column] = $field;
        }

        $blocks = [];

        foreach ($raw as $index => $entry) {
            if (! is_array($entry)) {
                throw SchemaException::invalid('each scope must be a mapping (index '.$index.')');
            }

            $name = $entry['name'] ?? null;
            $column = isset($entry['column']) ? Str::snake((string) $entry['column']) : null;
            $equals = $entry['equals'] ?? null;

            if (! is_string($name) || $name === '') {
                throw SchemaException::invalid('scope name is required (index '.$index.')');
            }

            if ($column === null || $column === '') {
                throw SchemaException::invalid("scope {$name}: column is required");
            }

            if (! is_string($equals) && ! is_int($equals) && ! is_float($equals)) {
                throw SchemaException::invalid("scope {$name}: equals is required");
            }

            if (! isset($columns[$column])) {
                throw SchemaException::invalid("scope {$name}: unknown column {$column}");
            }

            $value = $this->scopeEqualsLiteral($columns[$column], $equals);
            $method = Str::studly($name);
            $blocks[] = "    /**\n     * @param Builder<static> \$query\n     */\n"
                ."    public function scope{$method}(Builder \$query): void\n"
                ."    {\n"
                ."        \$query->where('{$column}', {$value});\n"
                ."    }\n";
        }

        return "\n".implode("\n", $blocks);
    }

    private function scopeEqualsLiteral(CrudField $field, string|int|float $equals): string
    {
        return match ($field->kind()) {
            'int', 'foreignId' => (string) (int) $equals,
            'float', 'decimal' => (string) (float) $equals,
            'bool' => ((string) $equals === '1' || $equals === 1 || (is_string($equals) && strtolower($equals) === 'true')) ? 'true' : 'false',
            default => "'".addslashes((string) $equals)."'",
        };
    }

    /**
     * @return list<list<string>>
     */
    private function columnGroups(mixed $raw, string $key): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        if (! is_array($raw)) {
            throw SchemaException::invalid("{$key} must be a list of column lists");
        }

        $groups = [];

        foreach ($raw as $entry) {
            if (is_string($entry)) {
                $groups[] = [Str::snake($entry)];

                continue;
            }

            if (! is_array($entry) || $entry === []) {
                throw SchemaException::invalid("{$key} entries must be column names or lists of columns");
            }

            $groups[] = array_map(fn ($c) => is_string($c) ? Str::snake($c) : throw SchemaException::invalid("{$key} column names must be strings"), $entry);
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function applyFieldConstraints(CrudField $field, array $item, string $fieldName): CrudField
    {
        $min = array_key_exists('min', $item) ? $this->requireInt($item['min'], "field {$fieldName}: min") : null;
        $max = array_key_exists('max', $item) ? $this->requireInt($item['max'], "field {$fieldName}: max") : null;
        $regex = null;

        if (array_key_exists('regex', $item)) {
            if (! is_string($item['regex']) || $item['regex'] === '') {
                throw SchemaException::invalid("field {$fieldName}: regex must be a non-empty string");
            }

            if (@preg_match('/'.$item['regex'].'/', '') === false) {
                throw SchemaException::invalid("field {$fieldName}: regex is not a valid pattern");
            }

            $regex = $item['regex'];
        }

        if ($min !== null && $max !== null && $min > $max) {
            throw SchemaException::invalid("field {$fieldName}: min cannot be greater than max");
        }

        if ($regex !== null && ! in_array($field->kind(), ['string', 'text'], true)) {
            throw SchemaException::invalid("field {$fieldName}: regex applies to string fields only");
        }

        if (($min !== null || $max !== null) && ! in_array($field->kind(), ['string', 'text', 'int', 'foreignId', 'float', 'decimal'], true)) {
            throw SchemaException::invalid("field {$fieldName}: min and max apply to string, int, float, and decimal fields only");
        }

        $columnUnique = (bool) ($item['unique'] ?? false);
        $resourceHidden = (bool) ($item['hidden'] ?? false);
        $customRuleClasses = $this->parseCustomRuleClasses($item['rules'] ?? null, $fieldName);
        $castOverride = null;

        if (array_key_exists('cast', $item)) {
            if (! is_string($item['cast']) || $item['cast'] === '') {
                throw SchemaException::invalid("field {$fieldName}: cast must be a non-empty string");
            }

            $allowed = ['array', 'collection', 'json', 'object'];

            if ($field->kind() !== 'array' || ! in_array($item['cast'], $allowed, true)) {
                throw SchemaException::invalid("field {$fieldName}: cast must be one of ".implode(', ', $allowed).' on array fields');
            }

            $castOverride = $item['cast'];
        }

        $hasSchemaDefault = array_key_exists('default', $item);
        $schemaDefault = $hasSchemaDefault ? $item['default'] : null;

        if ($hasSchemaDefault) {
            $this->assertDefaultMatchesField($field, $schemaDefault, $fieldName);
        }

        if ($field->columnUnique === $columnUnique
            && $field->validationMin === $min
            && $field->validationMax === $max
            && $field->validationRegex === $regex
            && $field->castOverride === $castOverride
            && $field->hasSchemaDefault === $hasSchemaDefault
            && $field->schemaDefault === $schemaDefault
            && $field->resourceHidden === $resourceHidden
            && $field->customRuleClasses === $customRuleClasses) {
            return $field;
        }

        return new CrudField(
            property: $field->property,
            column: $field->column,
            type: $field->type,
            nullable: $field->nullable,
            import: $field->import,
            extendedKind: $field->extendedKind,
            referencesTable: $field->referencesTable,
            onDelete: $field->onDelete,
            relatedModelFqn: $field->relatedModelFqn,
            relatedModelShort: $field->relatedModelShort,
            decimalPrecision: $field->decimalPrecision,
            decimalScale: $field->decimalScale,
            enumCases: $field->enumCases,
            validationMin: $min,
            validationMax: $max,
            validationRegex: $regex,
            columnUnique: $columnUnique,
            castOverride: $castOverride,
            schemaDefault: $schemaDefault,
            hasSchemaDefault: $hasSchemaDefault,
            resourceHidden: $resourceHidden,
            customRuleClasses: $customRuleClasses,
        );
    }

    /**
     * @return list<string>
     */
    private function parseCustomRuleClasses(mixed $raw, string $fieldName): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        if (! is_array($raw)) {
            throw SchemaException::invalid("field {$fieldName}: rules must be a list of class names");
        }

        $classes = [];

        foreach ($raw as $index => $entry) {
            if (! is_string($entry) || $entry === '') {
                throw SchemaException::invalid("field {$fieldName}: rules index {$index} must be a non-empty class name");
            }

            if (! preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $entry)) {
                throw SchemaException::invalid("field {$fieldName}: invalid rule class {$entry}");
            }

            $classes[] = ltrim($entry, '\\');
        }

        return $classes;
    }

    private function assertDefaultMatchesField(CrudField $field, mixed $default, string $fieldName): void
    {
        if ($default === null) {
            if (! $field->nullable) {
                throw SchemaException::invalid("field {$fieldName}: default null requires nullable: true");
            }

            return;
        }

        $valid = match ($field->kind()) {
            'string', 'text' => is_string($default),
            'int', 'foreignId' => is_int($default) || (is_string($default) && ctype_digit($default)),
            'float', 'decimal' => is_int($default) || is_float($default) || (is_string($default) && is_numeric($default)),
            'bool' => is_bool($default),
            'array' => is_array($default),
            'enum' => is_string($default) && in_array($default, $field->enumCases ?? [], true),
            default => false,
        };

        if (! $valid) {
            throw SchemaException::invalid("field {$fieldName}: default value does not match field type");
        }
    }

    /**
     * @param  list<string>  $relations
     * @param  list<string>  $modelImports
     */
    private function renderSchemaRelations(mixed $raw, string $module, string $entity, array &$relations, array &$modelImports): bool
    {
        if ($raw === null || $raw === []) {
            return false;
        }

        if (! is_array($raw)) {
            throw SchemaException::invalid('relations must be a list');
        }

        $usesHasMany = false;

        foreach ($raw as $index => $item) {
            if (! is_array($item)) {
                throw SchemaException::invalid('each relation must be a mapping (index '.$index.')');
            }

            $type = $item['type'] ?? null;

            if ($type !== 'hasMany') {
                throw SchemaException::invalid('relation index '.$index.': only hasMany is supported');
            }

            $name = $item['name'] ?? null;

            if (! is_string($name) || $name === '') {
                throw SchemaException::invalid('relation index '.$index.': name is required');
            }

            $method = Str::camel($name);
            $related = $item['model'] ?? null;

            if (! is_string($related) || $related === '') {
                throw SchemaException::invalid("relation {$name}: model is required");
            }

            [$relatedModule, $relatedEntity] = $this->resolveRelatedModel($related, $module);
            $relatedFqn = "App\\Modules\\{$relatedModule}\\Models\\{$relatedEntity}";
            $modelImports[] = $relatedFqn;

            $foreignKey = $item['foreignKey'] ?? Str::snake($entity).'_id';

            if (! is_string($foreignKey) || $foreignKey === '') {
                throw SchemaException::invalid("relation {$name}: foreignKey must be a non-empty string");
            }

            $relations[] = "    public function {$method}(): \\Illuminate\\Database\\Eloquent\\Relations\\HasMany\n"
                ."    {\n"
                ."        return \$this->hasMany({$relatedEntity}::class, '{$foreignKey}');\n"
                ."    }\n";
            $usesHasMany = true;
        }

        return $usesHasMany;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRelatedModel(string $related, string $module): array
    {
        $relatedModule = $module;
        $relatedEntity = $related;

        if (str_contains($related, '.')) {
            [$relatedModule, $relatedEntity] = explode('.', $related, 2);
            $relatedModule = ModuleName::normalize($relatedModule) ?? $relatedModule;
            $relatedEntity = ModuleName::normalize($relatedEntity) ?? $relatedEntity;
        } else {
            $relatedEntity = ModuleName::normalize($relatedEntity) ?? $relatedEntity;
        }

        if (PhpNames::isReserved($relatedEntity)) {
            throw SchemaException::invalid("relation model {$relatedEntity} is not allowed");
        }

        return [$relatedModule, $relatedEntity];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function renderBetweenFilter(array $item, int $index, string $column): string
    {
        $queryMin = $item['query_min'] ?? $column.'_min';
        $queryMax = $item['query_max'] ?? $column.'_max';

        if (! is_string($queryMin) || $queryMin === '' || ! is_string($queryMax) || $queryMax === '') {
            throw SchemaException::invalid("filter index {$index}: query_min and query_max must be non-empty strings");
        }

        return "        if (\$request->filled('{$queryMin}')) {\n"
            ."            \$query->where('{$column}', '>=', \$request->input('{$queryMin}'));\n"
            ."        }\n"
            ."        if (\$request->filled('{$queryMax}')) {\n"
            ."            \$query->where('{$column}', '<=', \$request->input('{$queryMax}'));\n"
            ."        }\n";
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<CrudField>  $fields
     * @return array{migrationLine: string, modelBlock: string, listLatestColumn: string, compositeColumns: list<string>, imports: list<string>}
     */
    private function parsePrimaryKey(array $raw, array $fields): array
    {
        $columnsByName = [];

        foreach ($fields as $field) {
            $columnsByName[$field->column] = true;
        }

        $idType = $raw['id'] ?? null;
        $primaryKey = $raw['primaryKey'] ?? null;

        if ($idType !== null && $primaryKey !== null) {
            throw SchemaException::invalid('use either id or primaryKey, not both');
        }

        if ($primaryKey !== null) {
            if (! is_array($primaryKey)) {
                throw SchemaException::invalid('primaryKey must be a mapping with columns');
            }

            $columns = $primaryKey['columns'] ?? null;

            if (! is_array($columns) || count($columns) < 2) {
                throw SchemaException::invalid('primaryKey.columns must be a list of at least two column names');
            }

            $snaked = [];

            foreach ($columns as $col) {
                if (! is_string($col) || $col === '') {
                    throw SchemaException::invalid('primaryKey.columns entries must be non-empty strings');
                }

                $snakedCol = Str::snake($col);

                if (! isset($columnsByName[$snakedCol])) {
                    throw SchemaException::invalid("primaryKey column {$snakedCol} is not in fields");
                }

                $snaked[] = $snakedCol;
            }

            $quoted = implode("', '", $snaked);
            $first = $snaked[0];

            return [
                'migrationLine' => '',
                'modelBlock' => "\n    public \$incrementing = false;\n\n"
                    ."    protected \$primaryKey = '{$first}';\n\n"
                    .'    // Composite primary key ('.implode(', ', $snaked).") — route binding uses {$first} only; see docs/SCHEMA-DSL.md.\n",
                'listLatestColumn' => 'created_at',
                'compositeColumns' => $snaked,
                'imports' => [],
            ];
        }

        if ($idType === null || $idType === 'incrementing' || $idType === 'bigint') {
            return [
                'migrationLine' => '            $table->id();',
                'modelBlock' => '',
                'listLatestColumn' => 'id',
                'compositeColumns' => [],
                'imports' => [],
            ];
        }

        if (! is_string($idType)) {
            throw SchemaException::invalid('id must be incrementing, uuid, or ulid');
        }

        $idType = strtolower(trim($idType));

        return match ($idType) {
            'uuid' => [
                'migrationLine' => '            $table->uuid(\'id\')->primary();',
                'modelBlock' => "\n    use HasUuids;\n",
                'listLatestColumn' => 'created_at',
                'compositeColumns' => [],
                'imports' => ['Illuminate\\Database\\Eloquent\\Concerns\\HasUuids'],
            ],
            'ulid' => [
                'migrationLine' => '            $table->ulid(\'id\')->primary();',
                'modelBlock' => "\n    use HasUlids;\n",
                'listLatestColumn' => 'created_at',
                'compositeColumns' => [],
                'imports' => ['Illuminate\\Database\\Eloquent\\Concerns\\HasUlids'],
            ],
            default => throw SchemaException::invalid('id must be incrementing, uuid, or ulid'),
        };
    }

    private function requireInt(mixed $value, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }

        throw SchemaException::invalid($context.' must be an integer');
    }
}
