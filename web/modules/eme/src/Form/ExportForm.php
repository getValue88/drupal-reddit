<?php

namespace Drupal\eme\Form;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\eme\Access\TemporarySchemeAccessCheck;
use Drupal\eme\Eme;
use Drupal\eme\InterfaceAwareExportBatchRunner;
use Drupal\eme\Utility\EmeUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form for starting a Content Entity to Migrations batch.
 */
class ExportForm extends ExportFormBase {

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The exportable content entity types (prepared labels keyed by the ID).
   *
   * @var string[]|\Drupal\Core\StringTranslation\TranslatableMarkup[]
   */
  protected $contentEntityTypes;

  /**
   * Construct an ExportForm instance.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_list
   *   The module extension list service.
   * @param \Drupal\eme\InterfaceAwareExportBatchRunner $batch_runner
   *   The export batch runner.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(ModuleExtensionList $module_list, InterfaceAwareExportBatchRunner $batch_runner, EntityTypeManagerInterface $entity_type_manager, StreamWrapperManagerInterface $stream_wrapper_manager) {
    parent::__construct($module_list, $batch_runner);
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->contentEntityTypes = EmeUtils::getContentEntityTypes($entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('extension.list.module'),
      $container->get('eme.batch_runner'),
      $container->get('entity_type.manager'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL) {
    $temporary_stream_access = (new TemporarySchemeAccessCheck($this->streamWrapperManager))->access();
    if (!$temporary_stream_access->isAllowed()) {
      $form['info'] = [
        '#type' => 'item',
        '#markup' => $this->t('The temporary file directory isn\'t accessible. You must configure it for being able to export content. See the <a href=":system-file-settings-link">File system configuration form</a> for further info.', [
          ':system-file-settings-link' => Url::fromRoute('system.file_system_settings')->toString(),
        ]),
      ];
      return $form;
    }

    $form['#tree'] = FALSE;
    $form['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-row', 'clearfix']],
    ];
    $form['row']['col_first'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
    ];
    $form['row']['col_last'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['layout-column', 'layout-column--half'],
      ],
    ];

    // Module metadata and structure.
    $form['row']['col_first']['name'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Module name'),
      '#placeholder' => $this->config('eme.settings')->get('module_machine') ?? Eme::ID_NAME,
      '#description' => $this->t('The <em>machine name</em> of the generated module.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['row']['col_first']['human'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Module human name'),
      '#placeholder' => $this->config('eme.settings')->get('module_human') ?? Eme::HUMAN_NAME,
      '#description' => $this->t('The <em>human-readable</em> name of the generated module'),
    ];
    $form['row']['col_first']['prefix'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration prefix'),
      '#placeholder' => $this->config('eme.settings')->get('migration_prefix') ?? Eme::ID_NAME,
      '#description' => $this->t('An ID prefix for the generated migration plugin definitions.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['row']['col_first']['group'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration group'),
      '#placeholder' => $this->config('eme.settings')->get('migration_group') ?? Eme::GROUP_NAME,
      '#description' => $this->t('The migration group of generated migration plugin definitions.'),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['row']['col_first']['data_subdir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data subdirectory'),
      '#placeholder' => $this->config('eme.settings')->get('data_subdir') ?? Eme::DATA_SUBDIR,
      '#description' => $this->t('The subdirectory in the generated module where the data source files are stored.'),
    ];
    $form['row']['col_first']['file_subdir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File subdirectory'),
      '#placeholder' => $this->config('eme.settings')->get('file_subdir') ?? Eme::FILE_ASSETS_SUBDIR,
      '#description' => $this->t('The subdirectory in the generated module where file assets of the file migration are stored.'),
    ];

    $form['row']['col_last']['entities_to_export'] = [
      '#type' => 'checkboxes',
      '#options' => $this->contentEntityTypes,
      '#title' => $this->t('Select which content entities should be exported to a migration module'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#op' => 'default',
        '#value' => $this->t('Start export'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $module_name_to_export = empty($form_state->getValue('name'))
      ? Eme::getModuleName()
      : $form_state->getValue('name');
    if (in_array($module_name_to_export, $this->discoveredModules)) {
      $form_state->setErrorByName('name', $this->t('A module with name @module-name already exists.', [
        '@module-name' => $module_name_to_export,
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = array_filter($form_state->getValues());
    $this->batchRunner->setupBatch(
      array_values(array_filter($values['entities_to_export'])),
      $values['name'] ?? Eme::getModuleName(),
      $values['human'] ?? Eme::getModuleHumanName(),
      $values['prefix'] ?? Eme::getMigrationPrefix(),
      $values['group'] ?? Eme::getMigrationGroup(),
      $values['data_subdir'] ?? Eme::getDataSubdir(),
      $values['file_subdir'] ?? Eme::getFileAssetsSubdir(),
      NULL,
      [get_class($this), 'finishBatch']
    );
  }

  /**
   * Used by machine name validate.
   */
  public static function machineNameExists($value, $element): bool {
    if ($element['#name'] !== 'name') {
      return FALSE;
    }

    $extension_list = \Drupal::service('extension.list.module');
    assert($extension_list instanceof ModuleExtensionList);
    $discovered_modules = array_keys($extension_list->reset()->getList());

    return in_array($value, $discovered_modules, TRUE);
  }

}
