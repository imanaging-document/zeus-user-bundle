<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Imanaging\ZeusUserBundle\Sso\ApplicationRegistryInterface;
use Imanaging\ZeusUserBundle\Sso\AuditLog;
use Imanaging\ZeusUserBundle\Sso\Authority;
use Imanaging\ZeusUserBundle\Sso\Client;
use Imanaging\ZeusUserBundle\Sso\GrantStore;
use Imanaging\ZeusUserBundle\Sso\Protocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class FlowTest extends TestCase
{
  private Connection $db;
  private Authority $authority;
  private AuditLog $audit;
  private bool $allowed = true;
  private array $app;

  protected function setUp(): void
  {
    $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $this->db->executeStatement('CREATE TABLE sso_authorization_code (
      code_hash TEXT PRIMARY KEY, application_url TEXT, user_id TEXT, callback TEXT, challenge TEXT,
      correlation TEXT, browser_ip TEXT, expires_at INTEGER, consumed_at INTEGER)');
    $this->db->executeStatement('CREATE TABLE sso_connection_event (
      id INTEGER PRIMARY KEY AUTOINCREMENT, occurred_at TEXT, protocol INTEGER, event TEXT,
      application_url TEXT, user_id TEXT, correlation TEXT, address_ip TEXT)');
    $this->app = ['url' => 'https://satellite.example', 'secret' => str_repeat('s', 32)];
    $registry = $this->createStub(ApplicationRegistryInterface::class);
    $registry->method('find')->willReturnCallback(function (string $url): array {
      if ($url !== $this->app['url']) {
        throw new AuthenticationException('Unknown application');
      }
      return $this->app;
    });
    $registry->method('identity')->willReturnCallback(function (string $userId): array {
      if (!$this->allowed) {
        throw new AuthenticationException('Revoked access');
      }
      return ['subject' => $userId, 'login' => 'internal-user', 'source' => 'zeus'];
    });
    $this->audit = new AuditLog($this->db, new NullLogger());
    $this->authority = new Authority($registry, new GrantStore($this->db), $this->audit);
  }

  private function parse(string $url): array
  {
    parse_str(parse_url($url, PHP_URL_QUERY), $data);
    return $data;
  }

  private function request(): array
  {
    $data = [
      'protocol' => '2', 'application_url' => $this->app['url'],
      'callback' => $this->app['url'] . '/administration/sso-login/return-validation/v2',
      'state' => Protocol::random(), 'correlation' => Protocol::random(), 'challenge' => Protocol::challenge(str_repeat('v', 64)),
      'issued_at' => (string) time(),
    ];
    $data['signature'] = Protocol::sign($data, $this->app['secret'], 'authorize');
    return $data;
  }

  private function exchange(array $response, array $request): array
  {
    $data = [
      'protocol' => '2', 'application_url' => $this->app['url'], 'callback' => $request['callback'],
      'code' => $response['code'], 'verifier' => str_repeat('v', 64), 'issued_at' => (string) time(),
    ];
    $data['signature'] = Protocol::sign($data, $this->app['secret'], 'exchange');
    return $data;
  }

  public function testFullServerExchangeAndReplayRejection(): void
  {
    $request = $this->request();
    $response = $this->parse($this->authority->authorize($request, '42', '192.0.2.1'));
    Protocol::verify($response, $this->app['secret'], 'callback');
    $exchange = $this->exchange($response, $request);
    self::assertSame('42', $this->authority->exchange($exchange, '192.0.2.2')['subject']);
    self::assertNotSame($response['code'], $this->db->fetchOne('SELECT code_hash FROM sso_authorization_code'));
    $this->expectException(AuthenticationException::class);
    $this->authority->exchange($exchange, '192.0.2.2');
  }

  #[DataProvider('invalidExchanges')]
  public function testRejectsInvalidExchange(string $case): void
  {
    $request = $this->request();
    $response = $this->parse($this->authority->authorize($request, '42', '192.0.2.1'));
    $exchange = $this->exchange($response, $request);
    if ($case === 'expired') {
      $this->db->executeStatement('UPDATE sso_authorization_code SET expires_at = ?', [time() - 1]);
    } elseif ($case === 'revoked') {
      $this->allowed = false;
    } else {
      $exchange[$case] = match ($case) {
        'verifier' => str_repeat('x', 64), 'callback' => 'https://attacker.example',
        'application_url' => 'https://other.example', 'code' => Protocol::random(),
      };
      $exchange['signature'] = Protocol::sign($exchange, $this->app['secret'], 'exchange');
    }
    $this->expectException(AuthenticationException::class);
    $this->authority->exchange($exchange, '192.0.2.2');
  }

  public static function invalidExchanges(): array
  {
    return [['expired'], ['revoked'], ['verifier'], ['callback'], ['application_url'], ['code']];
  }

  public function testLostConcurrentConsumptionIsRejected(): void
  {
    $request = $this->request();
    $response = $this->parse($this->authority->authorize($request, '42', '192.0.2.1'));
    $grant = $this->db->fetchAssociative('SELECT * FROM sso_authorization_code');
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn($grant);
    $connection->method('executeStatement')->willReturn(0);
    $store = new GrantStore($connection);
    $this->expectException(AuthenticationException::class);
    $store->consume($this->exchange($response, $request), $this->app);
  }

  public function testSignedNegativeResponseAndAccessDenied(): void
  {
    $request = $this->request();
    $response = $this->parse($this->authority->authorize($request, null, '192.0.2.1'));
    Protocol::verify($response, $this->app['secret'], 'callback');
    self::assertSame('no_session', $response['error']);
    $this->allowed = false;
    $response = $this->parse($this->authority->authorize($request, '42', '192.0.2.1'));
    self::assertSame('access_denied', $response['error']);
    self::assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM sso_authorization_code'));
  }

  public function testClientAndAuthorityRoundTrip(): void
  {
    $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
      self::assertSame('POST', $method);
      self::assertSame('https://zeus.example/api/sso/v2/exchange', $url);
      self::assertSame(0, $options['max_redirects']);
      parse_str($options['body'], $body);
      $identity = $this->authority->exchange($body, '192.0.2.2');
      return new MockResponse(json_encode($identity, JSON_THROW_ON_ERROR));
    });
    $client = new Client($http);
    $session = new Session(new MockArraySessionStorage());
    $url = $client->begin($session, 'https://zeus.example', $this->app['url'], $this->app['secret'],
      $this->app['url'] . '/administration/sso-login/return-validation/v2', '/documents');
    $response = $this->parse($this->authority->authorize($this->parse($url), '42', '192.0.2.1'));
    $result = $client->complete($session, $response, $this->app['secret']);
    self::assertSame('authenticated', $result['status']);
    self::assertSame('42', $result['identity']['subject']);
    self::assertSame('/documents', $result['target']);
    $this->expectException(AuthenticationException::class);
    $client->complete($session, $response, $this->app['secret']);
  }

  #[DataProvider('forgedCallbacks')]
  public function testClientRejectsUnprovenCallbacks(string $case): void
  {
    $client = new Client(new MockHttpClient());
    $session = new Session(new MockArraySessionStorage());
    $url = $client->begin($session, 'https://zeus.example', $this->app['url'], $this->app['secret'],
      $this->app['url'] . '/administration/sso-login/return-validation/v2');
    $response = $this->parse($this->authority->authorize($this->parse($url), null, '192.0.2.1'));
    if ($case === 'legacy') {
      $response = ['login' => 'admin'];
    } elseif ($case === 'state') {
      $response['state'] = Protocol::random();
    } elseif ($case === 'error') {
      $response['error'] = 'different_error';
    } elseif ($case === 'expired') {
      $pending = $session->get('_hephaistos_sso_v2');
      $pending[$response['state']]['expires'] = time() - 1;
      $session->set('_hephaistos_sso_v2', $pending);
    } else {
      $response['login'] = 'admin';
      $response['signature'] = Protocol::sign($response, $this->app['secret'], 'callback');
    }
    $this->expectException(AuthenticationException::class);
    $client->complete($session, $response, $this->app['secret']);
  }

  public static function forgedCallbacks(): array
  {
    return [['legacy'], ['state'], ['error'], ['expired'], ['extra field']];
  }

  public function testAuditNeverStoresProofMaterialAndFiltersProtocols(): void
  {
    $this->audit->record(1, 'legacy_identity_returned', '192.0.2.1', ['signature' => 'secret', 'code' => 'secret']);
    $this->audit->record(2, 'request_received', '192.0.2.1', ['verifier' => 'secret', 'state' => 'secret']);
    $rows = $this->audit->search(2, '', '', 1);
    self::assertCount(1, $rows);
    self::assertStringNotContainsString('secret', json_encode($rows, JSON_THROW_ON_ERROR));
  }

  public function testPurgeOnlyDeletesOldEventsAndExpiredCodes(): void
  {
    $this->audit->record(2, 'request_received', '192.0.2.1');
    $this->db->executeStatement('UPDATE sso_connection_event SET occurred_at = ?', ['2020-01-01 00:00:00']);
    $this->audit->record(2, 'request_received', '192.0.2.1');
    self::assertSame(1, $this->audit->purge(90));
    self::assertCount(1, $this->audit->search(0, '', '', 1));
  }
}
