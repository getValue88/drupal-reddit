<?php

namespace Drupal\eme\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for previously exported content migration modules.
 */
class Collection extends ExportFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eme_collection_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['table'] = $this->getTableSkeleton();

    foreach ($this->discoveredExports as $id => $export) {
      $form['table'][$id]['name'] = [
        '#markup' => $this->t('@name (<code>@machine-name</code>)', [
          '@name' => $export['name'],
          '@machine-name' => $id,
        ]),
      ];
      $form['table'][$id]['group'] = [
        '#markup' => $export['group'],
      ];
      $form['table'][$id]['location'] = [
        '#markup' => $export['path'],
      ];
      $form['table'][$id]['initial_types'] = [
        '#markup' => implode(', ', $export['types']),
      ];
      $form['table'][$id]['migrations'] = [
        '#markup' => implode(', ', $export['migrations']),
      ];

      $form['table'][$id]['operations'] = [
        '#type' => 'submit',
        '#op' => $id,
        '#value' => $this->t('Reexport'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering = $form_state->getTriggeringElement();

    $export_id = $triggering['#op'];

    if (
      array_key_exists($export_id, $this->discoveredExports) &&
      array_key_exists('types', $this->discoveredExports[$export_id])
    ) {
      $module_name = $export_id;
      $types_to_export = $this->discoveredExports[$export_id]['types'];
      $human_name = $this->discoveredExports[$export_id]['name'];
      $migration_prefix = $this->discoveredExports[$export_id]['id-prefix'];
      $migration_group = $this->discoveredExports[$export_id]['group'];
      $data_subdir = $this->discoveredExports[$export_id]['data-dir'];
      $file_subdir = $this->discoveredExports[$export_id]['file-dir'];
      $destination = $this->discoveredExports[$export_id]['path'];
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
  }

  /**
   * Returns skeleton for example tables.
   */
  public function getTableSkeleton() {
    return [
      '#type' => 'table',
      '#empty' => $this->t('No content export module available.'),
      '#header' => [
        [
          'data' => $this->t('Name'),
        ],
        [
          'data' => $this->t('Group'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        [
          'data' => $this->t('Location'),
          'class' => [RESPONSIVE_PRIORITY_LOW],
        ],
        [
          'data' => $this->t('Entity type IDs'),
          'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        ],
        [
          'data' => $this->t('Migration IDs'),
          'class' => [RESPONSIVE_PRIORITY_LOW],
        ],
        [
          'data' => $this->t('Operations'),
        ],
      ],
    ];
  }

}
