<?php

namespace Drupal\term_csv_export_import\Controller;

use Drupal\Core\Database\Database;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Cache\Cache;

/**
 * Class ImportController.
 */
class ImportController {

  /**
   * An array of data.
   *
   * @var array
   */
  protected  $data = [];

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected  $vocabulary;

  /**
   * {@inheritdoc}
   */
  public function __construct($data, $vocabulary) {
    $this->vocabulary = $vocabulary;
    $temp = fopen('php://memory', 'rw');
    fwrite($temp, $data);
    rewind($temp);
    $csvArray = [];
    while (!feof($temp)) {
      if ($csvRow = fgetcsv($temp)) {
        $csvArray[] = $csvRow;
      }
    }
    fclose($temp);
    $keys_noid = [
      'name',
      'status',
      'description__value',
      'description__format',
      'weight',
      'parent_name',
    ];
    $keys_id = [
      'tid',
      'uuid',
      'name',
      'status',
      'revision_id',
      'description__value',
      'description__format',
      'weight',
      'parent_name',
      'parent_tid',
    ];
    $keys = [];
    $may_need_revision = TRUE;
    if (!array_diff($keys_noid, $csvArray[0])) {
      \Drupal::messenger()->addWarning(t('The header keys were not included in the import.'));
      $keys = $csvArray[0];
      if (isset($keys['revision_id'])) {
        // This is not an export from an earlier version.
        $may_need_revision = FALSE;
      }
      unset($csvArray[0]);
    }
    foreach ($csvArray as $csvLine) {
      $num_of_lines = count($csvLine);
      $needs_revision = FALSE;
      if (in_array($num_of_lines, [9, 10, 11]) && ($may_need_revision || empty(trim($csvLine[1])))) {
        // Export may have fake or no uuids from d7. generate some that are
        // real-ish.
        if (empty(trim($csvLine[1])) || strpos($csvLine[1], 'fake_tax_uuid') !== FALSE) {
          $uuid_service = \Drupal::service('uuid');
          $csvLine[1] = $uuid_service->generate();
        }
        // This export may be from an earlier version. Check for revision_id.
        if (!is_numeric(trim($csvLine[4])) && in_array($num_of_lines, [9, 10])) {
          // The default revision_id in 8.7 is the tid.
          array_splice($csvLine, 4, 0, $csvLine[0]);
          $needs_revision = TRUE;
          $num_of_lines += 1;
        }
      }
      if (empty($keys)) {
        if (in_array($num_of_lines, [10, 11])) {
          $keys = $keys_id;
        }
        elseif (in_array($num_of_lines, [6, 7])) {
          $keys = $keys_noid;
        }
        else {
          \Drupal::messenger()->addError(
            t('Line with "@part" could not be parsed. Incorrect number of values: @count.',
              [
                '@part' => implode(',', $csvLine),
                '@count' => count($csvLine),
              ]
            )
          );
          continue;
        }
        if (in_array($num_of_lines, [7, 11])) {
          $keys[] = 'fields';
        }
      }
      if ($needs_revision && !in_array('revision_id', $keys)) {
        array_splice($keys, 4, 0, 'revision_id');
      }
      $this->data[] = array_combine($keys, $csvLine);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($preserve_vocabularies, $preserve_tids) {
    // We need to invalidate caches to pull direct from db.
    Cache::invalidateTags([
      'taxonomy_term_values',
    ]);
    $processed = 0;
    // TODO Inject.
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach ($this->data as $row) {
      // Remove whitespace.
      foreach ($row as $key => $value) {
        $row[$key] = trim($value);
      }
      // Check for existence of terms.
      if (isset($row['tid'])) {
        $term_existing = Term::load($row['tid']);
      }
      else {
        $term_existing = taxonomy_term_load_multiple_by_name($row['name'], $this->vocabulary);
        if (count($term_existing) > 1) {
          \Drupal::messenger()->addStatus(
            t('The term @name has multiple matches. Ignoring.', ['@name' => $row['name']])
          );
          continue;
        }
        else {
          $term_existing = $term_existing[0];
        }
      }
      if ($term_existing && $preserve_tids) {
        \Drupal::messenger()->addStatus(
          t(
            'The term with id @id already exists and preserve existing terms is checked. No modification has been made.',
            ['@id' => $row['tid']]
          )
        );
        continue;
      }
      if ($term_existing) {
        $new_term = $term_existing;
      }
      // Or create the term.
      elseif (isset($row['tid'])) {
        // Double check for Term ID cause this could go bad.
        $db = Database::getConnection();
        $query = $db->select('taxonomy_term_data')
          ->fields('taxonomy_term_data', ['tid'])
          ->condition('taxonomy_term_data.tid', $row['tid'], '=');
        $tids = $query->execute()->fetchAll(\PDO::FETCH_OBJ);
        $query1 = $db->select('taxonomy_term_field_data')
          ->fields('taxonomy_term_field_data', ['tid'])
          ->condition('taxonomy_term_field_data.tid', $row['tid'], '=');
        $tids1 = $query1->execute()->fetchAll(\PDO::FETCH_OBJ);
        if (!empty($tids) || !empty($tids1)) {
          \Drupal::messenger()->addError(t('The Term ID already exists.'));
          continue;
        }
        $db->insert('taxonomy_term_data')
          ->fields([
            'tid' => $row['tid'],
            'vid' => $this->vocabulary,
            'uuid' => $row['uuid'],
            'revision_id' => $row['revision_id'],
            'langcode' => $langcode,
          ])
          ->execute();
        $db->insert('taxonomy_term_field_data')
          ->fields([
            'tid' => $row['tid'],
            'vid' => $this->vocabulary,
            'status' => $row['status'],
            'revision_id' => $row['revision_id'],
            'name' => $row['name'],
            'langcode' => $langcode,
            'default_langcode' => 1,
            'weight' => $row['weight'],
            'revision_translation_affected' => 1,
          ])
          ->execute();
        $db->insert('taxonomy_term_revision')
          ->fields([
            'tid' => $row['tid'],
            'revision_id' => $row['revision_id'],
            'langcode' => $langcode,
            'revision_default' => 1,
          ])
          ->execute();
        $db->insert('taxonomy_term_field_revision')
          ->fields([
            'tid' => $row['tid'],
            'status' => $row['status'],
            'revision_id' => $row['revision_id'],
            'name' => $row['name'],
            'langcode' => $langcode,
            'default_langcode' => 1,
            'revision_translation_affected' => 1,
          ])
          ->execute();
        $new_term = Term::load($row['tid']);
      }
      else {
        $new_term = Term::create([
          'name' => $row['name'],
          'vid' => $this->vocabulary,
          'status' => $row['status'],
          'langcode' => $langcode,
        ]);
      }
      // Change the vocabulary if requested.
      if ($new_term->bundle() != $this->vocabulary && !$preserve_vocabularies) {
        // TODO: Make this work.
        // $new_term->vid->setValue($this->vocabulary);.
        /* Currently get an EntityStorageException when field does not exist
        in new vocab. */
        // TODO: Save the term so fields are set properly when above todo done.
        // $new_term->save();
        // So, we update the db instead.
        $tid = $new_term->id();
        $db = Database::getConnection();
        $db->update('taxonomy_term_data')
          ->fields(['vid' => $this->vocabulary])
          ->condition('tid', $tid, '=')
          ->execute();
        $db->update('taxonomy_term_field_data')
          ->fields(['vid' => $this->vocabulary])
          ->condition('tid', $tid, '=')
          ->execute();
        \Drupal::entityTypeManager()->getStorage('taxonomy_term')->resetCache([$tid]);
        $new_term = Term::load($tid);
      }
      // Set temp parents.
      $parent_terms = NULL;
      if (!empty($row['parent_tid'])) {
        if (strpos($row['parent_tid'], ';') !== FALSE) {
          $parent_tids = array_filter(explode(';', $row['parent_tid']), 'strlen');
          foreach ($parent_tids as $parent_tid) {
            $parent_terms[] = Term::load($parent_tid);
          }
        }
        else {
          $parent_terms[] = Term::load($row['parent_tid']);
        }
      }
      $new_term->setDescription($row['description__value'])
        ->setName($row['name'])
        ->set('status', $row['status'])
        ->set('langcode', $langcode)
        ->setFormat($row['description__format'])
        ->setWeight($row['weight']);
      // Check for parents.
      if ($parent_terms == NULL && !empty($row['parent_name'])) {
        $parent_names = explode(';', $row['parent_name']);
        foreach ($parent_names as $parent_name) {
          $parent_term = taxonomy_term_load_multiple_by_name($parent_name, $this->vocabulary);
          if (count($parent_term) > 1) {
            unset($parent_term);
            \Drupal::messenger()->addError(
              t(
                'More than 1 terms are named @name. Cannot distinguish by name. Try using id export/import.',
                ['@name' => $row['parent_name']]
              )
            );
          }
          else {
            $parent_terms[] = array_values($parent_term)[0];
          }
        }
      }
      if ($parent_terms) {
        $parent_terms = array_filter($parent_terms);
        $parent_tids = [];
        foreach ($parent_terms as $parent_term) {
          $parent_tids[] = $parent_term->id();
        }
        $new_term->parent = $parent_tids;
      }
      else {
        $new_term->parent = 0;
      }

      // Import all other non-default taxonomy fields if the row is there.
      if (isset($row['fields']) && !empty($row['fields'])) {
        parse_str($row['fields'], $field_array);
        if (!is_array($field_array)) {
          \Drupal::messenger()->addError(
            t(
              'The field data <em>@data</em> is not formatted correctly. Please use the export function.',
              ['@data' => $row['fields']]
            )
          );
          continue;
        }
        else {
          foreach ($field_array as $field_name => $field_values) {
            if ($new_term->hasField($field_name)) {
              $new_term->set($field_name, $field_values);
            }
            else {
              \Drupal::messenger()->addWarning(
                t(
                  'The field data <em>@data</em> could not be imported. Please add the appropriate fields to the vocabulary you are importing into.',
                  ['@data' => $row['fields']]
                )
              );
            }
          }
        }
      }

      $new_term->save();
      $processed++;
    }
    \Drupal::messenger()->addStatus(
      t('Imported @count terms.', ['@count' => $processed])
    );
  }

}
