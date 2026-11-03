<?php

declare(strict_types=1);

namespace Best\Blueprint\Schema;

/**
 * Entity-level options from a schema file (not individual columns).
 */
final readonly class EntitySchemaMeta
{
    /**
     * @param  list<list<string>>  $indexes
     * @param  list<list<string>>  $unique
     * @param  list<array{path: string, contents: string}>  $enumFiles  base-relative path => PHP source
     * @param  list<string>  $modelImports
     * @param  list<string>  $compositePrimaryKeyColumns
     */
    public function __construct(
        public bool $softDeletes = false,
        public array $indexes = [],
        public array $unique = [],
        public string $modelRelations = '',
        public string $modelScopes = '',
        public array $enumFiles = [],
        public array $modelImports = [],
        public bool $usesHasMany = false,
        public bool $hasQueryFilters = false,
        public string $queryFilterLines = '',
        public string $primaryKeyMigrationLine = '            $table->id();',
        public string $modelPrimaryKeyBlock = '',
        public string $listLatestColumn = 'id',
        public array $compositePrimaryKeyColumns = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
