<?php

namespace Drupal\eme;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Variable;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Archiver\ArchiveTar;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\Entity\File;

/**
 * The entity migration export pipeline processor.
 *
 * @todo Investigate caching options.
 *
 * @internal
 */
final class EmeProcessor {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The name used to identify the lock.
   *
   * @const string
   */
  const LOCK_NAME = 'eme';

  /**
   * Migration plugin definitions directory.
   *
   * @const string
   */
  const MIGRATION_DIR = 'migrations';

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

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
   * Whether translations should be included or not.
   *
   * @var bool
   */
  protected $includeTranslations = TRUE;

  /**
   * Entity types to export.
   *
   * @var string[]
   */
  protected $entityTypesShouldExported;

  /**
   * Module name to export to.
   *
   * @var string
   */
  protected $moduleName;

  /**
   * Module human name.
   *
   * @var string
   */
  protected $moduleHumanName;

  /**
   * Migration prefix of the migrations.
   *
   * @var string
   */
  protected $migrationPrefix;

  /**
   * Migration group of the migrations.
   *
   * @var string
   */
  protected $migrationGroup;

  /**
   * Data subdir.
   *
   * @var string
   */
  protected $dataSubdir;

  /**
   * File subdir.
   *
   * @var string
   */
  protected $fileSubdir;

  /**
   * The Drupal-relative path where the module should be extracted.
   *
   * @var string|null
   */
  protected $extractModule;

  /**
   * Constructs an Eme Processor (Entity Migration Export Processor) object.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend to ensure multiple exports do not occur at the same
   *   time.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param array $entity_types_raw
   *   Array of content entity types which have to be exported.
   * @param string $module_name
   *   The module name to use.
   * @param string $module_humam_name
   *   The module human name to use.
   * @param string $migration_prefix
   *   The migration ID prefix for the generated migrations.
   * @param string $migration_group
   *   The migration group of the generated migrations.
   * @param string $data_subdir
   *   The data subdir.
   * @param string $file_subdir
   *   The file subdir.
   * @param string|null $extract_module
   *   The destination where the export module should be copied or NULL if the
   *   batch should redirect to the export download page.
   */
  public function __construct(LockBackendInterface $lock, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FileSystemInterface $file_system, ModuleExtensionList $module_list, array $entity_types_raw, string $module_name, string $module_humam_name, string $migration_prefix, string $migration_group, string $data_subdir, string $file_subdir, string $extract_module = NULL) {
    $this->lock = $lock;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->fileSystem = $file_system;
    $this->moduleExtensionList = $module_list;
    $this->entityTypesShouldExported = $entity_types_raw;
    $this->moduleName = $module_name;
    $this->moduleHumanName = $module_humam_name;
    $this->migrationPrefix = $migration_prefix;
    $this->migrationGroup = $migration_group;
    $this->dataSubdir = $data_subdir;
    $this->fileSubdir = $file_subdir;
    $this->extractModule = $extract_module;
  }

  /**
   * Initializes the EME for processing a batch.
   *
   * @return array
   *   An array of method names and callables which are invoked to complete the
   *   export.
   */
  public function initialize() {
    $this->prepareArchive();
    $this->doLock();

    return [
      'discoverContentReferences',
      'writeEntityDataSource',
      'writeMigratedFiles',
      'writeMigrationPlugins',
      'finalizeModule',
    ];
  }

  /**
   * Locks the export process.
   */
  protected function doLock() {
    if (!$this->lock->acquire(static::LOCK_NAME)) {
      throw new EmeProcessorException('An another process is already exporting content');
    }
  }

  /**
   * Releases the lock of the export process.
   */
  protected function releaseLock() {
    $this->lock->release(static::LOCK_NAME);
  }

