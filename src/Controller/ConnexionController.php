<?php


namespace Imanaging\ZeusUserBundle\Controller;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Login;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ConnexionController extends ImanagingController
{
  private $login;
  private $tokenStorage;

  public function __construct(Login $login, TokenStorageInterface $tokenStorage)
  {
    $this->login = $login;
    $this->tokenStorage = $tokenStorage;
  }

  public function showTableConnexion(){
    $user = $this->getUser();
    if ($user instanceof UserInterface){
      $em = $this->getDoctrine()->getManager();
      $connexions = $em->getRepository(ConnexionInterface::class)->findAll();

      return $this->render('@ImanagingZeusUser/Connexion/showTable.html.twig', [
        'connexions' => $connexions
      ]);
    }
    return $this->render('@ImanagingZeusUser/access_denied.html.twig', [
    ]);
  }

  public function getDataForRedirect() {
    $user = $this->tokenStorage->getToken()->getUser();
    // on demande a ZEUS le token (sha256 login/tokenAppli+datatime) de cet utilisateur et si utilisateur zeus ou
    $res = $this->login->getDataForRedirect($user);
    if (in_array($res['type'], ['zeus', 'core'])) {
      return new JsonResponse($res);
    } else {
      return new JsonResponse(['error_message' => 'Un utilisateur local ne peux pas être redirigé'], 500);
    }
  }
}