<?php

namespace Drupal\eme\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\eme\Eme;

/**
 * Access check for the archive.
 */
class DownloadAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    if (is_file('temporary://' . Eme::ARCHIVE_NAME)) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }

    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
