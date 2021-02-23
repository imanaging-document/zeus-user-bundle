<?php


namespace Imanaging\ZeusUserBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder('imanaging_zeus_user');
    $rootNode = $treeBuilder->getRootNode();
    $rootNode
      ->children()
        ->variableNode('api_get_modules_path')->defaultValue("")->end()
        ->variableNode('api_get_roles_path')->defaultValue("")->end()
        ->variableNode('api_get_alertes_path')->defaultValue("")->end()
        ->variableNode('api_get_fonctions_path')->defaultValue("")->end()
        ->variableNode('api_get_notifications_path')->defaultValue("")->end()
        ->variableNode('api_get_users_path')->defaultValue("")->end()
        ->variableNode('api_connexion_path')->defaultValue("")->end()
        ->variableNode('own_url')->defaultValue("'%env(OWN_URL)%")->end()
      ->end()
    ;

    return $treeBuilder;
  }
}
