<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Imanaging\ZeusUserBundle\Sso\Entity\SsoAuthorizationCode;
use Imanaging\ZeusUserBundle\Sso\Entity\SsoConnectionEvent;
use Imanaging\ZeusUserBundle\Sso\AuditLog;
use Imanaging\ZeusUserBundle\Sso\GrantStore;
use Imanaging\ZeusUserBundle\Sso\Protocol;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SchemaTest extends TestCase
{
  public function testDoctrineGeneratedTablesSupportDbalCodeAndHistoryStorage(): void
  {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $configuration = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Sso/Entity'], true);
    $em = new EntityManager($db, $configuration);
    $metadata = [$em->getClassMetadata(SsoAuthorizationCode::class), $em->getClassMetadata(SsoConnectionEvent::class)];
    $schema = new SchemaTool($em);
    $schema->createSchema($metadata);
    $request = [
      'callback' => 'https://satellite.example/sso-login/return-validation/v2',
      'challenge' => Protocol::challenge(str_repeat('v', 64)), 'correlation' => Protocol::random(),
    ];
    $store = new GrantStore($db);
    $app = ['url' => 'https://satellite.example'];
    $code = $store->issue($request, $app, '42', '192.0.2.1');
    $grant = $store->consume(['code' => $code, 'verifier' => str_repeat('v', 64), 'callback' => $request['callback']], $app);
    self::assertSame('42', $grant['user_id']);
    $audit = new AuditLog($db, new NullLogger());
    $audit->record(2, 'exchange_accepted', '192.0.2.1', ['application_url' => $app['url']]);
    self::assertCount(1, $audit->search(2, '', '', 1));
    self::assertSame([], $schema->getUpdateSchemaSql($metadata));
  }
}
