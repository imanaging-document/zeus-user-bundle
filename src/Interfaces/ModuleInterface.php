<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface ModuleInterface
{
  public function getId();

  public function setId($id);

  public function getCode(): string;

  public function setCode(string $code): void;

  public function getLibelle();

  public function setLibelle($libelle);

  public function getRoute();

  public function setRoute($route);

  public function getOrdre();

  public function setOrdre($ordre);

  public function getNiveau(): int;

  public function setNiveau(int $niveau): void;

  public function isDroite();

  public function setDroite($droite);

  public function isVisible();

  public function setVisible($visible);

  public function getEnfants();

  public function setEnfants($enfants);

  public function getParent();

  public function setParent($parent);

  public function getRoles();

  public function setRoles($roles);

  public function getFonctions();

  public function setFonctions($fonctions);
}