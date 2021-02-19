<?php

namespace Drupal\eme_test_module_extension_list;

use Drupal\Core\Extension\ExtensionDiscovery as DefaultExtensionDiscovery;

/**
 * ExtensionDiscovery replacement with reset.
 */
class ExtensionDiscovery extends DefaultExtensionDiscovery {

  /**
   * Resets the discovery's static cache.
   *
   * @return self
   *   The instance with empty static cache.
   */
  public function reset() {
    static::$files = [];
    return $this;
  }

}
