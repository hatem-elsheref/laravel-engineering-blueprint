<?php

namespace Best\Blueprint\Support;

use Illuminate\Support\Str;

/**
 * One make:crud field and the code fragments generated for it.
 */
final readonly class CrudField
{
    public function __construct(
        public string $property,
        public string $column,
        public string $type,
        public bool $nullable,
        public ?string $import,
    ) {}

    /** "string", "int", "float", "bool", "array" or "date". */
    public function kind(): string
    {
        return $this->import === null ? ltrim($this->type, '?') : 'date';
    }

    public function migration(): string
    {
        $column = match ($this->kind()) {
            'string' => "\$table->string('{$this->column}')",
            'int' => "\$table->integer('{$this->column}')",
            'float' => "\$table->decimal('{$this->column}', 12, 2)",
            'bool' => "\$table->boolean('{$this->column}')",
            'array' => "\$table->json('{$this->column}')",
            'date' => "\$table->timestamp('{$this->column}')",
            default => throw $this->unknownKind(),
        };

        return $this->nullable ? $column.'->nullable()' : $column;
    }

    public function cast(): ?string
    {
        return match ($this->kind()) {
            'int' => 'integer',
            'float' => 'float',
            'bool' => 'boolean',
            'array' => 'array',
            'date' => 'immutable_datetime',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        $presence = match (true) {
            $this->nullable => 'nullable',
            $this->kind() === 'array' => 'present',
            default => 'required',
        };

        return [$presence, ...match ($this->kind()) {
            'string' => ['string', 'max:255'],
            // Ranges match the migration columns (integer, decimal(12, 2)) so bad input is a 422, not a 500.
            'int' => ['integer', 'between:-2147483648,2147483647'],
            'float' => ['numeric', 'between:-9999999999.99,9999999999.99'],
            'bool' => ['boolean'],
            'array' => ['array', 'max:100'],
            'date' => ['date'],
            default => throw $this->unknownKind(),
        }];
    }

    /**
     * Rules for array elements: short strings only (no nested arrays, which would break the pages).
     *
     * @return array<string, list<string>>
     */
    public function elementRules(): array
    {
        return $this->kind() === 'array' ? ["{$this->column}.*" => ['nullable', 'string', 'max:255']] : [];
    }

    public function factory(): string
    {
        return match ($this->kind()) {
            'string' => 'fake()->words(3, true)',
            'int' => 'fake()->numberBetween(1, 100)',
            'float' => 'fake()->randomFloat(2, 1, 1000)',
            'bool' => 'fake()->boolean()',
            'array' => '[]',
            'date' => 'CarbonImmutable::now()',
            default => throw $this->unknownKind(),
        };
    }

    public function fromRequest(): string
    {
        $key = "'{$this->column}'";

        $expression = match ($this->kind()) {
            'string' => "\$request->string({$key})->toString()",
            'int' => "\$request->integer({$key})",
            'float' => "\$request->float({$key})",
            'bool' => "\$request->boolean({$key})",
            // Browser forms send one input per value; empty inputs arrive as null/'' and are dropped.
            'array' => "array_values(array_filter((array) \$request->validated({$key}), fn (\$value) => \$value !== null && \$value !== ''))",
            'date' => "CarbonImmutable::parse(\$request->validated({$key}))->setTimezone(config('app.timezone'))",
            default => throw $this->unknownKind(),
        };

        return $this->nullable
            ? "\$request->validated({$key}) === null ? null : {$expression}"
            : $expression;
    }

    /**
     * Blade label + input + error for the create/edit form; $variable is the model variable (no $).
     */
    public function formInput(string $variable): string
    {
        $col = $this->column;
        $label = Str::headline($col);
        $current = "\${$variable}->{$col}";

        $input = match ($this->kind()) {
            'string' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"text\" value=\"{{ old('{$col}', {$current}) }}\">",
            'int' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"number\" step=\"1\" value=\"{{ old('{$col}', {$current}) }}\">",
            'float' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"number\" step=\"any\" value=\"{{ old('{$col}', {$current}) }}\">",
            'bool' => "    <input type=\"hidden\" name=\"{$col}\" value=\"0\">\n"
                ."    <input id=\"{$col}\" name=\"{$col}\" type=\"checkbox\" value=\"1\" @checked(old('{$col}', {$current}))>",
            'date' => "    <input id=\"{$col}\" name=\"{$col}\" type=\"datetime-local\" value=\"{{ old('{$col}', {$current}?->format('Y-m-d\\TH:i')) }}\">",
            'array' => "    @foreach ([...(array) old('{$col}', {$current} ?? []), ''] as \$value)\n"
                ."        <input name=\"{$col}[]\" type=\"text\" value=\"{{ \$value }}\" aria-label=\"{$label}\">\n"
                .'    @endforeach',
            default => throw $this->unknownKind(),
        };

        $for = $this->kind() === 'array' ? '' : " for=\"{$col}\"";

        return "<div>\n"
            ."    <label{$for}>{$label}</label>\n"
            ."{$input}\n"
            ."    @error('{$col}') <p role=\"alert\">{{ \$message }}</p> @enderror\n"
            .'</div>';
    }

    /**
     * Blade expression showing the value on index/show pages (always escaped by {{ }}).
     */
    public function display(string $variable): string
    {
        $value = "\${$variable}->{$this->column}";

        return match ($this->kind()) {
            'bool' => "{{ {$value} ? 'Yes' : 'No' }}",
            'date' => "{{ {$value}?->format('Y-m-d H:i') }}",
            'array' => "{{ implode(', ', {$value} ?? []) }}",
            default => "{{ {$value} }}",
        };
    }

    private function unknownKind(): \LogicException
    {
        return new \LogicException("Unsupported field type {$this->type}.");
    }
}
