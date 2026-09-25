<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

/**
 * Invalid input detected before any HTTP call is made.
 */
final class InvalidArgumentException extends \InvalidArgumentException implements AbuseIpDbException
{
}
