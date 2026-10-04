<?php

namespace App\Services\Inventory;

use InvalidArgumentException;

/** A posting rejected by stock policy or identity validation; nothing was written. */
class StockPolicyException extends InvalidArgumentException
{
}
