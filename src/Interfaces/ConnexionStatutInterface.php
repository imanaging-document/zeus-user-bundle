<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface ConnexionStatutInterface
{

  public function getId(): int;

  public function setId(int $id): void;

  public function getCode(): string;

  public function setCode(string $code): void;

  public function getLibelle(): string;

  public function setLibelle(string $libelle): void;
}