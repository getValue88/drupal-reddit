<?php

declare(strict_types=1);

namespace Drupal\eme\Utility;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\eme\Eme;

/**
 * Collection related utilities.
 */
class EmeUtils {

  /**
   * Returns the exportable entity types.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return array
   *   An array of descriptive labels keyed by the entity type ID.
   */
  public static function getContentEntityTypes(EntityTypeManagerInterface $entity_type_manager): array {
    $types_to_exclude = Eme::getExcludedTypes();
    $content_entity_types = array_reduce($entity_type_manager->getDefinitions(), function (array $carry, EntityTypeInterface $definition) use ($types_to_exclude) {
      if (!$definition instanceof ContentEntityType) {
        return $carry;
      }
      $entity_type_id = $definition->id();
      if (in_array($entity_type_id, $types_to_exclude, TRUE)) {
        return $carry;
      }
      $carry[$definition->id()] = t('@plural-label (<code>@provider</code>)', [
        '@plural-label' => ucfirst((string) $definition->getPluralLabel()),
        '@provider' => $definition->getProvider(),
      ]);
      return $carry;
    }, []);
    ksort($content_entity_types);

    return $content_entity_types;
  }

  /**
   * Returns metadata of the discovered content export modules.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   *
   * @return array
   *   An array of content export module metadata keyed by module name.
   */
  public static function getExports(ModuleExtensionList $module_extension_list): array {
    return array_reduce($module_extension_list->reset()->getList(), function (array $carry, Extension $extension) {
      if (
        property_exists($extension, 'info') &&
        is_array($extension->info) &&
        isset($extension->info['eme_settings'])
      ) {
        $module_name = $extension->getName();
        $path = preg_replace("/{$module_name}$/", '', $extension->subpath);
        $carry[$module_name] = $extension->info['eme_settings'] + [
          'name' => $extension->info['name'],
          'path' => trim(
            implode('/', array_filter([
              $extension->origin,
              $path,
            ])),
            '/'
          ),
        ];
      }
      return $carry;
    }, []);
  }

}
