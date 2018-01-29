<?php

/**
 * Implements hook_views_data_alter().
 */
function rest_extras_views_data_alter(array &$data) {
  // Custom field that will generate a complete Node object with additional processing
  $data['node']['node_export'] = array(
    'title' => t('Node Export Array'),
    'field' => array(
      'title' => t('Node Export Array'),
      'help' => t('Array of node info.'),
      'id' => 'node_export',
    ),
  );
}

