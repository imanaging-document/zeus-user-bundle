<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class GrantStore
{
  public function __construct(private Connection $connection)
  {
  }

  public function issue(array $request, array $application, string $userId, string $browserIp): string
  {
    $code = Protocol::random();
    $this->connection->insert('sso_authorization_code', [
      'code_hash' => hash('sha256', $code), 'application_url' => $application['url'], 'user_id' => $userId,
      'callback' => $request['callback'], 'challenge' => $request['challenge'], 'correlation' => $request['correlation'],
      'browser_ip' => $browserIp, 'expires_at' => time() + Protocol::CODE_TTL, 'consumed_at' => null,
    ]);
    return $code;
  }

  public function consume(array $data, array $application): array
  {
    $code = $data['code'] ?? '';
    $verifier = $data['verifier'] ?? '';
    if (!is_string($code) || !preg_match('/^[a-f0-9]{64}$/D', $code)
      || !is_string($verifier) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $verifier)) {
      throw new AuthenticationException('Invalid SSO code.');
    }
    $hash = hash('sha256', $code);
    $grant = $this->connection->fetchAssociative('SELECT * FROM sso_authorization_code WHERE code_hash = ?', [$hash]);
    if (!$grant || $grant['consumed_at'] !== null || (int) $grant['expires_at'] <= time()
      || $grant['application_url'] !== $application['url'] || $grant['callback'] !== ($data['callback'] ?? '')
      || !hash_equals($grant['challenge'], Protocol::challenge($verifier))) {
      throw new AuthenticationException('Invalid, expired or consumed SSO code.');
    }
    $updated = $this->connection->executeStatement(
      'UPDATE sso_authorization_code SET consumed_at = ? WHERE code_hash = ? AND consumed_at IS NULL AND expires_at > ?',
      [time(), $hash, time()]
    );
    if ($updated !== 1) {
      throw new AuthenticationException('SSO code already consumed.');
    }
    return $grant;
  }
}
