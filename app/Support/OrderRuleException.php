<?php

namespace App\Support;

use RuntimeException;

/** A staff action the order rules refuse (shown to the admin as a plain message, HTTP 422). */
class OrderRuleException extends RuntimeException
{
}
