<?php

namespace Imanaging\ZeusUserBundle\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ZeusUserBundle\Login;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class ZeusValidationSsoAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
  use TargetPathTrait;

  const ALGORITHM = 'aes-256-ctr';
  const SKEY = '1lgs2gjwjPZpeqUHlYD9ktJBXfsuH5al';
  const IV = 'gKySztfUMx7uQEl7';

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
    return str_contains($request->getPathInfo(), '/sso-login/return-validation') && $request->isMethod('GET');
  }

  public function authenticate(Request $request): Passport
  {
    $login = $request->get('login');
    if (is_null($login)) {
      $exception = $request->get('e');
      if (!is_null($exception)) {
        throw new AuthenticationException(base64_decode($exception));
      } else {
        throw new AuthenticationException('Impossible de vous connecter. #5');
      }
    }

    return new Passport(
      new UserBadge($login, function($userIdentifier) {
        $user = $this->em->getRepository(User::class)->findOneByLoginOrMail($userIdentifier);
        if (!($user instanceof User)) {
          $this->loginService->createConnexion(null, $userIdentifier, 'compte_inexistant');
          throw new AuthenticationException('Impossible de vous connecter. #1');
        }
        return $user;
      }),
      new CustomCredentials(function($credentials, User $user) {
        return true;
      }, ''),
      [
        new RememberMeBadge()
      ]
    );
  }

  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    return new RedirectResponse(self::decrypt($request->get('target')));
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
    return new RedirectResponse(
      $this->router->generate('hephaistos_login', ['ssoState' => CustomZeusAuthenticator::STATE_SSO_ZEUS_CHECKED])
    );
  }

  public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
  {
    return new RedirectResponse(
      $this->router->generate('hephaistos_login')
    );
  }

  public static function encrypt($strToEncode) {
    return bin2hex(openssl_encrypt($strToEncode, self::ALGORITHM, self::SKEY, OPENSSL_RAW_DATA, self::IV));
  }

  public static function decrypt($encodedStr) {
    return openssl_decrypt(pack('H*', $encodedStr), self::ALGORITHM, self::SKEY, OPENSSL_RAW_DATA, self::IV);
  }
}