  /**
   * Discovers referred content entities as a batch operation.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function discoverContentReferences(&$context) {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['entities_to_export'])) {
      $sandbox['entities_to_export'] = [];
      $sandbox['entities_checked'] = [];
      $context['results']['discovered'] = [];

      foreach ($this->entityTypesShouldExported as $entity_type) {
        $entities = $this->loadEntitiesByType($entity_type);
        $sandbox['entities_to_export'] += $entities;
      }

      $sandbox['progress'] = 0;
      $sandbox['total'] = count($sandbox['entities_to_export']);
      $context['message'] = $this->t('Discovering content references...');
    }

    if (PHP_SAPI !== 'cli') {
      $context['message'] = $this->t('Discovering content references: (@processed/@total)', [
        '@processed' => $sandbox['progress'],
        '@total' => $sandbox['total'],
      ]);
    }

    $unchecked = array_diff($sandbox['entities_to_export'], $sandbox['entities_checked']);
    $current = reset($unchecked);
    $sandbox['progress'] += 1;

    // @todo Also check translations.
    [
      $entity_type_id,
      $entity_id,
    ] = explode(':', $current);

    // Get the entity.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    assert($entity instanceof ContentEntityInterface);
    // Add discovered referenced entities to the export array.
    $referenced_content_entities = $this->contentEntityReferenceExtractor($entity);
    if ($entity_type_id === 'user' && empty($context['results']['user_has_file_reference'])) {
      $context['results']['user_has_file_reference'] = array_reduce($referenced_content_entities, function ($carry, $item) {
        return $carry || strpos($item, 'file:') === 0;
      }, NULL);
    }
    $sandbox['entities_to_export'] += $referenced_content_entities;
    $sandbox['total'] = count($sandbox['entities_to_export']);

    // Add entity to the results.
    $context['results']['discovered'] += [$current => $current];
    $sandbox['entities_checked'][$current] = $current;

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }
    else {
      // Remove ignored entities.
      $ignored = [
        'user:0',
      ];
      foreach ($ignored as $item) {
        unset($context['results']['discovered'][$item]);
      }
      natsort($context['results']['discovered']);
      $context['finished'] = 1;
    }
  }

  /**
   * Deletes the previous archive.
   */
  protected function prepareArchive() {
    $archive_location = implode('/', [
      $this->fileSystem->getTempDirectory(),
      Eme::ARCHIVE_NAME,
    ]);
    if (file_exists($archive_location)) {
      $this->fileSystem->delete($archive_location);
    }
  }

  /**
   * Returns the actual archive.
   *
   * @return \Drupal\Core\Archiver\ArchiveTar
   *   The module's archive.
   */
  protected function getArchive() {
    return new ArchiveTar(implode('/', [
      $this->fileSystem->getTempDirectory(),
      Eme::ARCHIVE_NAME,
    ]));
  }

