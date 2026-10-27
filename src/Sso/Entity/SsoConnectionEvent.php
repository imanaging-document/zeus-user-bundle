<?php

namespace Imanaging\ZeusUserBundle\Sso\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sso_connection_event')]
#[ORM\Index(name: 'sso_event_date', columns: ['occurred_at'])]
#[ORM\Index(name: 'sso_event_protocol', columns: ['protocol', 'id'])]
#[ORM\Index(name: 'sso_event_correlation', columns: ['correlation'])]
class SsoConnectionEvent
{
  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
  private ?string $id = null;

  #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
  private \DateTimeImmutable $occurredAt;

  #[ORM\Column(type: 'smallint')]
  private int $protocol;

  #[ORM\Column(type: 'string', length: 64)]
  private string $event;

  #[ORM\Column(name: 'application_url', type: 'string', length: 512, options: ['default' => ''])]
  private string $applicationUrl = '';

  #[ORM\Column(name: 'user_id', type: 'string', length: 64, options: ['default' => ''])]
  private string $userId = '';

  #[ORM\Column(type: 'string', length: 64, nullable: true, options: ['fixed' => true])]
  private ?string $correlation = null;

  #[ORM\Column(name: 'address_ip', type: 'string', length: 45)]
  private string $addressIp;
}
