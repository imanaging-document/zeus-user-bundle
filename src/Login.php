<?php
/**
 * Created by PhpStorm.
 * User: PC14
 * Date: 30/04/2018
 * Time: 15:38
 */

namespace Imanaging\ZeusUserBundle;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Imanaging\ApiCommunicationBundle\ApiCoreCommunication;
use Imanaging\ApiCommunicationBundle\ApiZeusCommunication;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionStatutInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class Login
{
  private $em;
  private $apiZeusCommunication;
  private $apiCoreCommunication;
  private $encoder;
  private $session;
  private $apiConnexionPath;
  private $ownUrl;
  private $apiZeusToken;

  /**
   * @param EntityManagerInterface $em
   * @param ApiZeusCommunication $apiZeusCommunication
   * @param UserPasswordEncoderInterface $encoder
   * @param ApiCoreCommunication $apiCoreCommunication
   * @param SessionInterface $session
   * @param $apiConnexionPath
   * @param $ownUrl
   * @param $apiZeusToken
   */
  public function __construct(EntityManagerInterface $em, ApiZeusCommunication $apiZeusCommunication,
                              UserPasswordEncoderInterface $encoder, ApiCoreCommunication $apiCoreCommunication, SessionInterface $session, $apiConnexionPath, $ownUrl, $apiZeusToken){
    $this->em = $em;
    $this->apiZeusCommunication = $apiZeusCommunication;
    $this->apiCoreCommunication = $apiCoreCommunication;
    $this->encoder = $encoder;
    $this->session = $session;
    $this->apiConnexionPath = $apiConnexionPath;
    $this->ownUrl = $ownUrl;
    $this->apiZeusToken = $apiZeusToken;
  }

  /**
   * @param $login
   * @param $password
   * @param string $ipAddress
   * @return bool|object
   * @throws Exception
   */
  public function canLog($login, $password, $ipAddress = null){
    // on supprime le cache de la session
    $this->session->clear();

    // on vérifie dans la base local si le user existe, sinon on check sur ZEUS
    $user = $this->em->getRepository(UserInterface::class)->findOneBy(['login' => $login]);
    if (!($user instanceof UserInterface)){
      // On tente aussi par e-mail
      $user = $this->em->getRepository(UserInterface::class)->findOneBy(['mail' => $login]);
    }
    if ($user instanceof UserInterface){
      if ($user->isUtilisateurZeus()) {
        // on check par API
        $url = $this->apiConnexionPath;
        $postData = [
          'login' => $login,
          'password' => $password,
          'adress_ip' => $ipAddress,
          'url' => $this->ownUrl,
        ];
        $response = $this->apiZeusCommunication->sendPostRequest($url, $postData);

        if ($response->getHttpCode() == 200) {
          $userLogin = json_decode($response->getData());

          if ($userLogin != "") {
            $userFound = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $userLogin));
            if ($userFound instanceof UserInterface) {
              return $userFound;
            }
          }
        }
      } elseif ($user->isUtilisateurCore()){
        // on check par API sur le CORE
        $now = new DateTime();
        $nowFormat = $now->format('YmdHis');
        $tokenFormatted = hash('sha256', $this->apiCoreCommunication->getApiCoreToken());

        $url = '/can-log?token='.$tokenFormatted.'&token_date'.$nowFormat.'&login='.$login.'&password='.$password;
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          return $user;
        }
        $this->createConnexion($user, $user->getLogin(), 'mdp_incorrect', $ipAddress);
      } else {
        if ($this->encoder->isPasswordValid($user, $password)){
          return $user;
        } else {
          $this->createConnexion('mdp_incorrect', $user, $user->getLogin(), $ipAddress);
        }
      }
    } else {
      $this->createConnexion($user, $login, 'compte_inexistant', $ipAddress);
    }
    return false;
  }


  public function canLogSso($login, $token) {
    // on supprime le cache du menu
    $this->session->remove('menu_0');
    $this->session->remove('menu_1');

    // on vérifie dans la base local si le user existe, sinon on check sur ZEUS
    $user = $this->em->getRepository(UserInterface::class)->findOneBy(['login' => $login]);

    if ($user instanceof UserInterface){
      if ($user->isUtilisateurZeus()) {
        // on check par API
        $adressIp = '';
        $appUrl = $this->ownUrl;
        $url = '/connexion-sso?login='.$login.'&token='.$token.'&adress_ip='.$adressIp.'&app_url='.$appUrl;
        $response = $this->apiZeusCommunication->sendGetRequest($url);

        if ($response->getHttpCode() == 200) {
          $userLogin = json_decode($response->getData());

          if ($userLogin != "") {
            $userFound = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $userLogin));
            if ($userFound instanceof UserInterface) {
              return $userFound;
            }
          }
        }
      } elseif ($user->isUtilisateurCore()){
        // on check par API sur le CORE
        $now = new DateTime();
        $nowFormat = $now->format('YmdHis');
        $tokenFormatted = hash('sha256', $this->apiCoreCommunication->getApiCoreToken());

        $url = '/can-log-sso?login='.$login.'&tokenUser='.$token.'&token='.$tokenFormatted.'&token_date'.$nowFormat;
        $response = $this->apiCoreCommunication->sendGetRequest($url);
        if ($response->getHttpCode() == 200) {
          $userLogin = json_decode($response->getData());

          if ($userLogin != "") {
            $userFound = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $userLogin));
            if ($userFound instanceof UserInterface) {
              return $userFound;
            }
          }
        }
      }
    } else {
      $this->createConnexion($user, $login, 'compte_inexistant', '');
    }
    return false;
  }

  /**
   * @param $user
   * @param $login
   * @param $codeStatut
   * @param null $adresseIp
   * @throws Exception
   */
  public function createConnexion($user, $login, $codeStatut, $adresseIp = null){
    $statut = $this->em->getRepository(ConnexionStatutInterface::class)->findOneBy(array('code' => $codeStatut));
    if ($statut instanceof ConnexionStatutInterface){
      $className = $this->em->getRepository(ConnexionInterface::class)->getClassName();
      $connexion = new $className();
      if ($connexion instanceof ConnexionInterface){
        $timeConnexion = new DateTime();

        $connexion->setUser($user);
        $connexion->setLogin($login);
        $connexion->setAdresseIp($adresseIp);
        $connexion->setStatut($statut);
        $connexion->setTimeConnexion($timeConnexion);

        $this->em->persist($connexion);
        $this->em->flush();
      }
    }
  }

  public function getDataForRedirect(UserInterface $user)
  {
    if ($user->isUtilisateurCore()) {
      return $this->getDataForRedirectCore($user);
    } elseif ($user->isUtilisateurZeus()) {
      return $this->getDataForRedirectZeus($user);
    } else {
      return ['type' => 'local'];
    }
  }

  private function getDataForRedirectCore(UserInterface $user)
  {
    return [
      'type' => 'core',
      'login' => $user->getLogin(),
      'token' => $this->getTokenFromCore($user)
    ];
  }

  private function getDataForRedirectZeus(UserInterface $user)
  {
    return [
      'type' => 'zeus',
      'login' => $user->getLogin(),
      'token' => $this->getTokenFromZeus($user)
    ];
  }

  private function getTokenFromZeus(UserInterface $user)
  {
    // on check par API
    $now = new DateTime();
    $tokenDate = $now->format('YmdHis');
    $token = hash('sha256', $tokenDate.$this->apiZeusToken);
    $url = '/connexion/token/' . $user->getLogin() . '?token='.$token.'&token_date='.$tokenDate;
    $response = $this->apiZeusCommunication->sendGetRequest($url);
    if ($response->getHttpCode() == 200) {
      return json_decode($response->getData());
    } else {
      return '';
    }
  }

  private function getTokenFromCore(UserInterface $user)
  {
    // on check par API
    $now = new DateTime();
    $nowFormat = $now->format('YmdHis');
    $tokenFormatted = hash('sha256', $this->apiCoreCommunication->getApiCoreToken());
    $url = '/connexion/token/' . $user->getLogin() . '?token='.$tokenFormatted.'&token_date'.$nowFormat;
    $response = $this->apiCoreCommunication->sendGetRequest($url);
    if ($response->getHttpCode() == 200) {
      return json_decode($response->getData());
    } else {
      return 'error';
    }
  }
}