  /**
   * Writes entity field values to a file.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function writeEntityDataSource(&$context) {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['entities_to_process'])) {
      $sandbox['entities_to_process'] = $context['results']['discovered'];
      $sandbox['total'] = count($sandbox['entities_to_process']);
      $sandbox['progress'] = 0;
      $context['message'] = $this->t('Collecting and writing entity data source to files: @total to do.', ['@total' => $sandbox['total']]);
    }

    if (PHP_SAPI !== 'cli') {
      $context['message'] = $this->t('Collecting and writing entity data source to files: (@processed/@total)', [
        '@processed' => $sandbox['progress'],
        '@total' => $sandbox['total'],
      ]);
    }

    $current = array_shift($sandbox['entities_to_process']);
    $sandbox['progress'] += 1;
    [
      $entity_type_id,
      $entity_id,
    ] = explode(':', $current);

    // Get the entity.
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    assert($entity instanceof ContentEntityInterface);
    $bundle = $entity->getEntityType()->getKey('bundle')
      ? $entity->bundle()
      : NULL;

    // Write data.
    $this->getArchive()->addString(
      $this->getDataPath($entity_type_id, $bundle, $entity_id),
      json_encode($this->getEntityValues($entity), JSON_PRETTY_PRINT),
      FALSE,
      [
        'mode' => 0644,
      ]
    );

    if ($bundle) {
      $context['results']['exported_entities'][$entity_type_id][$bundle][] = $entity_id;
    }
    else {
      $context['results']['exported_entities'][$entity_type_id][] = $entity_id;
    }

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Adds files required for file migrations to the module archive.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function writeMigratedFiles(&$context) {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['total'])) {
      $files = array_values($context['results']['exported_entities']['file'] ?? []);
      $sandbox['files_to_process'] = array_combine($files, $files);
      $sandbox['total'] = count($sandbox['files_to_process']);
      $sandbox['progress'] = 0;
      $context['message'] = $this->t('Copy the necessary files: @total to do.', ['@total' => $sandbox['total']]);
    }

    if (PHP_SAPI !== 'cli') {
      $context['message'] = $this->t('Copy the necessary files: (@processed/@total)', [
        '@processed' => $sandbox['progress'],
        '@total' => $sandbox['total'],
      ]);
    }

    // Add file to the archive.
    if ($current_file_id = array_shift($sandbox['files_to_process'])) {
      $file = $this->entityTypeManager->getStorage('file')->load($current_file_id);
      if ($file instanceof File) {
        $file_uri = $file->getFileUri();
        $scheme = StreamWrapperManager::getScheme($file_uri);
        $this->getArchive()->addModify($file_uri, $this->getFileDirectory($scheme), $scheme . '://');
      }
      $sandbox['progress'] += 1;
    }
    else {
      $context['finished'] = 1;
    }

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Creates migration plugin definitions.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function writeMigrationPlugins(&$context) {
    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['total'])) {
      $plugins_to_write = [];
      foreach ($context['results']['exported_entities'] ?? [] as $entity_type_id => $things) {
        if (is_array(reset($things))) {
          // "$things" is an nested array of entity IDs grouped per bundle – the
          // current entity type is an array of arrays.
          foreach (array_keys($things) as $bundle) {
            $plugins_to_write[] = implode(':', [$entity_type_id, $bundle]);
          }
        }
        else {
          // "$things" is an array of entity IDs – no bundles.
          $plugins_to_write[] = $entity_type_id;
        }
      }
      $sandbox['plugins_to_write'] = $plugins_to_write;
      $sandbox['progress'] = 0;
      $sandbox['total'] = count($plugins_to_write);
      $context['message'] = $this->t('Generating the migration plugin definitions: @total to generate.', ['@total' => $sandbox['total']]);
    }

    if (PHP_SAPI !== 'cli') {
      $context['message'] = $this->t('Generating the migration plugin definitions: (@processed/@total)', [
        '@processed' => $sandbox['progress'],
        '@total' => $sandbox['total'],
      ]);
    }

    // Create the migration plugin definition (a Yaml).
    $current_plugin = array_shift($sandbox['plugins_to_write']);
    $current_plugin_pieces = explode(':', $current_plugin);
    $entity_type_id = $current_plugin_pieces[0];
    $bundle = $current_plugin_pieces[1] ?? NULL;
    $migration_id = $this->getMigrationId($entity_type_id, $bundle);

    // EME creates a single file for every exported entity.
    $entity_ids = $bundle
      ? array_values($context['results']['exported_entities'][$entity_type_id][$bundle])
      : array_values($context['results']['exported_entities'][$entity_type_id]);
    $urls = array_reduce($entity_ids, function (array $carry, $entity_id) use ($entity_type_id, $bundle) {
      $carry[] = implode('/', [
        '..',
        $this->getDataPath($entity_type_id, $bundle, $entity_id),
      ]);
      return $carry;
    }, []);
    natsort($urls);

    $destination_plugin_base = $this->getEntityTypeKey($entity_type_id, 'revision')
      ? 'entity_complete'
      : 'entity';
    if ($entity_type_id === 'paragraph') {
      $destination_plugin_base = 'entity_reference_revisions';
    }
    $destination_plugin = implode(PluginBase::DERIVATIVE_SEPARATOR, [
      $destination_plugin_base,
      $entity_type_id,
    ]);

    // For first, let's create the skeleton of the migration plugin
    // definition.
    $plugin_definition = [
      'label' => 'Import ' . implode(' ', [$entity_type_id, $bundle]),
      'migration_group' => $this->migrationGroup,
      'migration_tags' => [
        'Drupal ' . explode('.', \Drupal::VERSION)[0],
        'Content',
        $this->moduleHumanName,
      ],
      'id' => $migration_id,
      'source' => [
        'plugin' => 'url',
        'data_fetcher_plugin' => 'file',
        'item_selector' => '/',
        'data_parser_plugin' => 'json',
        'urls' => $urls,
      ],
      'process' => [],
      'destination' => [
        'plugin' => $destination_plugin,
        'translations' => $this->includeTranslations,
      ],
      'migration_dependencies' => [],
    ];

    $migration_dependencies = [
      'required' => [],
      'optional' => [],
    ];

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle ?? $entity_type_id);
    // Add source ID configuration to the migration source plugin.
    foreach (['id', 'revision', 'langcode'] as $key_name) {
      if ($key = $this->getEntityTypeKey($entity_type_id, $key_name)) {
        $key_type = $field_definitions[$key]->getType() === 'integer'
          ? 'integer'
          : 'string';
        $plugin_definition['source']['ids'][$key] = [
          'type' => $key_type,
        ];
      }
    }

    foreach ($field_definitions as $field_name => $field_definition) {
      $plugin_definition['source']['fields'][$field_name] = [
        'name' => $field_name,
        'selector' => '/' . $field_name,
      ];

      // The default process pipeline.
      $process_pipeline = $field_name;

      $item_definition_class = $field_definition->getItemDefinition()->getClass();
      $item_extends_reference = is_subclass_of($item_definition_class, EntityReferenceItem::class);
      $item_is_reference = $item_definition_class === EntityReferenceItem::class;

      if ($item_is_reference || $item_extends_reference) {
        $item_definition = $field_definition->getItemDefinition();
        $settings = $item_definition->getSettings();
        $target_type = $settings['target_type'] ?? NULL;

        if (isset($context['results']['exported_entities'][$target_type])) {
          $target_has_bundles = !empty($this->getEntityTypeKey($target_type, 'bundle'));
          $target_bundles = $settings['handler_settings']['target_bundles'] ?? NULL;
          if (empty($target_bundles) && $target_has_bundles) {
            $target_bundles = array_keys($context['results']['exported_entities'][$target_type] ?? []);
          }

          $dependency_type = 'optional';
          if (
            // Comments need a preexisting author.
            ($entity_type_id === 'comment' && $target_type === 'user') ||
            // User migration with missing user pictures throws notice.
            ($entity_type_id === 'user' && $target_type === 'file' && !empty($context['results']['user_has_file_reference']))
          ) {
            $dependency_type = 'required';
          }

          if ($target_bundles && $target_has_bundles) {
            foreach ($target_bundles as $target_bundle) {
              if (!array_key_exists($target_bundle, $context['results']['exported_entities'][$target_type])
              ) {
                continue;
              }

              $migration_dependencies[$dependency_type] = array_unique(
                array_merge(
                  $migration_dependencies[$dependency_type],
                  [$this->getMigrationId($target_type, $target_bundle)]
                )
              );
            }
          }
          elseif (!$target_has_bundles) {
            $migration_dependencies[$dependency_type] = array_unique(
              array_merge(
                $migration_dependencies[$dependency_type],
                [$this->getMigrationId($target_type)]
              )
            );
          }
        }
      }

      // File migration requires additional processes.
      if ($entity_type_id !== 'file' || $field_name !== 'uri') {
        $plugin_definition['process'][$field_name] = $process_pipeline;
        continue;
      }

      $plugin_definition['process']['source_file_scheme'] = [
        [
          'plugin' => 'explode',
          'delimiter' => '://',
          'source' => 'uri',
        ],
        [
          'plugin' => 'extract',
          'index' => [0],
        ],
        [
          'plugin' => 'skip_on_empty',
          'method' => 'row',
        ],
      ];
      $plugin_definition['process']['source_file_path'] = [
        [
          'plugin' => 'explode',
          'delimiter' => '://',
          'source' => 'uri',
        ],
        [
          'plugin' => 'extract',
          'index' => [1],
        ],
        [
          'plugin' => 'skip_on_empty',
          'method' => 'row',
        ],
      ];
      $plugin_definition['process']['source_full_path'] = [
        [
          'plugin' => 'concat',
          // DIRECTORY_SEPARATOR?
          'delimiter' => '/',
          'source' => [
            'constants/eme_file_path',
            '@source_file_scheme',
            '@source_file_path',
          ],
        ],
      ];
      $plugin_definition['process'][$field_name] = [
        [
          'plugin' => 'file_copy',
          'source' => [
            '@source_full_path',
            'uri',
          ],
        ],
      ];
    }

    $plugin_definition['migration_dependencies'] = $migration_dependencies;
    $this->getArchive()->addString(static::MIGRATION_DIR . "/$migration_id.yml", Yaml::encode($plugin_definition), FALSE, [
      'mode' => 0644,
    ]);

    $context['results']['migration_ids'][] = $migration_id;
    $sandbox['progress'] += 1;

    if ($sandbox['progress'] < $sandbox['total']) {
      $context['finished'] = $sandbox['progress'] / $sandbox['total'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Finalized the exported module and finishes the batch.
   *
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  protected function finalizeModule(&$context) {
    $context['message'] = $this->t('Finalize the module.');
    $archive = $this->getArchive();
    $module_id = $this->moduleName;
    $migration_ids = $context['results']['migration_ids'];
    natsort($migration_ids);

    $archive->addString("$module_id.info.yml", Yaml::encode([
      'name' => $this->moduleHumanName,
      'type' => 'module',
      'description' => 'Generated by EME module',
      'core_version_requirement' => '^8.9 || ^9',
      'dependencies' => [
        'drupal:migrate',
        'migrate_plus:migrate_plus',
      ],
      'scenarios_module' => $module_id,
      'eme_settings' => [
        'migrations' => array_values($migration_ids),
        'types' => $this->entityTypesShouldExported,
        'id-prefix' => $this->migrationPrefix,
        'group' => $this->migrationGroup,
        'data-dir' => $this->dataSubdir,
        'file-dir' => $this->fileSubdir,
      ],
    ]));
    $template = file_get_contents(
      implode(DIRECTORY_SEPARATOR, [
        drupal_get_path('module', 'eme'),
        'scaffold',
        'module',
      ])
    );
    $module_file_content = preg_replace(
      [
        '/EME_MODULE_NAME/',
        '/EME_MODULE_MACHINENAME/',
        "/( +)'EME_MIGRATION_IDS'/",
        '/EME_FILE_ASSETS_SUBDIR/',
      ],
      [
        $this->moduleHumanName,
        $module_id,
        '${1}\'' . implode('\',
${1}\'', $migration_ids) . "'",
        $this->fileSubdir,
      ],
      $template
    );
    $archive->addString("$module_id.module", $module_file_content, FALSE, [
      'mode' => 0644,
    ]);

    if ($this->extractModule) {
      $path = implode('/', [
        $this->extractModule,
        $this->moduleName,
      ]);
      $module_list = $this->moduleExtensionList->reset()->getList();
      if (
        array_key_exists($module_id, $module_list) &&
        file_exists($path)
      ) {
        $this->fileSystem->deleteRecursive($path);
      }
      $dir = $this->extractModule;
      if (!$this->fileSystem->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \RuntimeException(sprintf("Cannot prepare the directory '%s'", $dir));
      }

      if (!$this->getArchive()->extract(DRUPAL_ROOT . '/' . $path)) {
        throw new FileWriteException(sprintf("Cannot extract the temporary archive to Drupal codebase."));
      }
    }

    $context['finished'] = 1;
    $context['results']['redirect'] = empty($this->extractModule);

    $this->releaseLock();
  }

  /**
   * Gets the field values of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array[]
   *   An array of array with the entity (revision) values.
   */
  protected function getEntityValues(ContentEntityInterface $entity): array {
    $entity_values_all = [
      $this->doGetEntityValues($entity),
    ];

    if ($this->includeTranslations) {
      foreach ($entity->getTranslationLanguages(FALSE) as $language) {
        $translation = $entity->getTranslation($language->getId());
        assert($translation instanceof ContentEntityInterface);
        $entity_values_all[] = $this->doGetEntityValues($translation);
      }
    }

    return $entity_values_all;
  }

