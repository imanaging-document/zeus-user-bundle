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

class CoreSsoAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
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
    return $request->getPathInfo() === '/sso-login' && $request->isMethod('GET');
  }

  public function authenticate(Request $request): Passport
  {
    $login = $request->get('login');
    $tokenWithDate = $request->get('t');
    $date = $request->get('d');
    $passwordDecoded = $this->decrypt($tokenWithDate);
    $dateDecoded = substr($passwordDecoded, 0, 14);
    if ($dateDecoded == $date) {
      $passwordEncoded = substr($passwordDecoded, 14);
      $password = $this->decrypt($passwordEncoded);
      $this->request = $request;

      return new Passport(
        new UserBadge($login, function($userIdentifier) {
          $user = $this->em->getRepository(User::class)->findOneByLoginOrMail($userIdentifier);
          if (!($user instanceof User)) {
            $this->loginService->createConnexion(null, $userIdentifier, 'compte_inexistant');
            throw new AuthenticationException('Impossible de vous connecter. #1');
          }

          if ($user->isUtilisateurZeus()) {
            // GO TO ZEUS LOGIN AUTHENTICATOR
            throw new AuthenticationException('Impossible de vous connecter. #3');
          }

          return $user;
        }),
        new CustomCredentials(function($credentials, User $user) {
          $user = $this->loginService->canLog($user->getLogin(), $credentials, $this->request->getClientIp());
          if ($user instanceof User) {
            $this->loginService->createConnexion($user, $user->getLogin(), 'connexion_reussie');
            return true;
          } else {
            throw new AuthenticationException('Identifiants de connexion invalides. #2');
          }
        }, $password),
        [
          new RememberMeBadge()
        ]
      );
    } else {
      throw new AuthenticationException('Impossible de vous connecter. #4');
    }
  }

  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    $target = $request->get('p');
    return new RedirectResponse($this->decrypt($target));
  }

  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
  {
    $target = $request->get('p');
    return new RedirectResponse($this->decrypt($target)."?e=".base64_encode($exception->getMessage()));
  }

  public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
  {
    $target = $this->getTargetPath($request->getSession(), 'user_secured_area');
    return new RedirectResponse(
      $this->router->generate('hephaistos_login')."?redirect_to=" . CoreSsoAuthenticator::encrypt($target)
    );
  }

  public static function encrypt($strToEncode) {
    return bin2hex(openssl_encrypt($strToEncode, self::ALGORITHM, self::SKEY, OPENSSL_RAW_DATA, self::IV));
  }

  public static function decrypt($encodedStr) {
    return openssl_decrypt(pack('H*', $encodedStr), self::ALGORITHM, self::SKEY, OPENSSL_RAW_DATA, self::IV);
  }
}