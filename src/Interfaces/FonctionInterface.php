<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface FonctionInterface
{
  public function getId();

  public function setId($id);

  public function getCode(): string;

  public function setCode(string $code): void;

  public function getLibelle();

  public function setLibelle($libelle);

  public function isZeusOnly();

  public function setZeusOnly($zeusOnly);

  public function getModule();

  public function setModule($module);
}