  /**
   * Returns the values of a content entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The field values of the given entity.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @todo Provide a way for optionally sanitizing the field values.
   */
  protected function doGetEntityValues(ContentEntityInterface $entity): array {
    $entity_values = [];
    $entity_fields = $entity->getFields(FALSE);
    $all_fields = $entity->getFields(TRUE);
    $computed_fields = array_diff_key($all_fields, $entity_fields);
    // We will only include "moderation_state".
    if (!empty($computed_fields['moderation_state'])) {
      $entity_fields += [
        'moderation_state' => $computed_fields['moderation_state'],
      ];
    }

    foreach ($entity_fields as $field_name => $field) {
      if ($field->isEmpty()) {
        // Some fields do not like missing values, e.g.
        // entity_reference_revisions.
        $entity_values[$field_name] = NULL;
        continue;
      }

      $property_count = count($field->first()->getValue());
      $main_property_name = $field->getFieldDefinition()->getFieldStorageDefinition()->getMainPropertyName();
      $property_definitions = $field->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();

      $complex_prop = $property_count > 1 || count($field) > 1 || !$main_property_name;
      $field_value = $complex_prop ? $field->getValue() : $field->{$main_property_name};

      // In some cases, core expects that field property values follow their
      // data type (e.g. taxonomy_build_node_index() expects that the target
      // term ID is integer).
      // Sadly the value getters don't do that.
      if ($complex_prop) {
        foreach ($field_value as $delta => $delta_value) {
          foreach ($delta_value as $property => $prop_value) {
            if ($property_definitions[$property]->getDataType() === 'integer') {
              $field_value[$delta][$property] = (int) $prop_value;
            }
          }
        }
      }
      // This is a simple field: there is only one field item, with one property
      // with a single value.
      elseif ($property_definitions[$main_property_name]->getDataType() === 'integer') {
        $field_value = (int) $field_value;
      }

      $entity_values[$field_name] = $field_value;
    }

    return $entity_values;
  }

