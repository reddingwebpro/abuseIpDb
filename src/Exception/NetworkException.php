<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

/**
 * Wraps a PSR-18 transport failure (DNS, connection, timeout...).
 */
final class NetworkException extends \RuntimeException implements AbuseIpDbException
{
}
