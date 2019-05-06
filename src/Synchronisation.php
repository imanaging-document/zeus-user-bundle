<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 30/05/2017
 * Time: 11:39
 */

namespace Imanaging\ZeusUserBundle;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Imanaging\ApiCommunicationBundle\ApiZeusCommunication;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;

class Synchronisation
{
  private $em;
  private $apiZeusCommunicationService;
  private $apiGetModulesPath;
  private $apiGetRolesPath;

  /**
   * @param EntityManagerInterface $em
   * @param ApiZeusCommunication $communicationService
   * @param $apiGetModulesPath
   * @param $apiGetRolesPath
   */
  public function __construct(EntityManagerInterface $em, ApiZeusCommunication $communicationService, $apiGetModulesPath, $apiGetRolesPath){
    $this->em = $em;
    $this->apiZeusCommunicationService = $communicationService;
    $this->apiGetModulesPath = $apiGetModulesPath;
    $this->apiGetRolesPath = $apiGetRolesPath;
  }

  /**
   * @return mixed
   */
  public function synchroniserModules(){
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $url = $this->apiGetModulesPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() === 200){
      // on gère la suppression de modules
      $modulesExistants = $this->em->getRepository(ModuleInterface::class)->findAll();

      $nbModuleDeleted = 0;
      $nbModuleUpdated = 0;
      $nbModuleAdded = 0;
      // récupération de tous les modules
      $modules = json_decode($response->getData());

      // ON CHARGE TOUS LES MODULES SANS LES PARENTS
      foreach ($modules as $module){
        $foundModule = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $module->code));
        if ($foundModule instanceof ModuleInterface) {
          $foundModule->setLibelle($module->libelle);
          $foundModule->setRoute($module->route);
          $foundModule->setOrdre($module->ordre);
          $foundModule->setNiveau($module->niveau);
          $foundModule->setDroite($module->droite);
          $foundModule->setParent(null);
          $this->em->persist($foundModule);
          $nbModuleUpdated++;

          // on supprime du tableaux des modules existants
          if (($key = array_search($foundModule, $modulesExistants)) !== false) {
            unset($modulesExistants[$key]);
          }
        } else {
          $className = $this->em->getRepository(ModuleInterface::class)->getClassName();
          $newModule = new $className();
          if ($newModule instanceof ModuleInterface){
            $newModule->setCode($module->code);
            $newModule->setLibelle($module->libelle);
            $newModule->setRoute($module->route);
            $newModule->setOrdre($module->ordre);
            $newModule->setNiveau($module->niveau);
            $newModule->setDroite($module->droite);
            $newModule->setParent(null);
            $this->em->persist($newModule);
            $nbModuleAdded++;
          }
        }
      }
      $this->em->flush();

      // ON CHARGE LES PARENTS
      foreach ($modules as $module){
        $foundModule = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $module->code));
        if ($foundModule instanceof ModuleInterface) {
          if (isset($module->parent_code)){
            $parent = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $module->parent_code));
            if ($parent instanceof ModuleInterface){
              $foundModule->setParent($parent);
              $this->em->persist($foundModule);
            }
          }
        }
      }
      $this->em->flush();

      foreach ($modulesExistants as $module) {
        // on supprime les liaisons avec les roles
        $module->setRoles(new ArrayCollection());
        $this->em->persist($module);
        $this->em->remove($module);
        $nbModuleDeleted++;
      }

      return array(
        'nb_module_updated' => $nbModuleUpdated,
        'nb_module_added' => $nbModuleAdded,
        'nb_module_deleted' => $nbModuleDeleted
      );
    } else {
      return false;
    }
  }

  /**
   * @return mixed
   */
  public function synchroniserRoles(){
    $loginApiDashboard = getenv('ZEUS_API_LOGIN');
    $passwordApiDashboard = getenv('ZEUS_API_PASSWORD');

    $url = $this->apiGetRolesPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() === 200) {
      $nbRoleUpdated = 0;
      $nbRoleAdded = 0;
      // récupération de tous les roles
      $roles = json_decode($response->getData());

      foreach ($roles as $role) {
        // On récupère tous les modules auxquels ce role est lié
        $modules = array();
        foreach ($role->modules as $module) {
          $foundModule = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $module->code));
          if ($foundModule instanceof ModuleInterface) {
            // si le module a été trouvé, on l'ajoute à la liste des modules
            array_push($modules, $foundModule);
          }
        }
        $foundRole = $this->em->getRepository(RoleInterface::class)->findOneBy(array('id' => $role->id));
        if ($foundRole instanceof RoleInterface) {
          $foundRole->setLibelle($role->libelle);
          $foundRole->setModules($modules);
          $this->em->persist($foundRole);
          $nbRoleUpdated++;
        } else {
          $className = $this->em->getRepository(RoleInterface::class)->getClassName();
          $newRole = new $className();
          if ($newRole instanceof RoleInterface){
            $newRole->setId($role->id);
            $newRole->setLibelle($role->libelle);
            $newRole->setModules($modules);
            $this->em->persist($newRole);
            $nbRoleAdded++;
          }
        }
      }
      $this->em->flush();

      return array(
        'nb_role_updated' => $nbRoleUpdated,
        'nb_role_added' => $nbRoleAdded
      );
    } else {
      return false;
    }
  }

  /**
   * @return mixed
   */
  public function synchroniserUsers(){
    $loginApiDashboard = getenv('ZEUS_API_LOGIN');
    $passwordApiDashboard = getenv('ZEUS_API_PASSWORD');

    $url = '/utilisateurs/all?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() == 200){
      $nbUserUpdated = 0;
      $nbUserAdded = 0;
      // récupération de tous les roles
      $decodedResponse = json_decode($response->getData());
      $users = $decodedResponse->users;
      foreach ($users as $user) {
        $role = $this->em->getRepository(RoleInterface::class)->findOneBy(array('id' => $user->role_hephaistos_questions_multi_canal));
        if ($role instanceof RoleInterface){
          $foundUser = $this->em->getRepository(UserInterface::class)->findOneBy(array('id' => $user->id));
          if ($foundUser instanceof UserInterface) {
            $foundUser->setNom($user->nom);
            $foundUser->setPrenom($user->prenom);
            $foundUser->setLogin($user->login);
            $foundUser->setUsername($user->username);
            $foundUser->setMail($user->mail);
            $foundUser->setRole($role);
            $foundUser->setUtilisateurZeus(true);
            $this->em->persist($foundUser);
            $nbUserUpdated++;
          } else {
            $className = $this->em->getRepository(UserInterface::class)->getClassName();
            $newUser = new $className();
            if ($newUser instanceof UserInterface){
              $newUser->setId($user->id);
              $newUser->setNom($user->nom);
              $newUser->setPrenom($user->prenom);
              $newUser->setLogin($user->login);
              $newUser->setUsername($user->username);
              $newUser->setMail($user->mail);
              $newUser->setRole($role);
              $newUser->setUtilisateurZeus(true);
              $this->em->persist($newUser);
              $nbUserAdded++;
            }

          }
        }
      }
      $this->em->flush();

      return array(
        'nb_user_updated' => $nbUserUpdated,
        'nb_user_added' => $nbUserAdded
      );
    } else {
      return false;
    }
  }
}