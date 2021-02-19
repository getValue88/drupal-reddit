<?php

namespace Drupal\Tests\eme\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eme\Traits\EmeTestSetupTrait;
use Drupal\Tests\eme\Traits\EmeTestTrait;

/**
 * Base class for testing Drush integration.
 */
abstract class DrushTestBase extends BrowserTestBase {

  use EmeTestSetupTrait;
  use EmeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'text',
    'comment',
    'eme',
    'eme_test_module_extension_list',
    'migrate_tools',
  ];

}
