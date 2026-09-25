<?php

declare(strict_types=1);

namespace AbuseIpDb\Exception;

/**
 * The API answered with a body that could not be decoded as expected.
 */
final class UnexpectedResponseException extends \RuntimeException implements AbuseIpDbException
{
}
