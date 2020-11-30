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
use Imanaging\ZeusUserBundle\Interfaces\AlerteMailInterface;
use Imanaging\ZeusUserBundle\Interfaces\DestinataireMailInterface;
use Imanaging\ZeusUserBundle\Interfaces\FonctionInterface;
use Imanaging\ZeusUserBundle\Interfaces\ModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\NotificationInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleInterface;
use Imanaging\ZeusUserBundle\Interfaces\RoleModuleInterface;
use Imanaging\ZeusUserBundle\Interfaces\UserInterface;

class Synchronisation
{
  private $em;
  private $apiZeusCommunicationService;
  private $apiGetModulesPath;
  private $apiGetRolesPath;
  private $apiGetAlertesPath;
  private $apiGetFonctionsPath;
  private $apiGetNotificationsPath;

  /**
   * @param EntityManagerInterface $em
   * @param ApiZeusCommunication $communicationService
   * @param $apiGetModulesPath
   * @param $apiGetRolesPath
   * @param $apiGetAlertesPath
   * @param $apiGetFonctionsPath
   */
  public function __construct(EntityManagerInterface $em, ApiZeusCommunication $communicationService,
                              $apiGetModulesPath, $apiGetRolesPath, $apiGetAlertesPath, $apiGetFonctionsPath, $apiGetNotificationsPath){
    $this->em = $em;
    $this->apiZeusCommunicationService = $communicationService;
    $this->apiGetModulesPath = $apiGetModulesPath;
    $this->apiGetRolesPath = $apiGetRolesPath;
    $this->apiGetAlertesPath = $apiGetAlertesPath;
    $this->apiGetFonctionsPath = $apiGetFonctionsPath;
    $this->apiGetNotificationsPath = $apiGetNotificationsPath;
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
          $foundModule->setVisible($module->visible);
          $foundModule->setZeusOnly($module->zeus_only);
          $foundModule->setTypeApplication($module->type_application);
          $foundModule->setDataApplication($module->data_application);
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
            $newModule->setVisible($module->visible);
            $newModule->setZeusOnly($module->zeus_only);
            $newModule->setTypeApplication($module->type_application);
            $newModule->setDataApplication($module->data_application);
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
        foreach ($module->getEnfants() as $sousModule){
          $sousModule->setParent(null);
          $this->em->persist($sousModule);
          $nbModuleDeleted++;
        }
        $this->em->flush();
        // on supprime les liaisons avec les roles
        foreach ($module->getRoles() as $roleModule){
          if ($roleModule instanceof RoleModuleInterface){
            $this->em->remove($roleModule);
          }
        }
        $this->em->remove($module);
        $this->em->flush();
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
  public function synchroniserFonctions(){
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $url = $this->apiGetFonctionsPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);


    if ($response->getHttpCode() === 200){
      // on gère la suppression de modules
      $fonctionsExistants = $this->em->getRepository(FonctionInterface::class)->findAll();

      $nbFonctionDeleted = 0;
      $nbFonctionUpdated = 0;
      $nbFonctionAdded = 0;
      // récupération de tous les modules
      $fonctions = json_decode($response->getData());

      // ON CHARGE TOUS LES MODULES SANS LES PARENTS
      foreach ($fonctions as $fonction){
        $foundFonction = $this->em->getRepository(FonctionInterface::class)->findOneBy(array('code' => $fonction->code));
        if ($foundFonction instanceof FonctionInterface) {
          if (isset($fonction->module_code)){
            $module = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $fonction->module_code));
          } else {
            $module = null;
          }
          $foundFonction->setLibelle($fonction->libelle);
          $foundFonction->setZeusOnly($fonction->zeus_only);
          $foundFonction->setModule($module);
          $this->em->persist($foundFonction);
          $nbFonctionUpdated++;

          // on supprime du tableaux des modules existants
          if (($key = array_search($foundFonction, $fonctionsExistants)) !== false) {
            unset($fonctionsExistants[$key]);
          }
        } else {
          $className = $this->em->getRepository(FonctionInterface::class)->getClassName();
          $newFonction = new $className();
          if ($newFonction instanceof FonctionInterface){
            if (isset($fonction->module_code)){
              $module = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $fonction->module_code));
            } else {
              $module = null;
            }
            $newFonction->setCode($fonction->code);
            $newFonction->setLibelle($fonction->libelle);
            $newFonction->setZeusOnly($fonction->zeus_only);
            $newFonction->setModule($module);
            $this->em->persist($newFonction);
            $nbFonctionAdded++;
          }
        }
      }
      $this->em->flush();

      foreach ($fonctionsExistants as $fonction) {
        $this->em->remove($fonction);
        $nbFonctionDeleted++;
      }

      return array(
        'nb_fonction_updated' => $nbFonctionUpdated,
        'nb_fonction_added' => $nbFonctionAdded,
        'nb_fonction_deleted' => $nbFonctionDeleted
      );
    } else {
      return false;
    }
  }

  /**
   * @return mixed
   */
  public function synchroniserRoles(){
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $url = $this->apiGetRolesPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() === 200) {
      $nbRoleUpdated = 0;
      $nbRoleAdded = 0;
      // récupération de tous les roles
      $roles = json_decode($response->getData());

      foreach ($roles as $_role) {
        $role = $this->em->getRepository(RoleInterface::class)->findOneBy(['code' => $_role->code]);
        if ($role instanceof RoleInterface) {
          $nbRoleUpdated++;
        } else {
          $className = $this->em->getRepository(RoleInterface::class)->getClassName();
          $role = new $className();
          if ($role instanceof RoleInterface){
            $role->setCode($_role->code);
            // Synchro ZEUS ==> ZEUS ONLY !
            $nbRoleAdded++;
          }
        }
        $role->setZeusOnly(true);
        $role->setParDefaut($_role->par_defaut);
        $role->setLibelle($_role->libelle);
        $this->em->persist($role);

        // On supprime tous les modules associés à ce role
        foreach ($role->getModules() as $module) {
          if ($module instanceof RoleModuleInterface) {
            $this->em->remove($module);
          }
        }

        // On récupère tous les modules auxquels ce role est lié
        foreach ($_role->modules as $_module) {
          $module = $this->em->getRepository(ModuleInterface::class)->findOneBy(array('code' => $_module->code));
          if ($module instanceof ModuleInterface) {
            $className = $this->em->getRepository(RoleModuleInterface::class)->getClassName();
            $roleModule = new $className();
            if ($roleModule instanceof RoleModuleInterface){
              $roleModule->setRole($role);
              $roleModule->setModule($module);
              $roleModule->setLibelle($module->getLibelle());
              $roleModule->setOrdre($module->getOrdre());
              $this->em->persist($roleModule);
            }
          }
        }
        // On récupère toutes les fonctions auxquelles ce role est lié
        $fonctions = array();
        foreach ($_role->fonctions as $fonction) {
          $foundFonction = $this->em->getRepository(FonctionInterface::class)->findOneBy(array('code' => $fonction->code));
          if ($foundFonction instanceof FonctionInterface) {
            // si le module a été trouvé, on l'ajoute à la liste des modules
            array_push($fonctions, $foundFonction);
          }
        }
        // On récupère toutes les notifications auxquelles ce role est lié
        $notifications = array();
        foreach ($_role->notifications as $notification) {
          $foundNotification = $this->em->getRepository(NotificationInterface::class)->findOneBy(array('code' => $notification->code));
          if ($foundNotification instanceof NotificationInterface) {
            // si le module a été trouvé, on l'ajoute à la liste des modules
            array_push($notifications, $foundNotification);
          }
        }

        $role->setFonctions($fonctions);
        $role->setNotifications($notifications);
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
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $typeApplication = getenv('TYPE_APPLICATION');
    $url = '/utilisateurs/all?login='.$loginApiDashboard.'&password='.$passwordApiDashboard.'&type_application='.$typeApplication;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() == 200){
      $nbUserUpdated = 0;
      $nbUserAdded = 0;
      // récupération de tous les roles
      $decodedResponse = json_decode($response->getData());
      $users = $decodedResponse->users;
      foreach ($users as $_user) {
        $role = $this->em->getRepository(RoleInterface::class)->findOneBy(['code' => $_user->role_code]);
        if ($role instanceof RoleInterface){
          $user = $this->em->getRepository(UserInterface::class)->findOneBy(['login' => $_user->login]);
          if ($user instanceof UserInterface) {
            $nbUserUpdated++;
          } else {
            $className = $this->em->getRepository(UserInterface::class)->getClassName();
            $user = new $className();
            if ($user instanceof UserInterface){
              $user->setLogin($_user->login);
              $nbUserAdded++;
            }
          }
          $user->setNom($_user->nom);
          $user->setPrenom($_user->prenom);
          $user->setUsername($_user->username);
          $user->setMail($_user->mail);
          $user->setRole($role);
          $user->setUtilisateurZeus(true);
          $this->em->persist($user);
        }
      }
      $this->em->flush();

      return [
        'nb_user_updated' => $nbUserUpdated,
        'nb_user_added' => $nbUserAdded
      ];
    } else {
      return false;
    }
  }

  /**
   * @return mixed
   */
  public function synchroniserAlertes(){
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $url = $this->apiGetAlertesPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() === 200) {
      $nbAlertesMailUpdated = 0;
      $nbAlertesMailAdded = 0;
      // récupération de tous les roles
      $alertes = json_decode($response->getData());

      foreach ($alertes as $alerte) {
        // on récupère ou créé l'alerte
        $alerteFound = $this->em->getRepository(AlerteMailInterface::class)->findOneBy(array('code' => $alerte->code));
        if (!($alerteFound instanceof AlerteMailInterface)) {
          $classAlerteMailName = $this->em->getRepository(AlerteMailInterface::class)->getClassName();
          $alerteFound = new $classAlerteMailName();
          $alerteFound->setCode($alerte->code);
          $nbAlertesMailAdded++;
        }else {
          $nbAlertesMailUpdated++;
        }
        $alerteFound->setLibelle($alerte->libelle);
        $this->em->persist($alerteFound);

        // on ajoute les destinataires
        $destinatairesMail = array();
        foreach ($alerte->utilisateurs as $destinataire) {
          $foundUser = $this->em->getRepository(UserInterface::class)->findOneBy(array('login' => $destinataire->login));
          if ($foundUser instanceof UserInterface) {
            $destinataireFound = $this->em->getRepository(DestinataireMailInterface::class)->findOneBy(array('user' => $foundUser));
            if (!($destinataireFound instanceof DestinataireMailInterface)) {
              $classDestinataireMailName = $this->em->getRepository(DestinataireMailInterface::class)->getClassName();
              $destinataireFound = new $classDestinataireMailName();
              $destinataireFound->setUser($foundUser);
              $this->em->persist($destinataireFound);
              $this->em->flush();
            }
            $destinataireFound->addAlerteMail($alerteFound);
            $this->em->persist($destinataireFound);
            // si le module a été trouvé, on l'ajoute à la liste des modules
            array_push($destinatairesMail, $destinataireFound);

          }
        }

        $alerteFound->setDestinataires($destinatairesMail);
        $this->em->persist($alerteFound);
      }
      $this->em->flush();

      return array(
        'nb_alertes_mail_updated' => $nbAlertesMailUpdated,
        'nb_alertes_mail_added' => $nbAlertesMailAdded
      );
    } else {
      return false;
    }
  }


  /**
   * @return mixed
   */
  public function synchroniserNotifications(){
    $loginApiDashboard = $this->apiZeusCommunicationService->getApiZeusLogin();
    $passwordApiDashboard = $this->apiZeusCommunicationService->getApiZeusPassword();

    $url = $this->apiGetNotificationsPath.'?login='.$loginApiDashboard.'&password='.$passwordApiDashboard;
    $response = $this->apiZeusCommunicationService->sendGetRequest($url);

    if ($response->getHttpCode() === 200) {
      $nbNotificationUpdated = 0;
      $nbNotificationAdded = 0;
      // récupération de tous les roles
      $notifications = json_decode($response->getData());

      foreach ($notifications as $notification) {
        // on récupère ou créé l'alerte
        $notificationFound = $this->em->getRepository(NotificationInterface::class)->findOneBy(array('code' => $notification->code));
        if (!($notificationFound instanceof NotificationInterface)) {
          $classNotificationName = $this->em->getRepository(NotificationInterface::class)->getClassName();
          $notificationFound = new $classNotificationName();
          $notificationFound->setCode($notification->code);
          $nbNotificationAdded++;
        }else {
          $nbNotificationUpdated++;
        }
        $notificationFound->setLibelle($notification->libelle);
        $notificationFound->setZeusOnly($notification->zeus_only);
        $this->em->persist($notificationFound);
      }
      $this->em->flush();

      return array(
        'nb_notifications_updated' => $nbNotificationUpdated,
        'nb_notifications_added' => $nbNotificationAdded
      );
    } else {
      return false;
    }
  }
}