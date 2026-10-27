<?php

namespace Imanaging\ZeusUserBundle\Sso;

interface ApplicationRegistryInterface
{
  /** Return id, url and secret for exactly one active registered application. */
  public function find(string $applicationUrl): array;

  /** Recheck account and application access, return subject/login/source, or reject. */
  public function identity(string $userId, array $application, string $browserIp): array;
}
