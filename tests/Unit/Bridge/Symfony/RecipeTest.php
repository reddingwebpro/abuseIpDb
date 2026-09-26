<?php

declare(strict_types=1);

namespace AbuseIpDb\Tests\Unit\Bridge\Symfony;

use AbuseIpDb\AbuseIpDbClient;
use AbuseIpDb\Bridge\Symfony\AbuseIpDbBundle;
use AbuseIpDb\Bridge\Symfony\DependencyInjection\AbuseIpDbExtension;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;

final class RecipeTest extends TestCase
{
    private const RECIPE_DIR = __DIR__ . '/../../../../recipes/reddingwebdev/abuseipdb/1.0';

    protected function setUp(): void
    {
        if (!class_exists(ContainerBuilder::class) || !class_exists(Yaml::class)) {
            self::markTestSkipped('Symfony DI / Yaml not installed.');
        }
    }

    public function testManifestStructureAndContent(): void
    {
        $manifestPath = self::RECIPE_DIR . '/manifest.json';
        self::assertFileExists($manifestPath);

        $json = (string) file_get_contents($manifestPath);
        $data = json_decode($json, true);
        self::assertIsArray($data, 'manifest.json must be valid JSON');

        self::assertArrayHasKey('bundles', $data);
        self::assertSame(['all'], $data['bundles'][AbuseIpDbBundle::class] ?? null);

        self::assertArrayHasKey('copy-from-recipe', $data);
        self::assertIsArray($data['copy-from-recipe']);
        foreach (array_keys($data['copy-from-recipe']) as $source) {
            $sourcePath = self::RECIPE_DIR . '/' . rtrim((string) $source, '/');
            self::assertTrue(file_exists($sourcePath), sprintf('Referenced source path "%s" must exist in recipe directory.', $source));
        }

        self::assertArrayHasKey('env', $data);
        self::assertArrayHasKey('ABUSEIPDB_API_KEY', $data['env']);

        self::assertArrayHasKey('post-install-output', $data);
        self::assertIsArray($data['post-install-output']);
        self::assertNotEmpty($data['post-install-output']);
    }

    public function testPostInstallTextFileExists(): void
    {
        $postInstallPath = self::RECIPE_DIR . '/post-install.txt';
        self::assertFileExists($postInstallPath);
        self::assertNotEmpty(file_get_contents($postInstallPath));
    }

    public function testRecipeConfigFileParsesAndCompilesContainer(): void
    {
        $configPath = self::RECIPE_DIR . '/config/packages/abuse_ip_db.yaml';
        self::assertFileExists($configPath);

        $parsed = Yaml::parseFile($configPath);
        self::assertIsArray($parsed);
        self::assertArrayHasKey('abuse_ip_db', $parsed);
        self::assertIsArray($parsed['abuse_ip_db']);

        // Test container compilation with recipe config
        $container = new ContainerBuilder();
        $container->setParameter('env(ABUSEIPDB_API_KEY)', 'dummy-api-key');

        $mockClient = new MockClient();
        $container->setDefinition('test.http_client', (new Definition(MockClient::class))->setSynthetic(true)->setPublic(true));

        $extension = new AbuseIpDbExtension();
        $extension->load([$parsed['abuse_ip_db']], $container);

        $factory = new Definition(Psr17Factory::class);
        $container->getDefinition(AbuseIpDbClient::class)
            ->replaceArgument(2, $factory)
            ->replaceArgument(3, $factory);

        $container->compile();

        self::assertTrue($container->has(AbuseIpDbClient::class));
        self::assertTrue($container->has('abuse_ip_db.client'));
        self::assertTrue($container->hasParameter('abuse_ip_db.api_key'));
    }
}
