<?php

/**
 * Implements hook_drush_command().
 */
function field_reference_delete_drush_command() {
  $items = [];
  $items['detect-orphan-references'] = [
    'description' => dt('Detect orphan references.'),
    'examples' => [
      'Standard example' => 'drush detect-orphan-references',
    ],
  ];
  return $items;
}

function drush_field_reference_delete_detect_orphan_references() {
  /** @var \Drupal\field_reference_delete\OrphanDetector $orphan_detector */
  $orphan_detector = \Drupal::service('field_reference_delete.orphan_detector');

  $table = $orphan_detector->findReferenceFields(TRUE);
  drush_print_table($table, TRUE);

  $orphans = $orphan_detector->findOrphans();
  drush_print_table($orphans, TRUE);


  // Report back the results.
  drush_log(dt('Hello world. @count orphan references detected.', [
    '@count' => 1,
  ]), 'ok');
}