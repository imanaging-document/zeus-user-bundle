<?php

namespace Imanaging\ZeusUserBundle\Sso\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Schema is managed by Doctrine; GrantStore performs atomic consumption through DBAL. */
#[ORM\Entity]
#[ORM\Table(name: 'sso_authorization_code')]
#[ORM\Index(name: 'sso_code_expiration', columns: ['expires_at'])]
class SsoAuthorizationCode
{
  #[ORM\Id]
  #[ORM\Column(name: 'code_hash', type: 'string', length: 64, options: ['fixed' => true])]
  private string $codeHash;

  #[ORM\Column(name: 'application_url', type: 'string', length: 512)]
  private string $applicationUrl;

  #[ORM\Column(name: 'user_id', type: 'string', length: 64)]
  private string $userId;

  #[ORM\Column(type: 'string', length: 1024)]
  private string $callback;

  #[ORM\Column(type: 'string', length: 43)]
  private string $challenge;

  #[ORM\Column(type: 'string', length: 64, options: ['fixed' => true])]
  private string $correlation;

  #[ORM\Column(name: 'browser_ip', type: 'string', length: 45)]
  private string $browserIp;

  #[ORM\Column(name: 'expires_at', type: 'bigint')]
  private string $expiresAt;

  #[ORM\Column(name: 'consumed_at', type: 'bigint', nullable: true)]
  private ?string $consumedAt = null;
}
