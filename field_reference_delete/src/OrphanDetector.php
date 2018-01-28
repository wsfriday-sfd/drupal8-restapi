<?php

namespace Drupal\field_reference_delete;

use Drupal\Core\Entity\Annotation\EntityType;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;

class OrphanDetector {

  public function findReferenceFields($header = FALSE) {
    $fields_ids =
      \Drupal::entityQuery('field_config')->condition('field_type', 'entity_reference')->execute();
    /** @var FieldConfig[] $fields */
    $fields = FieldConfig::loadMultiple($fields_ids);
    $definitions = \Drupal::entityManager()->getDefinitions();

    $rows = [];
    if ($header) {
      $rows[] = ['field_id' => 'Field ID',
        'field_table' => 'Field Table', 'field_column' => 'Reference Column',
        'target_table' => 'Target table', 'target_id' => 'Target key'
      ];
    }
    foreach ($fields as $field_id => $field) {
      /** @var FieldStorageConfigInterface $field_storage */
      $field_storage = FieldStorageConfig::loadByName($field->getTargetEntityTypeId(), $field->getName());

      /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
      $table_mapping = new DefaultTableMapping($definitions[$field->getTargetEntityTypeId()], [$field_storage]);

      $target_type = $field_storage->getSetting('target_type');

      $table = $table_mapping->getDedicatedDataTableName($field_storage);
      $entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType($target_type);
      $target_entity_type_base_table = $entity_type->getBaseTable();
      $field_column = $table_mapping->getFieldColumnName($field_storage, 'target_id');

      $key = \Drupal::entityManager()->getDefinition($target_type)->getKey('id');

      // We check that there is a table, because we may reference a view or other entity.
      if ($target_entity_type_base_table) {
        $rows[] = [
          'field_id' => $field_id,
          'field_table' => $table,
          'field_column_id' =>  \Drupal::entityManager()->getDefinition($field->getTargetEntityTypeId())->getKey('id'),
          'field_column' => $field_column,
          'target_table' => $target_entity_type_base_table,
          'target_id' => $key
        ];
      }
    }
    return $rows;
  }

  public function findOrphans($header = TRUE) {
    $rows = [];
    if ($header) {
      $rows[] =
        [
          'entity_type' => 'Entity Type',
          'entity_id' => 'Entity ID',
          'field_id' => 'in field',
          'target_type' => 'references the missing',
        ];
    }
    $references = $this->findReferenceFields(FALSE);
    foreach ($references as $reference) {
      $field_id = $reference['field_id'];
      $field_column_id = $reference['field_column_id'];
      $table = $reference['field_table'];
      $field_column = $reference['field_column'];
      $target_table = $reference['target_table'];
      $id = $reference['target_id'];

      $subquery = db_select($target_table, 't2');
      $subquery->fields('t2', array($id));

      $query = db_select($table, 't1');
      $query->fields('t1', array('entity_id'));
      $query->condition('t1.' . $field_column, $subquery, 'NOT IN');

      $results = $query->execute()->fetchAll();

      if (count($results) > 0) {
        foreach ($results as $result) {
          $rows[] =
            [
              'entity_type' => reset(explode('.', $field_id)),
              'entity_id' => $result->entity_id,
              'field_id' => $field_id,
              'target_type' => $target_table,
            ];
        }
      }
    }
    return $rows;
  }

}