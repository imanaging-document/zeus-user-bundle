<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class Authority
{
  public function __construct(
    private ApplicationRegistryInterface $registry,
    private GrantStore $grants,
    private AuditLog $audit,
    private bool $allowLocalHttp = false
  )
  {
  }

  public function authorize(array $data, ?string $userId, string $browserIp): string
  {
    try {
      Protocol::validateAuthorization($data, $this->allowLocalHttp);
      $application = $this->registry->find($data['application_url']);
      Protocol::verify($data, $application['secret'], 'authorize');
    } catch (AuthenticationException $exception) {
      $this->audit->record(2, 'request_rejected', $browserIp);
      throw $exception;
    }
    $context = ['application_url' => $application['url'], 'correlation' => $data['correlation'], 'user_id' => $userId ?? ''];
    $this->audit->record(2, 'request_received', $browserIp, $context);
    $response = ['state' => $data['state'], 'issued_at' => (string) time(), 'protocol' => '2'];
    if ($userId === null) {
      $response['error'] = 'no_session';
      $this->audit->record(2, 'no_session', $browserIp, $context);
    } else {
      try {
        $this->registry->identity($userId, $application, $browserIp);
        $response['code'] = $this->grants->issue($data, $application, $userId, $browserIp);
        $this->audit->record(2, 'code_issued', $browserIp, $context);
      } catch (AuthenticationException) {
        $response['error'] = 'access_denied';
        $this->audit->record(2, 'access_denied', $browserIp, $context);
      }
    }
    $response['signature'] = Protocol::sign($response, $application['secret'], 'callback');
    return $data['callback'] . '?' . http_build_query($response, '', '&', PHP_QUERY_RFC3986);
  }

  public function exchange(array $data, string $serverIp): array
  {
    $context = [];
    try {
      $expected = ['application_url', 'callback', 'code', 'issued_at', 'protocol', 'signature', 'verifier'];
      $keys = array_keys($data);
      sort($expected);
      sort($keys);
      if ($keys !== $expected || count(array_filter($data, 'is_string')) !== count($data) || $data['protocol'] !== '2') {
        throw new AuthenticationException('Invalid exchange.');
      }
      $application = $this->registry->find(Protocol::applicationUrl($data['application_url'], $this->allowLocalHttp));
      Protocol::verify($data, $application['secret'], 'exchange');
      $context['application_url'] = $application['url'];
      $grant = $this->grants->consume($data, $application);
      $context['correlation'] = $grant['correlation'];
      $context['user_id'] = $grant['user_id'];
      $identity = $this->registry->identity($grant['user_id'], $application, $grant['browser_ip']);
      $this->audit->record(2, 'exchange_accepted', $serverIp, $context);
      return $identity;
    } catch (AuthenticationException $exception) {
      $this->audit->record(2, 'exchange_rejected', $serverIp, $context);
      throw $exception;
    }
  }
}
