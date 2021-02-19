<?php

namespace Drupal\eme_test_module_extension_list;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides the module extension list service.
 */
class EmeTestModuleExtensionListServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('extension.list.module')) {
      $definition = $container->getDefinition('extension.list.module');
      $definition->setClass(ModuleExtensionList::class);
    }
  }

}
