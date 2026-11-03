<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

/**
 * One make:crud field and the code fragments generated for it.
 */
final readonly class CrudField
{
    /**
     * @param  list<string>|null  $enumCases
     */
    public function __construct(
        public string $property,
        public string $column,
        public string $type,
        public bool $nullable,
        public ?string $import,
        public ?string $extendedKind = null,
        public ?string $referencesTable = null,
        public ?string $onDelete = null,
        public ?string $relatedModelFqn = null,
        public ?string $relatedModelShort = null,
        public int $decimalPrecision = 12,
        public int $decimalScale = 2,
        public ?array $enumCases = null,
        public ?int $validationMin = null,
        public ?int $validationMax = null,
        public ?string $validationRegex = null,
        public bool $columnUnique = false,
        public ?string $castOverride = null,
        public mixed $schemaDefault = null,
        public bool $hasSchemaDefault = false,
        public bool $resourceHidden = false,
        /** @var list<string> FQCN validation rule classes */
        public array $customRuleClasses = [],
    ) {}

    /** @return string string|int|float|bool|array|date|text|decimal|foreignId|enum */
    public function kind(): string
    {
        if ($this->extendedKind !== null) {
            return $this->extendedKind;
        }

        if ($this->import !== null) {
            return str_contains($this->import, 'Carbon') ? 'date' : 'enum';
        }

        return ltrim($this->type, '?');
    }

    public function migration(): string
    {
        $column = match ($this->kind()) {
            'string' => "\$table->string('{$this->column}')",
            'text' => "\$table->text('{$this->column}')",
            'int' => "\$table->integer('{$this->column}')",
            'foreignId' => $this->foreignIdMigration(),
            'float' => "\$table->decimal('{$this->column}', 12, 2)",
            'decimal' => "\$table->decimal('{$this->column}', {$this->decimalPrecision}, {$this->decimalScale})",
            'bool' => "\$table->boolean('{$this->column}')",
            'array' => "\$table->json('{$this->column}')",
            'date' => "\$table->timestamp('{$this->column}')",
            'enum' => "\$table->string('{$this->column}', 64)",
            default => throw $this->unknownKind(),
        };

        if ($this->columnUnique) {
            $column .= '->unique()';
        }

        $default = $this->migrationDefaultSuffix();

        if ($default !== '') {
            $column .= $default;
        }

        return $this->nullable ? $column.'->nullable()' : $column;
    }

    private function migrationDefaultSuffix(): string
    {
        if (! $this->hasSchemaDefault || $this->schemaDefault === null) {
            return '';
        }

        return match ($this->kind()) {
            'string', 'text', 'enum' => '->default(\''.addslashes((string) $this->schemaDefault).'\')',
            'int', 'foreignId' => '->default('.(int) $this->schemaDefault.')',
            'float', 'decimal' => '->default('.(float) $this->schemaDefault.')',
            'bool' => '->default('.($this->schemaDefault ? 'true' : 'false').')',
            'array' => '->default(\''.addslashes(json_encode($this->schemaDefault, JSON_THROW_ON_ERROR)).'\')',
            default => '',
        };
    }

    private function foreignIdMigration(): string
    {
        if ($this->referencesTable === null) {
            $line = "\$table->unsignedBigInteger('{$this->column}')";

            return $this->nullable ? $line.'->nullable()' : $line;
        }

        $delete = $this->onDelete ?? 'restrict';
        $line = "\$table->foreignId('{$this->column}')";

        if ($this->nullable) {
            $line .= '->nullable()';
        }

        return $line."->constrained('{$this->referencesTable}')->onDelete('{$delete}')";
    }

    public function castLine(): ?string
    {
        $cast = $this->cast();

        if ($cast === null) {
            return null;
        }

        if ($this->kind() === 'enum') {
            return "'{$this->column}' => ".class_basename($this->import).'::class';
        }

        return "'{$this->column}' => '{$cast}'";
    }

    public function cast(): ?string
    {
        if ($this->castOverride !== null) {
            return $this->castOverride;
        }

        return match ($this->kind()) {
            'int', 'foreignId' => 'integer',
            'float', 'decimal' => 'float',
            'bool' => 'boolean',
            'array' => 'array',
            'date' => 'immutable_datetime',
            'enum' => $this->import,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function rules(bool $forUpdate = false, string $table = '', string $routeParameter = ''): array
    {
        $presence = match (true) {
            $this->nullable => 'nullable',
            $this->kind() === 'array' => 'present',
            default => 'required',
        };

        $rules = [$presence, ...match ($this->kind()) {
            'string', 'text' => ['string', 'max:'.$this->defaultStringMax()],
            'int', 'foreignId' => $this->integerRules(),
            'float', 'decimal' => $this->numericRules(),
            'bool' => ['boolean'],
            'array' => ['array', 'max:100'],
            'date' => ['date'],
            'enum' => ['string', 'Rule::enum('.class_basename($this->import).'::class)'],
            default => throw $this->unknownKind(),
        }];

        if ($this->validationMin !== null && in_array($this->kind(), ['string', 'text', 'int', 'foreignId', 'float', 'decimal'], true)) {
            $rules[] = 'min:'.$this->validationMin;
        }

        if ($this->validationMax !== null && in_array($this->kind(), ['int', 'foreignId', 'float', 'decimal'], true)) {
            $rules[] = 'max:'.$this->validationMax;
        }

        if ($this->validationRegex !== null && in_array($this->kind(), ['string', 'text'], true)) {
            $rules[] = 'regex:/'.str_replace('/', '\\/', $this->validationRegex).'/';
        }

        if ($this->columnUnique && $table !== '') {
            $rules[] = $forUpdate && $routeParameter !== ''
                ? "Rule::unique('{$table}', '{$this->column}')->ignore(\$this->route('{$routeParameter}'))"
                : "unique:{$table},{$this->column}";
        }

        foreach ($this->customRuleClasses as $class) {
            $rules[] = 'new \\'.ltrim($class, '\\').'()';
        }

        return $rules;
    }

    private function defaultStringMax(): int
    {
        if ($this->validationMax !== null) {
            return $this->validationMax;
        }

        return $this->kind() === 'text' ? 65535 : 255;
    }

    /**
     * @return list<string>
     */
    private function integerRules(): array
    {
        if ($this->validationMin !== null || $this->validationMax !== null) {
            return ['integer'];
        }

        return ['integer', 'between:-2147483648,2147483647'];
    }

    /**
     * @return list<string>
     */
    private function numericRules(): array
    {
        if ($this->validationMin !== null || $this->validationMax !== null) {
            return ['numeric'];
        }

        return ['numeric', 'between:-9999999999.99,9999999999.99'];
    }

    /**
     * @return array<string, list<string>>
     */
    public function elementRules(): array
    {
        return $this->kind() === 'array' ? ["{$this->column}.*" => ['nullable', 'string', 'max:255']] : [];
    }

    public function factory(): string
    {
        if ($this->hasSchemaDefault) {
            return $this->factoryDefaultLiteral();
        }

        return match ($this->kind()) {
            'string', 'text' => 'fake()->words(3, true)',
            'int', 'foreignId' => 'fake()->numberBetween(1, 100)',
            'float', 'decimal' => 'fake()->randomFloat(2, 1, 1000)',
            'bool' => 'fake()->boolean()',
            'array' => '[]',
            'date' => 'CarbonImmutable::now()',
            'enum' => class_basename($this->import).'::'.Str::studly($this->enumCases[0] ?? 'pending'),
            default => throw $this->unknownKind(),
        };
    }

    private function factoryDefaultLiteral(): string
    {
        if ($this->schemaDefault === null) {
            return 'null';
        }

        return match ($this->kind()) {
            'string', 'text' => "'".addslashes((string) $this->schemaDefault)."'",
            'int', 'foreignId' => (string) (int) $this->schemaDefault,
            'float', 'decimal' => (string) (float) $this->schemaDefault,
            'bool' => $this->schemaDefault ? 'true' : 'false',
            'array' => is_array($this->schemaDefault)
                ? json_encode(array_values($this->schemaDefault), JSON_THROW_ON_ERROR)
                : '[]',
            'enum' => class_basename($this->import).'::from(\''.addslashes((string) $this->schemaDefault).'\')',
            default => throw $this->unknownKind(),
        };
    }

    public function fromRequest(): string
    {
        $key = "'{$this->column}'";

        $expression = match ($this->kind()) {
            'string', 'text' => "\$request->string({$key})->toString()",
            'int', 'foreignId' => "\$request->integer({$key})",
            'float', 'decimal' => "\$request->float({$key})",
            'bool' => "\$request->boolean({$key})",
            'array' => "array_values(array_filter((array) \$request->validated({$key}), fn (\$value) => \$value !== null && \$value !== ''))",
            'date' => "CarbonImmutable::parse(\$request->validated({$key}))->setTimezone(config('app.timezone'))",
            'enum' => class_basename($this->import).'::from((string) \$request->string({$key}))',
            default => throw $this->unknownKind(),
        };

        return $this->nullable
            ? "\$request->validated({$key}) === null ? null : {$expression}"
            : $expression;
    }

    public function formInput(string $variable): string
    {
        $col = $this->column;
        $label = Str::headline($col);
        $current = "\${$variable}->{$col}";

        $input = match ($this->kind()) {
            'string', 'text' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"text\" value=\"{{ old('{$col}', {$current}) }}\">",
            'int', 'foreignId' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"number\" step=\"1\" value=\"{{ old('{$col}', {$current}) }}\">",
            'float', 'decimal' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"number\" step=\"any\" value=\"{{ old('{$col}', {$current}) }}\">",
            'bool' => "    <input type=\"hidden\" name=\"{$col}\" value=\"0\">\n"
                ."    <input id=\"{$col}\" name=\"{$col}\" type=\"checkbox\" value=\"1\" @checked(old('{$col}', {$current}))>",
            'date' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"datetime-local\" value=\"{{ old('{$col}', {$current}?->format('Y-m-d\\TH:i')) }}\">",
            'enum' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"text\" value=\"{{ old('{$col}', {$current}?->value ?? {$current}) }}\">",
            'array' => "    @foreach ([...(array) old('{$col}', {$current} ?? []), ''] as \$value)\n"
                ."        <input name=\"{$col}[]\" type=\"text\" value=\"{{ \$value }}\" aria-label=\"{{ __('{$label}') }}\">\n"
                .'    @endforeach',
            default => throw $this->unknownKind(),
        };

        $for = $this->kind() === 'array' ? '' : " for=\"{$col}\"";

        return "<div>\n"
            ."    <label{$for}>{{ __('{$label}') }}</label>\n"
            ."{$input}\n"
            ."    @error('{$col}') <p role=\"alert\">{{ \$message }}</p> @enderror\n"
            .'</div>';
    }

    public function display(string $variable): string
    {
        $value = "\${$variable}->{$this->column}";

        return match ($this->kind()) {
            'bool' => "{{ {$value} ? __('Yes') : __('No') }}",
            'date' => "{{ {$value}?->format('Y-m-d H:i') }}",
            'array' => "{{ implode(', ', {$value} ?? []) }}",
            'enum' => "{{ {$value}?->value ?? {$value} }}",
            default => "{{ {$value} }}",
        };
    }

    public function needsEnumImport(): bool
    {
        return $this->kind() === 'enum';
    }

    private function unknownKind(): \LogicException
    {
        return new \LogicException("Unsupported field type {$this->type}.");
    }
}
