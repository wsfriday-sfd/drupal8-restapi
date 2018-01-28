<?php

namespace Drupal\rest_extras\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a REST Resource
 *
 * @RestResource(
 *   id = "menu_resource",
 *   label = @Translation("Menu Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/getmenu"
 *   }
 * )
 */
class MenuResource extends ResourceBase {
  
  /**
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    $tree = [];
    $terms = \Drupal::service('entity_type.manager')
      ->getStorage("taxonomy_term")
      ->loadTree('section');
      
    foreach ($terms as $tree_object) {
      $this->buildTree($tree, $tree_object);
    }
    
    $response = ['main_menu' => $tree];
    
    //temp cache fix
    \Drupal::service('page_cache_kill_switch')->trigger();
    
    $account = \Drupal::currentUser();

    return (new ResourceResponse($response))->addCacheableDependency($account);
  }
  
  /**
   * Populates a tree array given a taxonomy term tree object.
   *
   * @param $tree
   * @param $object
   */
  protected function buildTree(&$tree, $object) {
    if ($object->depth != 0) {
      return;
    }
    // term object
    $term_obj = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->load($object->tid);
    if ($term_obj->field_status->value === "0") {
      return;
    }
    // Tree items
    $tree[$object->tid] = (array) $object;
    $tree[$object->tid]['term_export'] = json_decode(json_encode($term_obj->toArray()), true);
    
    $first = true;
    foreach ($term_obj->get('field_pages_plus') as $delta => $item) {
      if(is_object($item->entity)) {
        $ent = $item->entity->toArray();
        $item_link = array_key_exists('field_path', $ent) && count($ent['field_path']) > 0 ? $ent['field_path'][0]['value'] : "node/" . $item->entity->id();
        // Link setup
        if ($first) {
          $tree[$object->tid]['link'] = $item_link;
          $first = false;
        }
        $tree[$object->tid]['term_export']['field_pages_plus'][$delta]['name'] = $item->entity->label();
        $tree[$object->tid]['term_export']['field_pages_plus'][$delta]['link'] = $item_link;
        $tree[$object->tid]['term_export']['field_pages_plus'][$delta]['node_export'] = $ent;
        // i18n
        foreach ($item->entity->getTranslationLanguages(false) as $lang) {
          $tree[$object->tid]['term_export']['field_pages_plus'][$delta]['i18n'][$lang->getId()] = json_decode(json_encode($item->entity->getTranslation($lang->getId())->toArray()), true);
        }
        if (array_key_exists('status', $ent)) {
          $tree[$object->tid]['term_export']['field_pages_plus'][$delta]['status'] = $ent['status'][0]['value'];
        }
      }
    }
    
    if ($object->tid === '643') {
      $nsmi = $this->getNSMI();
      $tree[$object->tid]['term_export']['field_pages_plus'] = array_merge($tree[$object->tid]['term_export']['field_pages_plus'], $nsmi);
    }
    
    // Link override
    if ($tree[$object->tid]['term_export']['field_link'] && count($tree[$object->tid]['term_export']['field_link']) > 0) {
      $tree[$object->tid]['link'] = $tree[$object->tid]['term_export']['field_link'][0]['value'];
    }
    
    // i18n
    foreach ($term_obj->getTranslationLanguages(false) as $lang) {
      $tree[$object->tid]['i18n'][$lang->getId()] = json_decode(json_encode($term_obj->getTranslation($lang->getId())->toArray()), true);
    }
    
    $tree = array_values($tree);
  }
  
  /**
   * Returns NSMI for Self-Help section
   *
   */
  protected function getNSMI() {
    $tree = [];
    $terms = \Drupal::service('entity_type.manager')
      ->getStorage("taxonomy_term")
      ->loadTree('nsmi');
      
    foreach ($terms as $tree_object) {
      $this->buildNSMI($tree, $tree_object);
    }
    
    return $tree;
  }
  
  /**
   * Populates a tree array given a taxonomy term tree object.
   *
   * @param $tree
   * @param $object
   */
  protected function buildNSMI(&$tree, $object) {
    if ($object->depth !== 0) {
      return;
    }
    // term object
    $term_obj = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term')->load($object->tid);
    $tree[$object->tid] = (array) $object;
    $tree[$object->tid]['term_export'] = json_decode(json_encode($term_obj->toArray()), true);
    
    $tree[$object->tid]['link'] = 'self-help/' . $term_obj->id();
    
    foreach ($term_obj->get('field_term_file') as $delta => $item) {
      $file_uri = $item->entity->getFileUri();
      $tree[$object->tid]['term_export']['field_term_file'][$delta]['url'] = $this->file_output_url($file_uri) . "?t=" . intval($_SERVER['HTTP_ORIGIN'],36);
      $tree[$object->tid]['term_export']['field_term_file'][$delta]['filename'] = $item->entity->filename->value;
      $tree[$object->tid]['term_export']['field_term_file'][$delta]['filemime'] = $item->entity->filemime->value;
    }
    
    // i18n
    foreach ($term_obj->getTranslationLanguages(false) as $lang) {
      $tree[$object->tid]['i18n'][$lang->getId()] = json_decode(json_encode($term_obj->getTranslation($lang->getId())->toArray()), true);
    }
    
    $tree = array_values($tree);
  }
  
  protected function file_output_url($uri) {
    $path = str_replace('private://', '', $uri);
    $output = Url::fromRoute('system.private_file_download', ['filepath' => $path], ['absolute' => TRUE]);
    return $output->toString(TRUE)->getGeneratedUrl();
  }
  
}