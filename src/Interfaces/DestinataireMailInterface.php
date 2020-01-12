<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface DestinataireMailInterface
{
  public function getId();

  public function setId($id);

  public function setUser(UserInterface $user);

  public function getUser();

  public function addAlerteMail(AlerteMailInterface $alerteMail);

  public function removeAlerteMail(AlerteMailInterface $alerteMail);

  public function setAlertesMail($alertes);

  public function getAlertesMail();
}