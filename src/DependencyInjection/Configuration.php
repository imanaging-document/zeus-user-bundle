<?php


namespace Imanaging\ZeusUserBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
  public function getConfigTreeBuilder()
  {
    $treeBuilder = new TreeBuilder();
    $rootNode = $treeBuilder->root('imanaging_zeus_user');
    $rootNode
      ->children()
        ->variableNode('api_get_modules_path')->defaultValue("")->end()
        ->variableNode('api_get_roles_path')->defaultValue("")->end()
      ->end()
    ;

    return $treeBuilder;
  }
}