<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Bridge\Symfony;

use AbuseIpDb\AbuseIpDbClient;
use AbuseIpDb\Bridge\Symfony\AbuseIpDbBundle;
use AbuseIpDb\Bridge\Symfony\DependencyInjection\AbuseIpDbExtension;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class AbuseIpDbBundleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(ContainerBuilder::class) || !class_exists(\Symfony\Component\HttpKernel\Bundle\Bundle::class)) {
            self::markTestSkipped('Symfony DI / HttpKernel not installed.');
        }
    }

    public function testBundleExposesExtension(): void
    {
        self::assertInstanceOf(AbuseIpDbExtension::class, (new AbuseIpDbBundle())->getContainerExtension());
    }

    public function testContainerCompilesAndUsesConfig(): void
    {
        $mock = new MockClient();
        $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], (string) file_get_contents(__DIR__ . '/../../../Fixtures/check.json')));

        $container = $this->buildContainer([
            'api_key' => 'secret',
            'default_max_age_in_days' => 30,
            'http_client' => 'test.http_client',
        ], $mock);

        $client = $container->get(AbuseIpDbClient::class);
        self::assertInstanceOf(AbuseIpDbClient::class, $client);
        self::assertSame($client, $container->get('abuse_ip_db.client'));

        $client->check('127.0.0.1');
        $request = $mock->getLastRequest();
        self::assertSame('secret', $request->getHeaderLine('Key'));
        self::assertStringContainsString('maxAgeInDays=30', $request->getUri()->getQuery());
    }

    public function testMissingApiKeyFails(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->buildContainer([], new MockClient());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config, MockClient $mock): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('test.http_client', (new Definition(MockClient::class))->setSynthetic(true)->setPublic(true));
        (new AbuseIpDbExtension())->load([$config], $container);
        $factory = new Definition(Psr17Factory::class);
        $container->getDefinition(AbuseIpDbClient::class)->replaceArgument(2, $factory)->replaceArgument(3, $factory);
        $container->compile();
        $container->set('test.http_client', $mock);

        return $container;
    }
}
