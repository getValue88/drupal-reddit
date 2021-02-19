<?php

namespace Drupal\Tests\eme\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * A no-js settings form test for EME.
 *
 * @group eme
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'eme',
    'node',
  ];

  /**
   * Tests config form functionality without JavaScript.
   */
  public function testConfigForm() {
    $this->drupalLogin($this->rootUser);
    $session = $this->assertSession();
    $session->statusCodeEquals(200);

    $this->assertEquals([], $this->config('eme.settings')->get());

    $this->drupalGet(Url::fromRoute('eme.settings'));

    $session->fieldExists('Module name')->setValue('default_content');
    $session->fieldExists('Module human name')->setValue('Default Content');
    $session->fieldExists('Migration prefix')->setValue('default');
    $session->fieldExists('Migration group')->setValue('dci');

    $session->checkboxNotChecked('node');
    $session->checkboxNotChecked('block_content');
    $session->fieldExists('block_content')->check();
    $session->fieldExists('new_type')->setValue('type_to_ignore_1');
    $session->buttonExists('Ignore this type')->press();

    $this->assertEmpty($session->fieldExists('new_type')->getValue());
    $session->checkboxChecked('block_content');
    $session->checkboxChecked('type_to_ignore_1');
    $session->fieldExists('new_type')->setValue('type_to_ignore_2');

    $session->buttonExists('Save configuration')->press();

    $this->assertEquals([
      'module_machine' => 'default_content',
      'module_human' => 'Default Content',
      'migration_prefix' => 'default',
      'migration_group' => 'dci',
      'ignored_entity_types' => [
        'block_content',
        'type_to_ignore_1',
        'type_to_ignore_2',
      ],
    ], $this->config('eme.settings')->get());

    $this->drupalGet(Url::fromRoute('eme.settings'));

    $session->fieldValueEquals('Module name', 'default_content');
    $session->fieldValueEquals('Module human name', 'Default Content');
    $session->fieldValueEquals('Migration prefix', 'default');
    $session->fieldValueEquals('Migration group', 'dci');

    $session->checkboxNotChecked('node');
    $session->checkboxChecked('block_content');
    $session->checkboxChecked('type_to_ignore_1');
    $session->checkboxChecked('type_to_ignore_2');

    $session->fieldExists('type_to_ignore_1')->uncheck();
    $session->fieldExists('type_to_ignore_2')->uncheck();
    $session->fieldExists('block_content')->uncheck();
    $this->assertFalse($session->fieldExists('type_to_ignore_1')->isChecked());
    $this->assertFalse($session->fieldExists('type_to_ignore_2')->isChecked());
    $this->assertFalse($session->fieldExists('block_content')->isChecked());
    $this->assertEmpty($session->fieldExists('new_type')->getValue());

    $session->fieldExists('Module name')->setValue('');
    $session->fieldExists('Module human name')->setValue('');
    $session->fieldExists('Migration prefix')->setValue('');
    $session->fieldExists('Migration group')->setValue('');

    $session->buttonExists('Save configuration')->press();

    $this->assertEquals([], $this->config('eme.settings')->get());
  }

}