  /**
   * Calls a step.
   *
   * @param string|callable $process_step
   *   The step to do. Either a method of EmeProcessor or a callable.
   * @param array|\ArrayAccess $context
   *   A batch context array. If the content export is not running in a batch,
   *   then the only array key that is used is $context['finished']. A process
   *   needs to set $context['finished'] = 1 when it is done.
   *
   * @throws \Drupal\eme\EmeProcessorException
   *   If the given step cannot be called or throws an exception.
   */
  public function doStep($process_step, &$context) {
    try {
      if (!is_array($process_step) && method_exists($this, $process_step)) {
        $this->$process_step($context);
      }
      elseif (is_callable($process_step)) {
        call_user_func_array($process_step, [&$context, $this]);
      }
      return;
    }
    catch (\Exception $exception) {
    }

    $this->releaseLock();
    if (!empty($exception)) {
      throw new EmeProcessorException(sprintf("Unexpected error while processing %s.", Variable::export($process_step)), 1, $exception);
    }
    throw new EmeProcessorException(sprintf("Invalid content export step specified: %s", Variable::export($process_step)));
  }

  /**
   * Determines if an export is already running.
   *
   * @return bool
   *   TRUE if an export is already running, FALSE if not.
   */
  public function alreadyProcessing(): bool {
    return !$this->lock->lockMayBeAvailable(static::LOCK_NAME);
  }

