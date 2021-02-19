<?php

namespace Drupal\eme\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eme\Eme;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity Export settings form.
 */
class EmeSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an Entity Export settings form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [Eme::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['basics'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'class' => ['layout-row', 'clearfix'],
      ],
      'b-column-first' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-column', 'layout-column--half'],
        ],
      ],
      'b-column-last' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-column', 'layout-column--half'],
          'data-eme-selector' => 'ignored-types',
        ],
      ],
    ];

    // Simple configs.
    $form['basics']['b-column-first']['module_machine'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Module name'),
      '#default_value' => $this->config('eme.settings')->get('module_machine'),
      '#placeholder' => Eme::ID_NAME,
      '#description' => $this->t('The <em>machine name</em> of the generated module. Defaults to <code>@machine_name</code>.', [
        '@machine_name' => Eme::ID_NAME,
      ]),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['basics']['b-column-first']['module_human'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Module human name'),
      '#default_value' => $this->config('eme.settings')->get('module_human'),
      '#placeholder' => Eme::HUMAN_NAME,
      '#description' => $this->t('The <em>human-readable</em> name of the generated module. Defaults to <code>@human_name</code>.', [
        '@human_name' => Eme::HUMAN_NAME,
      ]),
    ];
    $form['basics']['b-column-first']['migration_prefix'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration prefix'),
      '#default_value' => $this->config('eme.settings')->get('migration_prefix'),
      '#placeholder' => Eme::ID_NAME,
      '#description' => $this->t('An ID prefix for the generated migration plugin definitions. Defaults to <code>@prefix</code>.', [
        '@prefix' => Eme::ID_NAME,
      ]),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['basics']['b-column-first']['migration_group'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Migration group'),
      '#default_value' => $this->config('eme.settings')->get('migration_group'),
      '#placeholder' => Eme::GROUP_NAME,
      '#description' => $this->t('The migration group of generated migration plugin definitions. Defaults to <code>@group</code>.', [
        '@group' => Eme::GROUP_NAME,
      ]),
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
    ];
    $form['basics']['b-column-first']['data_subdir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Data subdirectory'),
      '#default_value' => $this->config('eme.settings')->get('data_subdir'),
      '#placeholder' => Eme::DATA_SUBDIR,
      '#description' => $this->t('The subdirectory in the generated module where the data source files are stored. Defaults to <code>@data_subdir</code>.', [
        '@data_subdir' => Eme::DATA_SUBDIR,
      ]),
    ];
    $form['basics']['b-column-first']['file_subdir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File subdirectory'),
      '#default_value' => $this->config('eme.settings')->get('file_subdir'),
      '#placeholder' => Eme::FILE_ASSETS_SUBDIR,
      '#description' => $this->t('The subdirectory in the generated module where file assets of the file migration are stored. Defaults to <code>@file_subdir</code>.', [
        '@file_subdir' => Eme::FILE_ASSETS_SUBDIR,
      ]),
    ];

    // Excluded types is a bit weird: this config also allows adding
    // non-discoverable entity types since the current Drupal instance shouldn't
    // necessarily reflect the state when content export happens.
    $discovered_entity_types = array_reduce($this->entityTypeManager->getDefinitions(), function (array $carry, EntityTypeInterface $definition) {
      if (!$definition instanceof ContentEntityType) {
        return $carry;
      }
      $carry[$definition->id()] = $this->t('@plural-label (<code>@provider</code>)', [
        '@plural-label' => ucfirst($definition->getPluralLabel()),
        '@provider' => $definition->getProvider(),
      ]);
      return $carry;
    }, []);
    $stored_entity_types = $this->config('eme.settings')->get('ignored_entity_types') ?? [];
    $missing_stored_type_ids = array_diff($stored_entity_types, array_keys($discovered_entity_types));
    $missing_temporary_type_ids = [];
    $user_input = $form_state->getUserInput();
    if ($new_ignored_type = $form_state->getTemporaryValue('temporary_ignored_type') ?? NULL) {
      $ignorable_types = array_keys($user_input['ignored_entity_types'] ?? []);
      $missing_temporary_type_ids = array_diff($ignorable_types, array_keys($discovered_entity_types));
      $user_input['new_type'] = '';
      $user_input['ignored_entity_types'][$new_ignored_type] = $new_ignored_type;
      $missing_temporary_type_ids = array_unique(
        array_merge(
          $missing_temporary_type_ids,
          array_filter([$new_ignored_type])
        )
      );
    }
    $missing_type_ids = array_unique(
      array_merge(
        $missing_stored_type_ids,
        $missing_temporary_type_ids
      )
    );
    $missing_types = [];
    if (!empty($missing_type_ids)) {
      $missing_types = array_reduce($missing_type_ids, function (array $carry, string $missing_type) {
        $carry[$missing_type] = $this->t('@missing_type (<code>not available</code>)', [
          '@missing_type' => $missing_type,
        ]);
        return $carry;
      }, []);
    }

    $content_entity_types = $discovered_entity_types + $missing_types;
    ksort($content_entity_types);

    $form['basics']['b-column-last']['ignored_entity_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Entity types to ignore'),
      '#description' => $this->t("The selected entity types won't be shown on the custom export form and will be ignored during the export."),
      '#options' => $content_entity_types,
      '#default_value' => $this->config('eme.settings')->get('ignored_entity_types') ?? [],
    ];
    if (!empty($new_ignored_type)) {
      $form_state->setUserInput($user_input);
    }

    $form['basics']['b-column-last']['new_ignored_entity_type'] = [
      '#type' => 'container',
    ];
    $form['basics']['b-column-last']['new_ignored_entity_type']['row'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item', 'clearfix']],
    ];
    $form['basics']['b-column-last']['new_ignored_entity_type']['row']['label'] = [
      '#type' => 'label',
      '#title' => $this->t('Add a new type to be ignored'),
    ];
    $form['basics']['b-column-last']['new_ignored_entity_type']['row']['new_type'] = [
      '#type' => 'machine_name',
      '#required' => FALSE,
      '#machine_name' => [
        'exists' => [get_class($this), 'machineNameExists'],
      ],
      '#title' => $this->t('Add a new type to be ignored'),
      '#title_display' => 'hidden',
      '#size' => 30,
      '#description' => NULL,
      '#wrapper_attributes' => ['class' => ['align-left']],
    ];
    $form['basics']['b-column-last']['new_ignored_entity_type']['row']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form-item', 'align-left']],
    ];
    $form['basics']['b-column-last']['new_ignored_entity_type']['row']['wrapper']['add_type'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ignore this type'),
      '#name' => 'add_type',
      '#ajax' => [
        'callback' => '::addTypeCallback',
      ],
      '#submit' => ['::addTypeSubmit'],
      '#limit_validation_errors' => [['new_type']],
      '#states' => [
        'disabled' => [
          ':input[name="new_type"]' => ['value' => ''],
        ],
      ],
    ];

    $form['#attributes']['data-eme-selector'] = 'form';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('eme.settings');
    $values = $form_state->getValues();
    $nullable_string_configs = [
      'module_machine',
      'module_human',
      'migration_prefix',
      'migration_group',
      'data_subdir',
      'file_subdir',
    ];
    foreach ($nullable_string_configs as $config_key) {
      if (empty($values[$config_key])) {
        $config->clear($config_key);
        continue;
      }
      $config->set($config_key, $values[$config_key]);
    }

    if (empty($ignored_types = array_filter($values['ignored_entity_types'])) && empty($values['new_type'])) {
      $config->clear('ignored_entity_types');
    }
    else {
      $ignored_types = array_unique(
         array_merge(
          array_values($ignored_types),
          array_filter([$values['new_type']])
        )
      );
      $config->set('ignored_entity_types', array_values($ignored_types));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * AJAX callback to update ignored entities.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|array
   *   The form render array or an AJAX response object.
   */
  public function addTypeCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -1000,
      ];
      $form['#sorted'] = FALSE;
      $response->addCommand(new ReplaceCommand('[data-eme-selector="' . $form['#attributes']['data-eme-selector'] . '"]', $form));
      return $response;
    }
    $triggering_element = $form_state->getTriggeringElement();

    // Check if the add new button was clicked.
    if (end($triggering_element['#parents']) !== 'add_type') {
      return $response;
    }

    if (empty($added_type = $form_state->getTemporaryValue('temporary_ignored_type'))) {
      return $response;
    }

    $form_state->setTemporaryValue('temporary_ignored_type', NULL);
    $response->addCommand(new ReplaceCommand('[data-eme-selector="' . $form['basics']['b-column-last']['#attributes']['data-eme-selector'] . '"]', $form['basics']['b-column-last']));

    return $response;
  }

  /**
   * Submit handler for the "Add type" button.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addTypeSubmit(array $form, FormStateInterface $form_state) {
    if (empty($new_value = $form_state->getUserInput()['new_type'] ?? NULL)) {
      return;
    }

    $form_state
      ->setTemporaryValue('temporary_ignored_type', $new_value)
      ->setRebuild();
  }

  /**
   * Used by machine name validate.
   */
  public static function machineNameExists($value, $element): bool {
    if ($element['#name'] !== 'module_machine') {
      return FALSE;
    }

    return $value === 'eme';
  }

}
