<?php

namespace Drupal\eme\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\eme\Eme;
use Drupal\eme\EmeProcessor;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeUtils;
use Drush\Commands\DrushCommands;

/**
 * Drush commands of Entity Migrate Export.
 */
class EmeCommands extends DrushCommands {

  /**
   * Info about discovered previous exports.
   *
   * @var array[]
   */
  protected $discoveredExports;

  /**
   * List of the discovered modules.
   *
   * @var string[]
   */
  protected $discoveredModules;

  /**
   * The export batch runner.
   *
   * @var \Drupal\eme\InterfaceAwareExportBatchRunner
   */
  protected $batchRunner;

  /**
   * The exportable content entity types (prepared labels keyed by the ID).
   *
   * @var string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[]
   */
  protected $contentEntityTypes;

  /**
   * Construct an EmeCommands instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner) {
    parent::__construct();
    $this->discoveredExports = EmeUtils::getExports($module_list);
    $this->discoveredModules = array_keys($module_list->reset()->getList());
    $this->contentEntityTypes = EmeUtils::getContentEntityTypes($entity_type_manager);
    $this->batchRunner = $batch_runner;
  }

  /**
   * Exports content to migration.
   *
   * @param array $options
   *   An associative array of options whose values come from cli.
   *
   * @option types
   *   IDs of entity types to export, separated by commas.
   * @option destination
   *   The destination of the module. Defaults to 'modules/custom'.
   * @option module
   *   The name of the module to export to.
   * @option name
   *   The human name of the module to export to.
   * @option id-prefix
   *   The "base" ID of the generated migrations.
   * @option group
   *   The group of the generated migrations.
   * @option data-dir
   *   The base directory where data sources should be saved.
   * @option file-dir
   *   The base directory where file assets should be saved.
   * @option update
   *   The name of the module to update.
   *
   * @usage eme:export --module demo_content --types node,block_content --destination profiles/modules/custom
   *   Export all custom blocks, nodes and their dependencies to a new module at
   *   location DRUPAL_ROOT/profiles/modules/custom
   *
   * @usage eme:export --update demo_content
   *   Refresh the previously created export which module name is
   *   "demo_content".
   *
   * @command eme:export
   * @aliases emex
   */
  public function export(array $options = [
    'types' => NULL,
    'destination' => NULL,
    'module' => NULL,
    'name' => NULL,
    'id-prefix' => NULL,
    'group' => NULL,
    'data-dir' => NULL,
    'file-dir' => NULL,
    'update' => NULL,
  ]) {
    $given_options = array_filter($options);
    $types_to_export = $given_options['types']
      ? array_filter(explode(',', $given_options['types']))
      : NULL;
    $module_name = $given_options['module'] ?? Eme::getModuleName();
    $human_name = $given_options['name'] ?? Eme::getModuleHumanName();
    $migration_prefix = $given_options['id-prefix'] ?? Eme::getMigrationPrefix();
    $migration_group = $given_options['group'] ?? Eme::getMigrationGroup();
    $data_subdir = $given_options['data-dir'] ?? Eme::getDataSubdir();
    $file_subdir = $given_options['file-dir'] ?? Eme::getFileAssetsSubdir();
    $destination = !empty($options['destination'])
      ? trim($given_options['destination'], '/')
      : 'modules/custom';

    // Update does not allows override anything but the exported entity types.
    if ($options['update']) {
      if (!array_key_exists($options['update'], $this->discoveredExports)) {
        $this->logger()->error(dt('The specified export module does not exist.'));
      }
      $module_name = $options['update'];
      // Update does not allows override anything but the exported entity types.
      $types_to_export = $types_to_export ?? $this->discoveredExports[$module_name]['types'];
      $human_name = $this->discoveredExports[$module_name]['name'];
      $migration_prefix = $this->discoveredExports[$module_name]['id-prefix'];
      $migration_group = $this->discoveredExports[$module_name]['group'];
      $data_subdir = $this->discoveredExports[$module_name]['data-dir'];
      $file_subdir = $this->discoveredExports[$module_name]['file-dir'];
      $destination = $this->discoveredExports[$module_name]['path'];
    }

    // Validate entity type IDs.
    if (empty($types_to_export) || !is_array($types_to_export)) {
      $this->logger()->error(dt('No entity types were provided.'));
      return;
    }
    $missing_mistyped_ignored = array_reduce($types_to_export, function (array $carry, string $entity_type_id) {
      if (!isset($this->contentEntityTypes[$entity_type_id])) {
        $carry[] = $entity_type_id;
      }
      return $carry;
    }, []);
    if (!empty($missing_mistyped_ignored)) {
      $this->logger()->error(dt('The following entity type IDs cannot be found or are set to be ignored during content export: @entity-types.', [
        '@entity-types' => implode(', ', $missing_mistyped_ignored),
      ]));
    }

    // Validate export destination.
    if (empty($destination)) {
      $this->logger()->error(dt('Destination of the export module must be provided.'));
    }

    // Validate module name.
    if (empty($options['update']) && array_key_exists($module_name, $this->discoveredModules)) {
      $this->logger()->error(dt('A module with name @module-name already exists on the file system. You should pick a different module name.', [
        '@module-name' => $module_name,
      ]));
    }

    $this->batchRunner->setupBatch(
      $types_to_export,
      $module_name,
      $human_name,
      $migration_prefix,
      $migration_group,
      $data_subdir,
      $file_subdir,
      $destination,
      [get_class($this), 'finishBatch']
    );

    // Process the batch.
    drush_backend_batch_process();

    $this->output()->writeln(dt('@tada Export finished.', [
      '@tada' => '🎉',
    ]));
  }

  /**
   * Processes the content export batch while persisting the processor.
   *
   * @param \Drupal\eme\EmeProcessor $eme_processor
   *   The content export processor.
   * @param string $process_step
   *   The process step (a method) to do.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function processBatch(EmeProcessor $eme_processor, $process_step, &$context): void {
    if (!isset($context['sandbox']['eme_processor'])) {
      $context['sandbox']['eme_processor'] = $eme_processor;
    }
    $active_processor = $context['sandbox']['eme_processor'];
    assert($active_processor instanceof EmeProcessor);
    $active_processor->doStep($process_step, $context);
  }

}
