<?php

namespace Best\Blueprint\Support;

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
            'int' => ['integer'],
            'float' => ['numeric'],
            'bool' => ['boolean'],
            'array' => ['array'],
            'date' => ['date'],
        }];
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
            'array' => "(array) \$request->validated({$key})",
            'date' => "CarbonImmutable::parse(\$request->validated({$key}))",
        };

        return $this->nullable
            ? "\$request->validated({$key}) === null ? null : {$expression}"
            : $expression;
    }
}
