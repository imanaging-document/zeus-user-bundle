<?php


namespace Imanaging\ZeusUserBundle\Controller;


use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ImanagingController extends AbstractController
{
  /**
   * Gestion de l'accès aux différents modules
   * @param UserInterface $user
   * @param array $modulesRoute
   * @param bool $showFlashBag
   * @return bool
   */
  public function userCanAccess(UserInterface $user, array $modulesRoute, $showFlashBag = true){
    $canAccess = false;
    if ($user instanceof UserInterface){
      $role = $user->getRole();
      if ($role instanceof RoleInterface){
        // On récupère tous les modules du rôle
        $routes = array();
        foreach ($role->getModules() as $roleModule){
          if ($roleModule instanceof RoleModuleInterface){
            $routes[] = $roleModule->getModule()->getRoute();
          }
        }
        foreach ($modulesRoute as $moduleRoute){
          if (in_array($moduleRoute, $routes)){
            $canAccess = true;
          }
        }
      }
    }
    if ($showFlashBag){
      if (!$canAccess){
        $this->get('session')->getFlashBag()->add(
          'error',
          'Vous ne pouvez pas accéder à ce module.'
        );
      }
    }
    return $canAccess;
  }
}