<?php

/**
 * @file
 * Contains \Drupal\default_content\RouteSubscriber.
 */

namespace Drupal\default_content;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds new export route for every content entity.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $definition) {
      if ($definition instanceOf ContentEntityTypeInterface) {
        $link_template = $definition->getLinkTemplate('canonical');
        if (strpos($link_template, '/') !== FALSE) {
          $base_path = '/' . $link_template;
        }
        else {
          // Try to get the route from the current collection.
          if (!$entity_route = $collection->get("entity.$entity_type_id.canonical")) {
            continue;
          }
          $base_path = $entity_route->getPath();
        }

        $path = $base_path . '/export';
        $route = new Route(
          $path,
          [
            '_form' => '\Drupal\default_content\ExportForm',
            '_title' => 'Export',
          ],
          [
            '_entity_access' =>  $entity_type_id . '.view',
            '_permission' => 'use default content export',
          ],
          [
            '_admin_route' => TRUE,
          ]
        );
        $collection->add('entity.' . $entity_type_id . '.default_content_export', $route);
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      RoutingEvents::ALTER => ['onAlterRoutes', 100],
    ];
  }

}
