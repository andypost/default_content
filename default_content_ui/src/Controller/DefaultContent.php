<?php

namespace Drupal\default_content_ui\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Url;
use Drupal\default_content\DefaultContentScanner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Renders default content status.
 */
class DefaultContent extends ControllerBase {

  /**
   * The file system scanner.
   *
   * @var \Drupal\default_content\DefaultContentScanner
   */
  protected $scanner;

  /**
   * Renders list of modules with default content.
   *
   * @return array
   *   Renderable array.
   */
  public function overview() {
    $build = [];
    foreach ($this->moduleHandler()->getModuleList() as $extension) {
      $files = $this->getExtensionFiles($extension);
      if (empty($files['info']) && empty($files['scan'])) {
        continue;
      }
      $details = [
        'module' => [
          '#type' => 'link',
          '#title' => new FormattableMarkup('@extension (@type)', [
            '@extension' => $extension->getName(),
            '@type' => $extension->getType(),
          ]),
          '#url' => Url::fromRoute('default_content_ui.extension', [
            'extension' => $extension->getName(),
          ]),
        ],
      ];
      $details['info'] = [
        '#theme' => 'item_list',
        '#title_display' => 'before',
        '#title' => $this->t('Info file defined'),
        '#items' => array_keys($files['info']),
      ];
      $details['scan'] = [
        '#theme' => 'item_list',
        '#title_display' => 'after',
        '#title' => $this->t('Scanned files'),
        '#items' => array_keys($files['scan']),
      ];
      $build[$extension->getName()] = $details;
    }
    return $build;
  }

  /**
   * Populates list of default content in extension.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to examine.
   *
   * @return array
   *   Array of keys with found default content.
   */
  protected function getExtensionFiles(Extension $extension) {
    $result = [
      'info' => [],
      'scan' => [],
    ];
    $info = $this->getInfoParser()->parse($extension->getPathname());
    if (isset($info['default_content'])) {
      $result['info'] = $info['default_content'];
    }
    $folder = $extension->getPath() . '/content';
    if (file_exists($folder) && is_dir($folder)) {
      $types = $this->entityTypeManager()->getDefinitions();
      foreach ($types as $id => $type) {
        $dir = $folder . '/' . $id;
        if (file_exists($dir) && is_dir($dir)) {
          if ($files = $this->scanner()->scan($dir)) {
            foreach ($files as $file) {
              $result['scan'][$id][$file->name] = $file->uri;
            }
          }
        }
      }
    }
    return $result;
  }

  /**
   * Returns info file parser.
   *
   * @return \Drupal\Core\Extension\InfoParserInterface
   *   The info file parser.
   */
  protected function getInfoParser() {
    return \Drupal::service('info_parser');
  }

  /**
   * Utility to get a default content scanner.
   *
   * @return \Drupal\default_content\DefaultContentScanner
   *   A system listing implementation.
   */
  protected function scanner() {
    if ($this->scanner) {
      return $this->scanner;
    }
    return new DefaultContentScanner();
  }

  /**
   * Exports all of the content defined in a extension's info file.
   *
   * @param string $extension
   *   The name of the extension.
   *
   * @return array
   *   Renderable array.
   */
  public function getByExtension($extension) {
    $module_handler = $this->moduleHandler();
    if ($extension && $module_handler->moduleExists($extension)) {
      $extension = $module_handler->getModule($extension);
      $files = $this->getExtensionFiles($extension);
      $build = [];
      $types = $this->entityTypeManager()->getDefinitions();
      foreach ($types as $id => $type) {
        if (empty($files['info'][$id]) && empty($files['scan'][$id])) {
          continue;
        }

        $details = [
          '#type' => 'details',
          '#title' => new FormattableMarkup('@label (@entity_type) - @count_info (@count_scan)', [
            '@label' => $type->getLabel(),
            '@entity_type' => $id,
            '@count_info' => isset($files['info'][$id]) ? count($files['info'][$id]) : 0,
            '@count_scan' => isset($files['scan'][$id]) ? count($files['scan'][$id]) : 0,
          ]),
        ];

        if (!empty($files['info'][$id])) {
          $table = [
            '#type' => 'table',
            '#header' => [
              'uuid' => $this->t('Uuid'),
              'status' => $this->t('Status'),
            ],
            '#caption' => $this->t('Defined in info'),
            '#empty' => $this->t('None'),
          ];
          $rows = [];
          foreach ($files['info'][$id] as $uuid) {
            $entity = $this->entityManager()
              ->loadEntityByUuid($id, $uuid);
            $rows[$uuid] = [
              'uuid' => ['data' => $uuid],
              'status' => [
                'data' => [
                  '#markup' => ($entity) ? $this->t('Imported as @id', [
                    '@id' => $entity->id(),
                  ]) : isset($files['scan'][$id][$uuid . '.json']) ? $this->t('Import new') : $this->t('Not found'),
                ],
              ],
            ];
          }
          $table['#rows'] = $rows;
          $details['info'] = $table;
        }
        if (!empty($files['scan'][$id])) {
          $table = [
            '#type' => 'table',
            '#header' => [
              'uuid' => $this->t('Uuid'),
              'status' => $this->t('Status'),
            ],
            '#caption' => $this->t('Found in extension'),
            '#empty' => $this->t('None'),
          ];
          $rows = [];
          foreach ($files['scan'][$id] as $name => $uri) {
            $uuid = substr($name, 0, -5);

            if (isset($details['info']['#rows'][$uuid])) {
              continue;
            }
            $entity = $this->entityManager()
              ->loadEntityByUuid($id, $uuid);
            $rows[$uuid] = [
              'uuid' => ['data' => $uuid],
              'status' => [
                'data' => [
                  '#markup' => ($entity) ? $this->t('Imported as @id', [
                    '@id' => $entity->id(),
                  ]) : $uri,
                ],
              ],
            ];
          }
          $table['#rows'] = $rows;
          $details['scan'] = $table;
        }
        $build[$id] = $details;
      }
      return $build;
    }
    throw new AccessDeniedHttpException();
  }

}
