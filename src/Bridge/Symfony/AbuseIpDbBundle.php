<?php

declare(strict_types=1);

namespace AbuseIpDb\Bridge\Symfony;

use AbuseIpDb\Bridge\Symfony\DependencyInjection\AbuseIpDbExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Optional Symfony integration; only loaded when registered in a kernel.
 */
final class AbuseIpDbBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new AbuseIpDbExtension();
    }
}
