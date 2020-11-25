<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface RoleInterface
{
  public function addModule(ModuleInterface $module);

  public function removeModule(ModuleInterface $module);

  public function getId();

  public function setId($id);

  public function getCode();

  public function setCode($code);

  public function getLibelle();

  public function setLibelle($libelle);

  public function isZeusOnly();

  public function setZeusOnly($zeusOnly);

  public function isParDefaut();

  public function setParDefaut($parDefaut);

  public function getModules();

  public function setModules($modules);

  public function getFonctions();

  public function setFonctions($fonctions);

  public function getNotifications();

  public function setNotifications($notifications);

  public function canDo($codeFonction);

  public function canAccess($moduleId);

  public function getLibelleForModule($moduleId);

  public function getOrdreForModule($moduleId);
}
