<?php

declare(strict_types=1);

namespace Drupal\eme;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * An interface aware batch runner service.
 */
final class InterfaceAwareExportBatchRunner {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;


  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The database lock object.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Construct an export batch runner instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The database lock object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FileSystemInterface $file_system, ModuleExtensionList $module_list, LockBackendInterface $lock) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->fileSystem = $file_system;
    $this->moduleExtensionList = $module_list;
    $this->lock = $lock;
  }

  /**
   * Sets up an export batch.
   *
   * @param string[] $types_to_export
   *   An array of entity type IDs which should be exported.
   * @param string $module_name
   *   The module name where content should be exported to.
   * @param string $human_name
   *   The human name of the export module.
   * @param string $prefix
   *   Migration ID prefix of the generated migrations.
   * @param string $group
   *   The (Migrate Tools) migration group of the generated migrations.
   * @param string $data_dir
   *   The base directory where data source should be saved.
   * @param string $file_dir
   *   The base directory where file assets should be saved.
   * @param string|null $destination
   *   The destination where the exported migration module should be saved.
   * @param string|string[]|null $finish_callback
   *   Finish callback of the export batch.
   */
  public function setupBatch(array $types_to_export, string $module_name, string $human_name, string $prefix, string $group, string $data_dir, string $file_dir, $destination = NULL, $finish_callback = NULL): void {
    $eme_processor = new EmeProcessor(
      $this->lock,
      $this->entityTypeManager,
      $this->entityFieldManager,
      $this->fileSystem,
      $this->moduleExtensionList,
      $types_to_export,
      $module_name,
      $human_name,
      $prefix,
      $group,
      $data_dir,
      $file_dir,
      $destination
    );
    if ($eme_processor->alreadyProcessing()) {
      throw new EmeProcessorException("An another export process may be exporting content. If this is not true, then empty the 'eme' record from the 'semaphore' table and try again.");
    }

    try {
      $batch = [
        'title' => $this->translate('Export content to migration'),
        'init_message' => $this->translate('Starting content reference discovery.'),
        'progress_message' => $this->translate('Completed step @current of @total.'),
        'error_message' => $this->translate('Content export has encountered an error.'),
      ];
      foreach ($eme_processor->initialize() as $process_step) {
        $batch['operations'][] = [
          [get_class($this), 'processExportStep'],
          [$eme_processor, $process_step],
        ];
      }

      if ($finish_callback) {
        $batch['finished'] = $finish_callback;
      }
    }
    catch (\Exception $e) {
      throw new EmeProcessorException('Cannot initialize the export process.', 0, $e);
    }

    batch_set($batch);
  }

  /**
   * Processes a content export step while persisting the processor.
   *
   * @param \Drupal\eme\EmeProcessor $eme_processor
   *   The content export processor.
   * @param string $process_step
   *   The process step (a method) to do.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function processExportStep(EmeProcessor $eme_processor, $process_step, &$context): void {
    if (!isset($context['sandbox']['eme_processor'])) {
      $context['sandbox']['eme_processor'] = $eme_processor;
    }

    $active_processor = $context['sandbox']['eme_processor'];
    assert($active_processor instanceof EmeProcessor);
    $active_processor->doStep($process_step, $context);
  }

  /**
   * Translates a message.
   *
   * @param string $message
   *   The message to translate.
   * @param array|null $args
   *   Arguments of the message.
   * @param array|null $context
   *   Context of the message.
   *
   * @return mixed
   *   The translated message.
   */
  public function translate(string $message, array $args = [], array $context = []) {
    $callback = function_exists('t') ? 't' : NULL;
    if (!$callback) {
      $callback = function_exists('dt') ? 'dt' : NULL;
    }
    if ($callback) {
      return call_user_func_array(
        $callback,
        [
          $message,
          $args,
          $context,
        ]
      );
    }

    return $message;
  }

}
