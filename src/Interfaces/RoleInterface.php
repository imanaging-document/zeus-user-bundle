<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface RoleInterface
{
  public function addModule(ModuleInterface $module);

  public function removeModule(ModuleInterface $module);

  public function getId();

  public function setId($id);

  public function getLibelle();

  public function setLibelle($libelle);

  public function getUser();

  public function setUser($user);

  public function getModules();

  public function setModules($modules);

  public function getFonctions();

  public function setFonctions($fonctions);

  public function getNotifications();

  public function setNotifications($notifications);

  public function canDo();

  public function canAccess();
}
