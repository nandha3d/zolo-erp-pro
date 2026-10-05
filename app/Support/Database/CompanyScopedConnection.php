<?php

namespace App\Support\Database;

use Illuminate\Database\MySqlConnection;

/**
 * MySQL connection whose query builder limits raw table reads and writes to the request company, so
 * DB::table() readers in reports, exports and services cannot cross company boundaries any more than Eloquent can.
 */
class CompanyScopedConnection extends MySqlConnection
{
    public function query()
    {
        return new CompanyScopedBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }
}
