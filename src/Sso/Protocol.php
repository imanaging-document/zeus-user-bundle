<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class Protocol
{
  public const VERSION = 2;
  public const REQUEST_TTL = 600;
  public const CODE_TTL = 60;

  public static function random(): string
  {
    return bin2hex(random_bytes(32));
  }

  public static function challenge(string $verifier): string
  {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
  }

  public static function sign(array $data, string $secret, string $purpose): string
  {
    if (strlen($secret) < 32) {
      throw new AuthenticationException('SSO credential unavailable.');
    }
    unset($data['signature']);
    ksort($data, SORT_STRING);
    foreach ($data as $value) {
      if (!is_string($value) || strlen($value) > 2048) {
        throw new AuthenticationException('Invalid SSO payload.');
      }
    }
    $key = hash_hkdf('sha256', $secret, 32, 'hephaistos-sso-v2');
    return hash_hmac('sha256', $purpose . "\n" . json_encode($data, JSON_THROW_ON_ERROR), $key);
  }

  public static function verify(array $data, string $secret, string $purpose, ?int $now = null): void
  {
    $now ??= time();
    $signature = $data['signature'] ?? '';
    $issued = $data['issued_at'] ?? '';
    if (!is_string($signature) || !is_string($issued) || !ctype_digit($issued)
      || (int) $issued > $now + 30 || (int) $issued < $now - self::REQUEST_TTL
      || !hash_equals(self::sign($data, $secret, $purpose), $signature)) {
      throw new AuthenticationException('Invalid or expired SSO proof.');
    }
  }

  public static function applicationUrl(string $url, bool $allowLocalHttp = false): string
  {
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
      || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
      || isset($parts['query']) || isset($parts['fragment']) || str_contains($url, '\\')
      || preg_match('/[\x00-\x20]/', $url)) {
      throw new AuthenticationException('Invalid SSO application URL.');
    }
    if ($parts['scheme'] !== 'https' && !in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true)
      && !($allowLocalHttp && str_ends_with(strtolower($parts['host']), '.local'))) {
      throw new AuthenticationException('HTTPS required for SSO.');
    }
    return rtrim($url, '/');
  }

  public static function validateAuthorization(array $data, bool $allowLocalHttp = false): void
  {
    $keys = ['application_url', 'callback', 'challenge', 'correlation', 'issued_at', 'protocol', 'signature', 'state'];
    $actual = array_keys($data);
    sort($actual);
    sort($keys);
    if ($actual !== $keys || count(array_filter($data, 'is_string')) !== count($data)
      || ($data['protocol'] ?? null) !== '2') {
      throw new AuthenticationException('Unsupported SSO request.');
    }
    foreach (['state', 'correlation'] as $key) {
      if (!is_string($data[$key]) || !preg_match('/^[a-f0-9]{64}$/D', $data[$key])) {
        throw new AuthenticationException('Invalid SSO transaction.');
      }
    }
    if (!is_string($data['challenge']) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $data['challenge'])) {
      throw new AuthenticationException('Invalid PKCE challenge.');
    }
    $base = self::applicationUrl($data['application_url'], $allowLocalHttp);
    $callbacks = [$base . '/sso-login/return-validation/v2', $base . '/administration/sso-login/return-validation/v2'];
    if (!in_array($data['callback'], $callbacks, true)) {
      throw new AuthenticationException('Unregistered SSO callback.');
    }
  }

  public static function localTarget(string $target, string $fallback = '/'): string
  {
    if (!str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\')
      || preg_match('/[\x00-\x20]/', $target)) {
      return $fallback;
    }
    return $target;
  }
}
