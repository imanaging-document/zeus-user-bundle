<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Doctrine\DBAL\DriverManager;
use Imanaging\ZeusUserBundle\Sso\ApplicationRegistryInterface;
use Imanaging\ZeusUserBundle\Sso\AuditLog;
use Imanaging\ZeusUserBundle\Sso\Authority;
use Imanaging\ZeusUserBundle\Sso\Client;
use Imanaging\ZeusUserBundle\Sso\GrantStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;

final class ServicesTest extends TestCase
{
  public function testNewServicesCompileWithoutChangingLegacyAuthenticatorDefinitions(): void
  {
    $container = new ContainerBuilder();
    $container->setParameter('kernel.debug', false);
    $loader = new XmlFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/src/Resources/config'));
    $loader->load('services.xml');
    self::assertTrue($container->hasDefinition('imanaging_zeus_user.security.zeus_validation_sso_authenticator'));
    $wanted = [Client::class, GrantStore::class, AuditLog::class];
    foreach (array_keys($container->getDefinitions()) as $id) {
      if ($id !== 'service_container' && !in_array($id, $wanted, true)) {
        $container->removeDefinition($id);
      }
    }
    foreach (array_keys($container->getAliases()) as $id) {
      $container->removeAlias($id);
    }
    foreach ($wanted as $id) {
      $container->getDefinition($id)->setPublic(true);
    }
    $container->register('http_client', MockHttpClient::class);
    $container->register('logger', NullLogger::class);
    $container->setDefinition('doctrine.dbal.default_connection', (new Definition(\Doctrine\DBAL\Connection::class))
      ->setFactory([DriverManager::class, 'getConnection'])->setArguments([['driver' => 'pdo_sqlite', 'memory' => true]]));
    $container->register(ApplicationRegistryInterface::class)->setSynthetic(true)->setPublic(true);
    $container->register(Authority::class)->setPublic(true)->setArguments([
      new Reference(ApplicationRegistryInterface::class), new Reference(GrantStore::class), new Reference(AuditLog::class),
    ]);
    $container->compile();
    $container->set(ApplicationRegistryInterface::class, $this->createStub(ApplicationRegistryInterface::class));
    self::assertInstanceOf(Authority::class, $container->get(Authority::class));
    self::assertInstanceOf(Client::class, $container->get(Client::class));
  }
}
