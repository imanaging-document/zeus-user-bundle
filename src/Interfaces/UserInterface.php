<?php

namespace Imanaging\ZeusUserBundle\Interfaces;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface as UserInterfaceAlias;

interface UserInterface extends UserInterfaceAlias, PasswordAuthenticatedUserInterface
{
  public function getId();

  public function setId($id);

  public function getNom();

  public function setNom($nom);

  public function getPrenom();

  public function setPrenom($prenom);

  public function getLogin();

  public function setLogin($login);

  public function getUsername();

  public function setUsername($username);

  public function getPassword(): ?string;

  public function setPassword(string $password): void;

  public function isActif(): bool;

  public function setActif(bool $actif): void;

  public function isUtilisateurZeus(): bool;

  public function setUtilisateurZeus(bool $utilisateurZeus): void;

  public function isUtilisateurCore(): bool;

  public function setUtilisateurCore(bool $utilisateurZeus): void;

  public function getMail();

  public function setMail($mail);

  public function getRole();

  public function setRole($role);
}
