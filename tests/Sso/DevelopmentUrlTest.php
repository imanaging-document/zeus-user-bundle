<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Imanaging\ZeusUserBundle\Sso\Protocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class DevelopmentUrlTest extends TestCase
{
  #[DataProvider('urls')]
  public function testLocalHttpRequiresExplicitDevelopmentMode(string $url, bool $debug, bool $allowed): void
  {
    if (!$allowed) {
      $this->expectException(AuthenticationException::class);
    }
    self::assertSame($url, Protocol::applicationUrl($url, $debug));
  }

  public static function urls(): array
  {
    return [
      ['http://zeus.local', false, false], ['http://zeus.local', true, true],
      ['http://production.example', true, false], ['http://zeus.local.evil.example', true, false],
      ['http://localhost', false, true], ['https://production.example', false, true],
    ];
  }
}
