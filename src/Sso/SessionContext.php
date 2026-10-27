<?php

namespace Imanaging\ZeusUserBundle\Sso;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class SessionContext
{
  public const PENDING_AUTHORIZATIONS = '_hephaistos_sso_v2_authorizations';

  public static function resetForLogin(SessionInterface $session): void
  {
    $pending = $session->get(self::PENDING_AUTHORIZATIONS, []);
    $valid = [];
    if (is_array($pending)) {
      foreach ($pending as $nonce => $entry) {
        if (is_string($nonce) && preg_match('/^[a-f0-9]{64}$/D', $nonce) && is_array($entry)
          && is_array($entry['request'] ?? null) && is_int($entry['expires'] ?? null)
          && $entry['expires'] > time() && $entry['expires'] <= time() + Protocol::REQUEST_TTL + 30) {
          $valid[$nonce] = $entry;
        }
      }
    }
    $targets = [];
    if ($valid !== []) {
      foreach ($session->all() as $key => $value) {
        if (preg_match('/^_security\.[a-zA-Z0-9_]+\.target_path$/D', $key) && is_string($value)
          && preg_match('~^/sso-login/v2/authorize\?resume=([a-f0-9]{64})$~D', $value, $match)
          && isset($valid[$match[1]])) {
          $targets[$key] = $value;
        }
      }
    }
    // Keep the historical cleanup, except for validated temporary SSO context and its bound local target.
    $session->clear();
    if ($valid !== []) {
      $session->set(self::PENDING_AUTHORIZATIONS, array_slice($valid, -10, null, true));
      foreach ($targets as $key => $value) {
        $session->set($key, $value);
      }
    }
  }
}