  /**
   * Entity loader.
   *
   * @param string $entity_type
   *   Content Entity Type which entities should be loaded.
   *
   * @return array
   *   List of content entity IDs.
   */
  protected function loadEntitiesByType($entity_type): array {
    if (in_array($entity_type, Eme::getExcludedTypes(), TRUE)) {
      return [];
    }
    $entity_storage = $this->entityTypeManager->getStorage($entity_type, FALSE);
    assert($entity_storage instanceof EntityStorageInterface);
    // @todo Add real permission checks for this module.
    $entity_ids = $entity_storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $entity_identifiers = [];

    foreach (array_values($entity_ids) as $entity_id) {
      $entity_identifiers[$entity_type . ':' . $entity_id] = $entity_type . ':' . $entity_id;
    }

    return $entity_identifiers;
  }

  /**
   * Extracts content entity references from fields of the given content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   *   A content entity.
   *
   * @return array
   *   An array of entities which are referenced by the entity fields.
   */
  protected function contentEntityReferenceExtractor(ContentEntityBase $entity): array {
    $entity_fields = $entity->getFields(FALSE);
    $entity_identifiers = [];
    foreach ($entity_fields as $field) {
      if ($field instanceof EntityReferenceFieldItemList) {
        $referenced_entities = $field->referencedEntities();
        foreach ($referenced_entities as $referenced_entity) {
          if ($referenced_entity instanceof ContentEntityBase) {
            $referenced_entity_identifier = $referenced_entity->getEntityTypeId() . ':' . $referenced_entity->id();
            $entity_identifiers[$referenced_entity_identifier] = $referenced_entity_identifier;
          }
        }
      }
    }

    return $entity_identifiers;
  }

