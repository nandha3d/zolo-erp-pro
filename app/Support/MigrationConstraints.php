<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Resume constraints that MySQL commits separately from CREATE TABLE. */
final class MigrationConstraints
{
    public static function unique(string $table, string $name, array $columns, bool $primary = false): void
    {
        $existing = collect(Schema::getIndexes($table))->firstWhere('name', $name);
        if ($existing) {
            if ($existing['columns'] !== $columns || !$existing['unique'] || ($primary && !$existing['primary'])) {
                throw new RuntimeException('Unexpected existing index '.$table.'.'.$name.'.');
            }
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns, $primary) {
            $primary ? $blueprint->primary($columns) : $blueprint->unique($columns, $name);
        });
    }

    public static function foreign(string $table, string $name, array $columns, string $parent): void
    {
        $existing = collect(Schema::getForeignKeys($table))->first(fn ($key) => ($key['name'] ?? null) === $name
            || (($key['name'] ?? null) === null && $key['columns'] === $columns && $key['foreign_table'] === $parent));
        if ($existing) {
            if ($existing['columns'] !== $columns || $existing['foreign_table'] !== $parent
                || $existing['foreign_columns'] !== ['id'] || $existing['on_delete'] !== 'restrict') {
                throw new RuntimeException('Unexpected existing foreign key '.$table.'.'.$name.'.');
            }
            return;
        }
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($columns, $name)
            ->references('id')->on($parent)->restrictOnDelete());
    }
}
