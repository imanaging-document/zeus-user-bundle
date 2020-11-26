<?php


namespace Imanaging\ZeusUserBundle\Interfaces;

interface RouteInterface
{
  public function getId();

  public function setId(int $id);

  public function getRoute();

  public function setRoute(string $route);

  public function getCodeModule();

  public function setCodeModule(string $codeModule);
}