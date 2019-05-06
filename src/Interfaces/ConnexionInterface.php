<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface ConnexionInterface
{
  public function getId(): int;

  public function setId(int $id): void;

  public function getLogin(): string;

  public function setLogin(string $login): void;

  public function getAdresseIp();

  public function setAdresseIp($adresseIp): void;

  public function getStatut();

  public function setStatut($statut): void;

  public function getUser();

  public function setUser($user): void;

  public function getTimeConnexion();

  public function setTimeConnexion(\DateTime $timeConnexion): void;
}