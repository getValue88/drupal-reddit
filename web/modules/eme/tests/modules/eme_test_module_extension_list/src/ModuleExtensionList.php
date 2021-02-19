<?php

namespace Drupal\eme_test_module_extension_list;

use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ModuleExtensionList as DefaultModuleExtensionList;
use Symfony\Component\HttpFoundation\Request;

/**
 * A replacement ModuleExtensionList for EME tests.
 */
class ModuleExtensionList extends DefaultModuleExtensionList {

  /**
   * {@inheritdoc}
   */
  protected function getExtensionDiscovery() {
    $discovery = parent::getExtensionDiscovery();
    $profile_directories = $this->getActiveProfile()
      ? $this->getProfileDirectories($discovery)
      : NULL;
    $site_path = DrupalKernel::findSitePath(Request::createFromGlobals(), TRUE, DRUPAL_ROOT);

    $extension_discovery = new ExtensionDiscovery($this->root, FALSE, $profile_directories, $site_path);
    return $extension_discovery->reset();
  }

}
