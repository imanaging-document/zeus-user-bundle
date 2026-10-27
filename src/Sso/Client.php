<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Client
{
  private const SESSION_KEY = '_hephaistos_sso_v2';

  public function __construct(private HttpClientInterface $http, private bool $allowLocalHttp = false)
  {
  }

  public function begin(
    SessionInterface $session,
    string $authorityUrl,
    string $applicationUrl,
    string $secret,
    string $callback,
    string $target = '/',
    ?string $correlation = null,
    string $source = 'zeus',
    bool $interactive = false
  ): string
  {
    $applicationUrl = Protocol::applicationUrl($applicationUrl, $this->allowLocalHttp);
    $authorityUrl = Protocol::applicationUrl($authorityUrl, $this->allowLocalHttp);
    if (!in_array($source, ['zeus', 'core'], true)) {
      throw new \InvalidArgumentException('Unknown SSO source.');
    }
    $state = Protocol::random();
    $verifier = Protocol::random();
    $data = [
      'protocol' => '2', 'application_url' => $applicationUrl, 'callback' => $callback,
      'state' => $state, 'challenge' => Protocol::challenge($verifier), 'correlation' => $correlation ?? Protocol::random(),
      'issued_at' => (string) time(),
    ];
    $data['signature'] = Protocol::sign($data, $secret, 'authorize');
    Protocol::validateAuthorization($data, $this->allowLocalHttp);
    $pending = array_filter($session->get(self::SESSION_KEY, []), static fn(array $entry): bool => $entry['expires'] > time());
    if (count($pending) >= 10) {
      array_shift($pending);
    }
    $pending[$state] = [
      'authority' => $authorityUrl, 'application_url' => $applicationUrl, 'callback' => $callback, 'verifier' => $verifier,
      'source' => $source, 'target' => Protocol::localTarget($target), 'expires' => time() + Protocol::REQUEST_TTL, 'correlation' => $data['correlation'],
    ];
    $session->set(self::SESSION_KEY, $pending);
    $path = $interactive ? '/sso-login/v2/authorize' : '/sso-login/v2/check';
    return $authorityUrl . $path . '?' . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
  }

  public function complete(SessionInterface $session, array $response, string $secret): array
  {
    $state = $response['state'] ?? '';
    $pending = $session->get(self::SESSION_KEY, []);
    $entry = is_string($state) ? ($pending[$state] ?? null) : null;
    if ($entry === null || $entry['expires'] <= time() || ($response['protocol'] ?? '') !== '2') {
      throw new AuthenticationException('Unsolicited or expired SSO callback.');
    }
    Protocol::verify($response, $secret, 'callback');
    $expected = ['issued_at', 'protocol', 'signature', 'state', isset($response['error']) ? 'error' : 'code'];
    $keys = array_keys($response);
    sort($expected);
    sort($keys);
    if ($keys !== $expected) {
      throw new AuthenticationException('Invalid SSO callback.');
    }
    unset($pending[$state]);
    $session->set(self::SESSION_KEY, $pending);
    if (isset($response['error'])) {
      if ($response['error'] !== 'no_session') {
        throw new AuthenticationException('SSO access denied.');
      }
      return [
        'status' => 'no_session', 'target' => $entry['target'], 'correlation' => $entry['correlation'],
        'authority' => $entry['authority'], 'source' => $entry['source'],
      ];
    }
    $data = [
      'protocol' => '2', 'application_url' => $entry['application_url'], 'callback' => $entry['callback'],
      'code' => $response['code'], 'verifier' => $entry['verifier'], 'issued_at' => (string) time(),
    ];
    $data['signature'] = Protocol::sign($data, $secret, 'exchange');
    try {
      $httpResponse = $this->http->request('POST', $entry['authority'] . '/api/sso/v2/exchange', [
        'body' => $data, 'timeout' => 5, 'max_duration' => 10, 'max_redirects' => 0,
      ]);
      if ($httpResponse->getStatusCode() !== 200) {
        throw new AuthenticationException('SSO exchange refused.');
      }
      $identity = $httpResponse->toArray(false);
    } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $exception) {
      throw new AuthenticationException('SSO authority unavailable.', 0, $exception);
    }
    if (!is_string($identity['subject'] ?? null) || $identity['subject'] === ''
      || !is_string($identity['login'] ?? null) || $identity['login'] === ''
      || ($identity['source'] ?? null) !== $entry['source']) {
      throw new AuthenticationException('Invalid SSO identity.');
    }
    return ['status' => 'authenticated', 'identity' => $identity, 'target' => $entry['target'], 'authority' => $entry['authority']];
  }
}
