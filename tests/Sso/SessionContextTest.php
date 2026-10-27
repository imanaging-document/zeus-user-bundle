<?php

namespace Imanaging\ZeusUserBundle\Tests\Sso;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Imanaging\ZeusUserBundle\Login;
use Imanaging\ZeusUserBundle\Sso\Protocol;
use Imanaging\ZeusUserBundle\Sso\SessionContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionContextTest extends TestCase
{
  public function testRealLoginKeepsOnlyLiveSsoContextAndBoundTarget(): void
  {
    $session = new Session(new MockArraySessionStorage());
    $nonce = Protocol::random();
    $expired = Protocol::random();
    $entry = ['request' => ['protocol' => '2'], 'expires' => time() + 300];
    $session->set(SessionContext::PENDING_AUTHORIZATIONS, [
      $nonce => $entry, $expired => ['request' => [], 'expires' => time() - 1],
    ]);
    $target = '/sso-login/v2/authorize?resume=' . $nonce;
    $session->set('_security.user_secured_area.target_path', $target);
    $session->set('_security.other.target_path', 'https://attacker.example');
    $session->set('menu_0', ['old-user-menu']);
    $request = Request::create('/connexion');
    $request->setSession($session);
    $stack = new RequestStack();
    $stack->push($request);
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findOneBy')->willReturn(null);
    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('getRepository')->willReturn($repository);
    $login = $this->getMockBuilder(Login::class)->disableOriginalConstructor()->onlyMethods(['createConnexion'])->getMock();
    $login->expects(self::once())->method('createConnexion');
    (new \ReflectionProperty(Login::class, 'em'))->setValue($login, $em);
    (new \ReflectionProperty(Login::class, 'requestStack'))->setValue($login, $stack);
    self::assertFalse($login->canLog('unknown-user', 'invalid-password'));
    self::assertSame([$nonce => $entry], $session->get(SessionContext::PENDING_AUTHORIZATIONS));
    self::assertSame($target, $session->get('_security.user_secured_area.target_path'));
    self::assertFalse($session->has('menu_0'));
    self::assertFalse($session->has('_security.other.target_path'));
  }

  public function testLegacyLoginStillClearsItsWholeSession(): void
  {
    $session = new Session(new MockArraySessionStorage());
    $session->set('menu_0', ['old']);
    $session->set('_security.user_secured_area.target_path', '/old-target');
    SessionContext::resetForLogin($session);
    self::assertSame([], $session->all());
  }

  public function testUnknownResumeTargetIsNotPreserved(): void
  {
    $session = new Session(new MockArraySessionStorage());
    $session->set(SessionContext::PENDING_AUTHORIZATIONS, [
      Protocol::random() => ['request' => [], 'expires' => time() + 300],
    ]);
    $session->set('_security.user_secured_area.target_path', '/sso-login/v2/authorize?resume=' . Protocol::random());
    SessionContext::resetForLogin($session);
    self::assertFalse($session->has('_security.user_secured_area.target_path'));
  }
}
