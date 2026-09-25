<?php

declare(strict_types=1);

use AbuseIpDb\AbuseIpDbClient;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(AbuseIpDbClient::class)
            ->args(['', null, null, null, []])
            ->public()
        ->alias('abuse_ip_db.client', AbuseIpDbClient::class)
            ->public();
};
