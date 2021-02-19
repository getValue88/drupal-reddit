<?php

namespace Drupal\eme\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * An access check which depends on whether the temporary scheme is accessible.
 */
class TemporarySchemeAccessCheck implements AccessInterface {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * Constructs a TemporarySchemeAccessCheck instance.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(StreamWrapperManagerInterface $streamWrapperManager) {
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * Checks access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access() {
    $temporary_is_valid = $this->streamWrapperManager->isValidScheme('temporary');
    $writable_wrappers = $this->streamWrapperManager->getWrappers(StreamWrapperInterface::WRITE);
    if ($temporary_is_valid && array_key_exists('temporary', $writable_wrappers)) {
      return AccessResult::allowed()->setCacheMaxAge(0);
    }

    return AccessResult::forbidden()->setCacheMaxAge(0);
  }

}
