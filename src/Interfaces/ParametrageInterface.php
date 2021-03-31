<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface ParametrageInterface
{
  public function getCle(): string;

  public function setCle(string $cle);

  public function getValeur(): string;

  public function setValeur(string $valeur);
}