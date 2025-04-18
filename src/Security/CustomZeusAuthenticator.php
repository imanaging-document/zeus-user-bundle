<?php

namespace Imanaging\ZeusUserBundle\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Login;
use Imanaging\ZeusUserBundle\Security\Exception\CustomCoreAuthenticationException;
use Imanaging\ZeusUserBundle\Security\Exception\CustomZeusAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class CustomZeusAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
  use TargetPathTrait;

  const STATE_SSO_NON_CHECKED = 0;
  const STATE_SSO_ZEUS_CHECKED = 1;
  const STATE_SSO_CORE_CHECKED = 2;

  private RouterInterface $router;
  private Request $request;
  private $ownUrl;
  private EntityManagerInterface $em;
  private Login $loginService;

  /**
   * @param EntityManagerInterface $em
   * @param RouterInterface $router
   * @param $ownUrl
   * @param Login $loginService
   */
  public function __construct(EntityManagerInterface $em, RouterInterface $router, Login $loginService, $ownUrl)
  {
    $this->em = $em;
    $this->router = $router;
    $this->loginService = $loginService;
    $this->ownUrl = $ownUrl;
  }

  public function supports(Request $request): ?bool
  {
    return $request->getPathInfo() === '/connexion' && $request->isMethod('POST');
  }

  public function authenticate(Request $request): Passport
  {
    $login = $request->request->get('login');
    $password = $request->request->get('password');
    $this->request = $request;
    $user = $this->em->getRepository(UserInterface::class)->findOneByLoginOrMail($login);
    if (!($user instanceof User)) {
      $this->loginService->createConnexion(null, $login, 'compte_inexistant');
      throw new AuthenticationException('Identifiants de connexion invalides.');
    }

    if ($user->isUtilisateurZeus()) {
      // GO TO ZEUS LOGIN AUTHENTICATOR
      throw new CustomZeusAuthenticationException($login, $password);
    } else {
      // GO TO CORE
      throw new CustomCoreAuthenticationException($login, $password);
    }
  }

  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    if ($target = $this->getTargetPath($request->getSession(), $firewallName)) {
      return new RedirectResponse($target);
    }

    return new RedirectResponse(
      $this->router->generate('hephaistos_homepage')
    );
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    return new RedirectResponse(
      $this->router->generate('hephaistos_login')
    );
  }

  public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
  {
    $target = $this->getTargetPath($request->getSession(), 'user_secured_area');
    return new RedirectResponse(
      $this->router->generate('hephaistos_login')."?redirect_to=" . CoreSsoAuthenticator::encrypt($target)
    );
  }
}
