<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Imanaging\ZeusUserBundle\Sso\Protocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class ProtocolTest extends TestCase
{
  #[DataProvider('invalidProofs')]
  public function testRejectsInvalidProof(array $changes, string $secret, int $offset): void
  {
    $data = ['issued_at' => (string) time(), 'protocol' => '2', 'state' => str_repeat('a', 64)];
    $data['signature'] = Protocol::sign($data, str_repeat('s', 32), 'authorize');
    $data = array_replace($data, $changes);
    $this->expectException(AuthenticationException::class);
    Protocol::verify($data, $secret, 'authorize', time() + $offset);
  }

  public static function invalidProofs(): array
  {
    return [
      'tampered' => [['state' => str_repeat('b', 64)], str_repeat('s', 32), 0],
      'expired' => [[], str_repeat('s', 32), 601],
      'future' => [[], str_repeat('s', 32), -31],
      'wrong secret' => [[], str_repeat('x', 32), 0],
      'weak secret' => [[], 'short', 0],
      'array signature' => [['signature' => []], str_repeat('s', 32), 0],
    ];
  }

  public function testSignatureIsIndependentOfKeyOrderButBoundToPurpose(): void
  {
    $data = ['issued_at' => (string) time(), 'protocol' => '2'];
    $data['signature'] = Protocol::sign($data, str_repeat('s', 32), 'authorize');
    Protocol::verify(array_reverse($data, true), str_repeat('s', 32), 'authorize');
    $this->expectException(AuthenticationException::class);
    Protocol::verify($data, str_repeat('s', 32), 'callback');
  }

  #[DataProvider('badCallbacks')]
  public function testRejectsUnregisteredCallback(string $callback): void
  {
    $data = [
      'protocol' => '2', 'application_url' => 'https://satellite.example', 'callback' => $callback,
      'challenge' => Protocol::challenge(str_repeat('a', 64)), 'state' => str_repeat('b', 64),
      'correlation' => str_repeat('c', 64), 'issued_at' => (string) time(), 'signature' => str_repeat('d', 64),
    ];
    $this->expectException(AuthenticationException::class);
    Protocol::validateAuthorization($data);
  }

  public static function badCallbacks(): array
  {
    return [
      ['https://attacker.example/sso-login/return-validation/v2'],
      ['https://satellite.example.attacker.example/sso-login/return-validation/v2'],
      ['https://satellite.example/sso-login/return-validation/v2?next=https://attacker.example'],
      ['https://satellite.example/sso-login/return-validation/v2#fragment'],
      ['https://satellite.example/sso-login/return-validation/v2/../other'],
    ];
  }

  #[DataProvider('targets')]
  public function testTargetStaysLocal(string $target, string $expected): void
  {
    self::assertSame($expected, Protocol::localTarget($target));
  }

  public static function targets(): array
  {
    return [['/documents/42', '/documents/42'], ['https://attacker.example', '/'], ['//attacker.example', '/'], ['/\\attacker', '/']];
  }
}
