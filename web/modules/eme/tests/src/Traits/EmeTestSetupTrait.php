<?php

namespace Drupal\Tests\eme\Traits;

use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Test setup trait for EME tests.
 */
trait EmeTestSetupTrait {

  use DrushTestTrait;

  /**
   * A path where the exported module should be saved.
   *
   * @var string
   */
  protected $exportDestination = 'modules/content_migrations';

  /**
   * The machine name for the generated module.
   *
   * @var string
   */
  protected $moduleName;

  /**
   * The human name for the generated module.
   *
   * @var string
   */
  protected $moduleHumanName;

  /**
   * The migration plugin ID prefix of the generated migration plugins.
   *
   * @var string
   */
  protected $migrationPrefix;

  /**
   * The migration group of the generated migration plugins.
   *
   * @var string
   */
  protected $migrationGroup;

  /**
   * The directory where the exported entity data should be stored.
   *
   * @var string
   */
  protected $dataSubdir;

  /**
   * The directory where the exported file assets should be stored.
   *
   * @var string
   */
  protected $fileSubdir;

  /**
   * Returns the destination for the exported module.
   *
   * @return string
   *   The destination where the exported module should be saved.
   */
  protected function getMigrateExportDestination(): string {
    return $this->siteDirectory . '/' . $this->exportDestination;
  }

  /**
   * Sets up variables used for generating content exports.
   */
  protected function setupExportVars() {
    $sites_basename = basename($this->siteDirectory);
    $this->moduleName = "em_{$sites_basename}";
    $this->moduleHumanName = "Entity export {$sites_basename}";
    $this->migrationPrefix = "id_{$sites_basename}";
    $this->migrationGroup = "g_{$sites_basename}";
    $this->dataSubdir = "data_{$sites_basename}";
    $this->fileSubdir = "file_{$sites_basename}";
    $site_directory = $this->container->getParameter('app.root') . '/' . $this->siteDirectory;
    $this->assertDirectoryIsWritable($site_directory);
  }

  /**
   * Checks that migrate status output has all the lines.
   *
   * @param string|string[] $expected_lines
   *   The expected lines in drush output.
   */
  protected function assertDrushOutputHasAllLines($expected_lines) {
    $actual_output = $this->getSimplifiedOutput();

    try {
      foreach ((array) $expected_lines as $expected_line) {
        $this->assertStringContainsString($this->simplifyOutput($expected_line), $actual_output);
      }
    }
    catch (ExpectationFailedException $e) {
      $this->assertEquals(implode("\n", $expected_lines), $this->getOutput());
    }
  }

}
