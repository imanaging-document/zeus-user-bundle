<?php

namespace Imanaging\ZeusUserBundle\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CustomZeusAuthenticationException extends AuthenticationException
{
  private $login;
  private $password;

  public function __construct($login, $password)
  {
    $this->login = $login;
    $this->password = $password;
    parent::__construct();
  }

  /**
   * @return mixed
   */
  public function getLogin()
  {
    return $this->login;
  }

  /**
   * @param mixed $login
   */
  public function setLogin($login): void
  {
    $this->login = $login;
  }

  /**
   * @return mixed
   */
  public function getPassword()
  {
    return $this->password;
  }

  /**
   * @param mixed $password
   */
  public function setPassword($password): void
  {
    $this->password = $password;
  }

  public function __serialize(): array
  {
    return [$this->login, $this->password, parent::__serialize()];
  }

  public function __unserialize(array $data): void
  {
    [$this->login, $this->password, $parentData] = $data;
    parent::__unserialize($parentData);
  }

}