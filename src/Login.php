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
use Imanaging\ApiCommunicationBundle\ApiZeusCommunication;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionStatutInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class Login
{
  private $em;
  private $apiZeusCommunication;
  private $encoder;

  /**
   * @param EntityManagerInterface $em
   * @param ApiZeusCommunication $apiZeusCommunication
   * @param UserPasswordEncoderInterface $encoder
   */
  public function __construct(EntityManagerInterface $em, ApiZeusCommunication $apiZeusCommunication, UserPasswordEncoderInterface $encoder){
    $this->em = $em;
    $this->apiZeusCommunication = $apiZeusCommunication;
    $this->encoder = $encoder;
  }
  
  /**
   * @param $login
   * @param $password
   * @param string $ipAddress
   * @return bool|object
   * @throws Exception
   */
  public function canLog($login, $password, $ipAddress = null){
    // on vérifie dans la base local si le user existe, sinon on check sur ZEUS
    $user = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $login, 'utilisateurZeus' => false));
    if ($user instanceof UserInterface){
      if ($this->encoder->isPasswordValid($user, $password)){
        return $user;
      } else {
        $this->createConnexion('mdp_incorrect', $user, $user->getLogin(), $ipAddress);
        return false;
      }
    } else {
      $user = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $login));
      if ($user instanceof UserInterface){
        // on check par API
        $url = '/connexion?login='.$login.'&password='.$password;
        $response = $this->apiZeusCommunication->sendGetRequest($url);

        if ($response->getHttpCode() == 200) {
          $userLogin = json_decode($response->getData());

          if ($userLogin != "") {
            $user = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $userLogin));
            if ($user instanceof UserInterface) {
              return $user;
            }
          }
        }
        $this->createConnexion($user, $user->getLogin(), 'mdp_incorrect', $ipAddress);
      } else {
        $this->createConnexion($user, $login, 'compte_inexistant', $ipAddress);
      }
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
}