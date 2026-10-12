<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;

/**
 * Column listings for the company / branch scoping that runs on almost every query. Without it one page asked
 * information_schema 30-90 times. The listing is kept for the life of the application instance and dropped on any
 * DDL statement (see AppServiceProvider), so migrations and tests that create tables never see a stale answer.
 */
final class SchemaCache
{
    /** @var array<string, list<string>> "connection.table" => lower-case column names ([] = no such table) */
    private array $columns = [];

    public function hasColumn(Connection $connection, string $table, string $column): bool
    {
        return in_array(strtolower($column), $this->columns($connection, $table), true);
    }

    public function hasTable(Connection $connection, string $table): bool
    {
        return $this->columns($connection, $table) !== [];
    }

    public function flush(): void
    {
        $this->columns = [];
    }

    private function columns(Connection $connection, string $table): array
    {
        return $this->columns[$connection->getName().'.'.$table]
            ??= array_map('strtolower', $connection->getSchemaBuilder()->getColumnListing($table));
    }
}