  /**
   * Helper which returns special entity keys.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|null $key
   *   The key. Defaults to "id".
   *
   * @return string|false
   *   The entity specific key.
   */
  private function getEntityTypeKey($entity_type_id, $key = 'id') {
    return $this->entityTypeManager->getDefinition($entity_type_id)->getKey($key);
  }

  /**
   * Returns the ID of a content entity migration plugin definition.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   *
   * @return string
   *   The ID of a content entity migration plugin definition.
   */
  private function getMigrationId(string $entity_type_id, string $bundle = NULL):string {
    return implode('_', array_filter([
      $this->migrationPrefix,
      $entity_type_id,
      $bundle,
    ]));
  }

  /**
   * Returns the directory where the data source of an entity should be saved.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   *
   * @return string
   *   The directory where the data source of an entity should be saved.
   */
  private function getDataDirectory(string $entity_type_id, string $bundle = NULL): string {
    return implode('/', array_filter([
      $this->dataSubdir,
      $entity_type_id,
      $bundle,
    ]));
  }

  /**
   * Returns the path where the data of the specified entity should be saved.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity, e.g. "node".
   * @param string|null $bundle
   *   The bundle ID of the entity, e.g. "article".
   * @param string|int $entity_id
   *   The ID of the entity.
   *
   * @return string
   *   The full path where the data of the specified entity should be saved.
   */
  private function getDataPath(string $entity_type_id, $bundle, $entity_id): string {
    return implode('/', [
      $this->getDataDirectory($entity_type_id, $bundle),
      "{$entity_type_id}-{$entity_id}.json",
    ]);
  }

  /**
   * Returns the directory where files with a specified scheme should be saved.
   *
   * @param string|false $scheme
   *   A scheme.
   *
   * @return string
   *   The directory where files with the specified scheme should be saved;
   *   relative to the generated module's root.
   */
  private function getFileDirectory($scheme = FALSE): string {
    return implode('/', array_filter([
      $this->fileSubdir,
      $scheme,
    ]));
  }

}
