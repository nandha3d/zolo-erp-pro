<?php

namespace App\Support\Database;

use Illuminate\Database\SQLiteConnection;

class CompanyScopedSqliteConnection extends SQLiteConnection
{
    public function query()
    {
        return new CompanyScopedBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
