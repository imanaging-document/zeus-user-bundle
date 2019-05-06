<?php


namespace Imanaging\ZeusUserBundle\Controller;
use Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface;
use Imanaging\ZeusUserBundle\Login;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ConnexionController extends AbstractController
{
  private $login;

  public function __construct(Login $login)
  {
    $this->login = $login;
  }

  public function index(){
    $em = $this->getDoctrine()->getManager();
    $connexions = $em->getRepository(ConnexionInterface::class)->findAll();

    return $this->render('@ImanagingZeusUser/Connexion/index.html.twig', [
      'connexions' => $connexions
    ]);
  }
}