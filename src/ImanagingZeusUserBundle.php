<?php


namespace Imanaging\ZeusUserBundle;


use Imanaging\ZeusUserBundle\DependencyInjection\ImanagingZeusUserExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImanagingZeusUserBundle extends Bundle
{
  /**
   * Overridden to allow for the custom extension alias.
   */
  public function getContainerExtension()
  {
    if (null === $this->extension) {
      $this->extension = new ImanagingZeusUserExtension();
    }
    return $this->extension;
  }
}