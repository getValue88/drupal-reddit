<?php

namespace Drupal\Tests\eme\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Tests EME and Drush compatibility – verifies usage steps in README.
 *
 * @group eme
 */
class DrushOperationsTest extends DrushTestBase {

  /**
   * Test export with Drush.
   */
  public function testExportDrush() {
    $this->setupExportVars();

    $this->createTestEntityTypes();
    $this->createDefaultTestContent();

    // Let's export the test content.
    $this->drush('eme:export', [], [
      'types' => 'node,comment,user',
      'destination' => $this->getMigrateExportDestination(),
      'module' => $this->moduleName,
      'name' => $this->moduleHumanName,
      'id-prefix' => $this->migrationPrefix,
      'group' => $this->migrationGroup,
      'data-dir' => $this->dataSubdir,
      'file-dir' => $this->fileSubdir,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->install([$this->moduleName]);

    $exp_group_desc = "{$this->migrationGroup} ({$this->migrationGroup})";

    $this->drush('migrate:status', [], [
      'group' => $this->migrationGroup,
      'fields' => 'group,id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$exp_group_desc}  {$this->migrationPrefix}_user             Idle  4  0  4",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_article     Idle  2  0  2",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_page        Idle  1  0  1",
      "{$exp_group_desc}  {$this->migrationPrefix}_comment_article  Idle  2  0  2",
    ]);

    // Let's export only comments and their dependent entities.
    $this->drush('eme:export', [], [
      'types' => 'comment',
      'update' => $this->moduleName,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $this->drush('cache:rebuild');

    $this->drush('migrate:status', [], [
      'group' => $this->migrationGroup,
      'fields' => 'group,id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$exp_group_desc}  {$this->migrationPrefix}_user             Idle  2  0  2",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_article     Idle  1  0  1",
      "{$exp_group_desc}  {$this->migrationPrefix}_comment_article  Idle  2  0  2",
    ]);

    // Delete the test content.
    $this->deleteTestContent();
    $this->resetAll();

    // Uninstall and reinstall node and comment.
    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->uninstall(['node']);
    $module_installer->uninstall(['comment']);
    $this->resetAll();
    $module_installer->install(['node', 'comment']);

    $this->createTestEntityTypes();

    $this->assertEmpty(\Drupal::entityTypeManager()->getStorage('node')->loadMultiple());
    $expected_user_ids = [
      // Anonymous user.
      0 => 0,
      // Root user.
      1 => 1,
    ];
    $this->assertEquals($expected_user_ids, array_keys(\Drupal::entityTypeManager()->getStorage('user')->loadMultiple()));

    // Let's import the test content.
    $this->drush('migrate:import', ['--execute-dependencies'], [
      'group' => $this->migrationGroup,
    ]);

    $this->drush('migrate:status', [], [
      'group' => $this->migrationGroup,
      'fields' => 'group,id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$exp_group_desc}  {$this->migrationPrefix}_user             Idle  2  2  0",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_article     Idle  1  1  0",
      "{$exp_group_desc}  {$this->migrationPrefix}_comment_article  Idle  2  2  0",
    ]);

    $this->assertTestContent();

    // Create additional test content and verify that it is added to the content
    // export.
    $this->createAdditionalTestContent();
    $this->drush('eme:export', [], [
      'update' => $this->moduleName,
    ]);
    $this->assertOutputEquals('🎉 Export finished.');

    $this->assertComment1Json(implode('/', [
      DRUPAL_ROOT,
      $this->getMigrateExportDestination(),
      $this->moduleName,
      $this->dataSubdir,
      'comment',
      'article',
      'comment-1.json',
    ]));

    // Delete the previously imported and the additional test content.
    $this->drush('migrate:rollback', [], [
      'group' => $this->migrationGroup,
    ]);
    $this->deleteTestContent();
    $this->resetAll();

    // Uninstall and reinstall node and comment.
    $module_installer = $this->container->get('module_installer');
    assert($module_installer instanceof ModuleInstallerInterface);
    $module_installer->uninstall(['node']);
    $module_installer->uninstall(['comment']);
    $this->resetAll();
    $module_installer->install(['node', 'comment']);

    $this->createTestEntityTypes();

    $this->drush('cache:rebuild');

    $this->drush('migrate:status', [], [
      'group' => $this->migrationGroup,
      'fields' => 'group,id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$exp_group_desc}  {$this->migrationPrefix}_user             Idle  3  0  3",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_article     Idle  1  0  1",
      "{$exp_group_desc}  {$this->migrationPrefix}_comment_article  Idle  3  0  3",
    ]);

    // Let's import the updated test export.
    $this->drush('migrate:import', ['--execute-dependencies'], [
      'group' => $this->migrationGroup,
    ]);

    $this->drush('migrate:status', [], [
      'group' => $this->migrationGroup,
      'fields' => 'group,id,status,total,imported,unprocessed',
    ]);
    $this->assertDrushOutputHasAllLines([
      "{$exp_group_desc}  {$this->migrationPrefix}_user             Idle  3  3  0",
      "{$exp_group_desc}  {$this->migrationPrefix}_node_article     Idle  1  1  0",
      "{$exp_group_desc}  {$this->migrationPrefix}_comment_article  Idle  3  3  0",
    ]);

    $this->assertTestContent();
  }

}
