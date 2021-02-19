<?php

namespace Drupal\eme;

/**
 * Helpers of Entity Migrate Export.
 *
 * @internal
 */
final class Eme {

  /**
   * The name of the temporary archive.
   *
   * @const string
   */
  const ARCHIVE_NAME = 'eme.tar.gz';

  /**
   * Eme's config name.
   *
   * @const string
   */
  const CONFIG_NAME = 'eme.settings';

  /**
   * Eme's state key.
   *
   * @const string
   */
  const EXPORT_FORM_STATE = 'eme.export_form_state';

  /**
   * The default name used to identify the module and the generated migrations.
   *
   * @const string
   */
  const ID_NAME = 'eme_migrate';

  /**
   * The default human name of the generated migration module.
   *
   * @const string
   */
  const HUMAN_NAME = 'Content Entity Migration';

  /**
   * The default group of the generated migrations.
   *
   * @const string
   */
  const GROUP_NAME = 'eme';

  /**
   * The default data source directory.
   *
   * @const string
   */
  const DATA_SUBDIR = 'data';

  /**
   * The default directory of the files.
   *
   * @const string
   */
  const FILE_ASSETS_SUBDIR = 'assets';

  /**
   * Returns the subdirectory where file assets are saved.
   *
   * @return string
   *   The subdirectory where file assets are saved.
   */
  public static function getDataSubdir(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('data_subdir');
    return !empty($config)
      ? $config
      : self::DATA_SUBDIR;
  }

  /**
   * Returns the subdirectory where file assets are saved.
   *
   * @return string
   *   The subdirectory where file assets are saved.
   */
  public static function getFileAssetsSubdir(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('file_subdir');
    return !empty($config)
      ? $config
      : self::FILE_ASSETS_SUBDIR;
  }

  /**
   * Returns the machine name of the generated module.
   *
   * @return string
   *   The machine name of the generated module.
   */
  public static function getModuleName(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('module_machine');
    return !empty($config)
      ? $config
      : self::ID_NAME;
  }

  /**
   * Returns the human name of the generated module.
   *
   * @return string
   *   The human name of the generated module.
   */
  public static function getModuleHumanName(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('module_human');
    return !empty($config)
      ? $config
      : self::HUMAN_NAME;
  }

  /**
   * Returns the prefix added to the migrations ID.
   *
   * @return string
   *   The prefix added to the migrations ID.
   */
  public static function getMigrationPrefix(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('migration_prefix');
    return !empty($config)
      ? $config
      : self::ID_NAME;
  }

  /**
   * Returns the prefix added to the migrations ID.
   *
   * @return string
   *   The prefix added to the migrations ID.
   */
  public static function getMigrationGroup(): string {
    $config = \Drupal::config(self::CONFIG_NAME)->get('migration_group');
    return !empty($config)
      ? $config
      : self::GROUP_NAME;
  }

  /**
   * Returns the list of entity types to exclude.
   *
   * @return string[]
   *   The list of entity types to exclude.
   */
  public static function getExcludedTypes(): array {
    return \Drupal::config(self::CONFIG_NAME)->get('ignored_entity_types') ?? [];
  }

}
