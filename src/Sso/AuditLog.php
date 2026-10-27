<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class AuditLog
{
  public function __construct(private Connection $connection, private LoggerInterface $logger)
  {
  }

  public function record(int $protocol, string $event, string $ip, array $context = []): void
  {
    // Explicit allowlist: never persist URLs with queries, codes, state, PKCE or credentials.
    $row = [
      'occurred_at' => gmdate('Y-m-d H:i:s'), 'protocol' => $protocol, 'event' => substr($event, 0, 64),
      'address_ip' => substr($ip, 0, 45), 'application_url' => substr((string) ($context['application_url'] ?? ''), 0, 512),
      'user_id' => substr((string) ($context['user_id'] ?? ''), 0, 64),
      'correlation' => preg_match('/^[a-f0-9]{64}$/D', (string) ($context['correlation'] ?? '')) ? $context['correlation'] : null,
    ];
    try {
      $this->connection->insert('sso_connection_event', $row);
    } catch (\Doctrine\DBAL\Exception $exception) {
      // Existing satellites remain usable before the history migration has been applied.
      $this->logger->error('SSO history write failed.', ['exception_class' => get_class($exception), 'event' => $event]);
    }
  }

  public function search(int $protocol, string $event, string $application, int $page): array
  {
    $query = $this->connection->createQueryBuilder()->select('*')->from('sso_connection_event');
    foreach (['protocol' => $protocol, 'event' => $event, 'application_url' => $application] as $key => $value) {
      if ($value !== '' && $value !== 0) {
        $query->andWhere($key . ' = :' . $key)->setParameter($key, $value);
      }
    }
    return $query->orderBy('id', 'DESC')->setMaxResults(101)->setFirstResult((max(1, $page) - 1) * 100)
      ->executeQuery()->fetchAllAssociative();
  }

  public function purge(int $days = 90): int
  {
    if ($days < 1) {
      throw new \InvalidArgumentException('Retention must be positive.');
    }
    $this->connection->executeStatement('DELETE FROM sso_authorization_code WHERE expires_at < ?', [time() - 3600]);
    return $this->connection->executeStatement(
      'DELETE FROM sso_connection_event WHERE occurred_at < ?', [gmdate('Y-m-d H:i:s', time() - $days * 86400)]
    );
  }
}
