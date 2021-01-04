<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface RoleModuleInterface
{
  public function getId();

  public function setId($id);

  public function getLibelle();

  public function setLibelle($libelle);

  public function getOrdre();

  public function setOrdre($ordre);

  public function isAcces(): bool;

  public function setAcces(bool $acces);

  public function getRole();

  public function setRole(RoleInterface $role);

  public function getModule();

  public function setModule(ModuleInterface $module);
}