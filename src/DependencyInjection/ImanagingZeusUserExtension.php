<?php


namespace Imanaging\ZeusUserBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ImanagingZeusUserExtension extends Extension
{
  /**
   * @param array $configs
   * @param ContainerBuilder $container
   * @throws Exception
   */
  public function load(array $configs, ContainerBuilder $container)
  {
    $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
    $loader->load('services.xml');

    $configuration = $this->getConfiguration($configs, $container);
    $config = $this->processConfiguration($configuration, $configs);

    $definition = $container->getDefinition('imanaging_zeus_user.synchronisation');
    $definition->setArgument(2, $config['api_get_modules_path']);
    $definition->setArgument(3, $config['api_get_roles_path']);
    $definition->setArgument(4, $config['api_get_alertes_path']);
    $definition->setArgument(5, $config['api_get_fonctions_path']);
    $definition->setArgument(6, $config['api_get_notifications_path']);
    $definition->setArgument(7, $config['api_get_users_path']);

    $definition = $container->getDefinition('imanaging_zeus_user.login');
    $definition->setArgument(5, $config['api_connexion_path']);
    $definition->setArgument(6, $config['own_url']);
    $definition->setArgument(7, $config['api_zeus_token']);
  }

  public function getAlias() : string
  {
    return 'imanaging_zeus_user';
  }
}
