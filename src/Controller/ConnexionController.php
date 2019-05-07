<?php


namespace Imanaging\ZeusUserBundle\Controller;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Imanaging\ZeusUserBundle\Login;

class ConnexionController extends ImanagingController
{
  private $login;

  public function __construct(Login $login)
  {
    $this->login = $login;
  }

  public function showTableConnexion(){
    $user = $this->getUser();
    if ($user instanceof UserInterface){
      if ($this->userCanAccess($user, ['zeus_user_show_connexion_table'])){
        $em = $this->getDoctrine()->getManager();
        $connexions = $em->getRepository(ConnexionInterface::class)->findAll();

        return $this->render('@ImanagingZeusUser/Connexion/showTable.html.twig', [
          'connexions' => $connexions
        ]);
      }
    }
    return $this->render('@ImanagingZeusUser/access_denied.html.twig', [
    ]);
  }
}