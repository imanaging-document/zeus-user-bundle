<?php

namespace Imanaging\ZeusUserBundle\Interfaces;

use Symfony\Component\Security\Core\User\UserInterface as UserInterfaceAlias;

interface UserInterface extends UserInterfaceAlias
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

  public function getPassword();

  public function setPassword(string $password): void;

  public function isUtilisateurZeus(): bool;

  public function setUtilisateurZeus(bool $utilisateurZeus): void;

  public function getMail();

  public function setMail($mail);

  public function getRole();

  public function setRole($role);
}