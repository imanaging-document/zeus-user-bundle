ZeusUserBundle
============

This bundle allows different imanaging-document applications to sync roles, modules, users and log Zeus Users in your application.

It requires the [imanaging-document/api-communication-bundle](https://github.com/imanaging-document/api-communication-bundle).

This bundle can't be used outside an imanaging-document application.

Install the bundle with:

```console
$ composer require imanaging-document/zeus-user-bundle
```

Configuration
----------------------------------

Bundle configuration

You have to create a ```config/packages/imanaging_zeus_user.yaml``` file:
```yaml
imanaging_zeus_user:
    api_get_modules_path: ~
    api_get_roles_path: ~
```

These fields are required and they must start with a "/".

Doctrine configuration :
```yaml
doctrine:
    orm:
        resolve_target_entities:
            Imanaging\ZeusUserBundle\Interfaces\UserInterface: App\Entity\User
            Imanaging\ZeusUserBundle\Interfaces\ConnexionStatutInterface: App\Entity\ConnexionStatut
            Imanaging\ZeusUserBundle\Interfaces\ConnexionInterface: App\Entity\Connexion
            Imanaging\ZeusUserBundle\Interfaces\ModuleInterface: App\Entity\Module
            Imanaging\ZeusUserBundle\Interfaces\RoleInterface: App\Entity\Role
```

You must set the entities you want to use.

Basic usage
----------------------------------
Get the Synchronisation and Login service :
```php
use Imanaging\ZeusUserBundle\Synchronisation;
use Imanaging\ZeusUserBundle\Login;

class MyBeautifulService
{
  private ...
  private $synchronisationService;
  private $loginService;
  private ...
  
  /**
   * ...
   * @param Synchronisation $synchronisationService
   * ...
   */
  public function __construct(..., Synchronisation $synchronisationService, Login $loginService, ...){
    ...
    $this->synchronisationService = $synchronisationService;
    $this->loginService = $loginService;
    ...
  }
  ...
}
```

Synchronisation
```php
$result = $this->synchronisationService->synchroniserModules();
if (is_array($result)){
  $output->writeln("<fg=green>".$result['nb_module_updated']." modules ont etes mis a jour.</>");
  $output->writeln("<fg=green>".$result['nb_module_added']." modules ont etes crees.</>");
  $output->writeln("<fg=green>".$result['nb_module_deleted']." modules ont etes supprimees.</>");
} else {
  $output->writeln("<fg=red>La mise à jour des modules a échoué.</>");
}

$result = $this->synchronisationService->synchroniserRoles();
if (is_array($result)){
  $output->writeln("<fg=green>".$result['nb_role_updated']." roles ont etes mis a jour.</>");
  $output->writeln("<fg=green>".$result['nb_role_added']." roles ont etes crees.</>");
} else {
  $output->writeln("<fg=red>La mise à jour des roles a échoué.</>");
}

$result = $this->synchronisationService->synchroniserUsers();
if (is_array($result)){
  $output->writeln("<fg=green>".$result['nb_user_updated']." utilisateurs ont etes mis a jour.</>");
  $output->writeln("<fg=green>".$result['nb_user_added']." utilisateurs ont etes crees.</>");
} else {
  $output->writeln("<fg=red>La mise à jour des utilisateurs a échoué.</>");
}
```

Login
```php
$user = $loginService->canLog("LOGIN", "P@SSW0RD", "127.0.0.1");
if ($user instanceof User){
  if ($user->isUtilisateurZeus()) {
    $token = new UsernamePasswordToken($user, 'password', "secured_area", array('ROLE_USER'));
  } else {
    $token = new UsernamePasswordToken($user, $user->getPassword(), "secured_area", array('ROLE_USER'));
  }
  // Set the token
  $this->get("security.token_storage")->setToken($token);
  $event = new InteractiveLoginEvent($request, $token);
  $eventDispatcher->dispatch("security.interactive_login", $event);

  // Create connexion success history
  $loginService->createConnexion($user, $user->getLogin(), 'connexion_reussie');

  ...
}
```