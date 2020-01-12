<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface AlerteNotificationInterface
{
  public function getId();

  public function setId($id);

  public function getCode(): string;

  public function setCode(string $code): void;

  public function getLibelle();

  public function setLibelle($libelle);

  public function getRoles();

  public function setRoles($roles);
}