<?php


namespace Imanaging\ZeusUserBundle\Controller;
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
    return $this->json([
      'foo' => 'bar'
    ]);
  }
}