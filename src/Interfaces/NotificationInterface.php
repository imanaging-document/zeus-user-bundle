<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface NotificationInterface
{
  public function getId();

  public function setId($id);

  public function getCode(): string;

  public function setCode(string $code): void;

  public function getLibelle();

  public function setLibelle($libelle);

  public function isZeusOnly();

  public function setZeusOnly($zeusOnly);

  public function getRoles();

  public function setRoles($roles);
}