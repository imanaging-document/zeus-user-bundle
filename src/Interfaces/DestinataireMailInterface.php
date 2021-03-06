<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface DestinataireMailInterface
{
  public function getId();

  public function setId($id);

  public function getNom();

  public function setNom(string $nom);

  public function getPrenom();

  public function setPrenom(string $prenom);

  public function getMail();

  public function setMail(string $mail);

  public function getNumero();

  public function setNumero(string $numero);

  public function setUser(UserInterface $user);

  public function getUser();

  public function addAlerteMail(AlerteMailInterface $alerteMail);

  public function removeAlerteMail(AlerteMailInterface $alerteMail);

  public function setAlertesMail($alertes);

  public function getAlertesMail();
}