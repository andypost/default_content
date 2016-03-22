<?php

/**
 * @file
 * Contains \Drupal\default_content\DefaultContentServiceProvider.
 */

namespace Drupal\default_content;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * A default content service provider.
 */
class DefaultContentServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container
      ->getDefinition('rest.link_manager.type')
      ->setClass('Drupal\default_content\LinkManager\TypeLinkManager');

    $container
      ->getDefinition('rest.link_manager.relation')
      ->setClass('Drupal\default_content\LinkManager\RelationLinkManager');

    $modules = $container->getParameter('container.modules');
    // @todo Get rid of after https://www.drupal.org/node/2543726
    if (isset($modules['taxonomy'])) {
      // Add a normalizer service for term entities.
      $service_definition = new Definition('Drupal\default_content\Normalizer\TermEntityNormalizer', [
        new Reference('rest.link_manager'),
        new Reference('entity.manager'),
        new Reference('module_handler'),
      ]);
      // The priority must be higher than that of
      // serializer.normalizer.entity.hal in hal.services.yml
      $service_definition->addTag('normalizer', ['priority' => 30]);
      $container->setDefinition('default_content.normalizer.taxonomy_term.halt', $service_definition);
    }
  }

}
