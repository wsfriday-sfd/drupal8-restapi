<?php

/**
 * @file
 * Definition of Drupal\rest_extras\Plugin\views\field\NodeExport
 *
 * Custom field for Views that will generate a complete Node object with additional processing done
 */

namespace Drupal\rest_extras\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\Component\Utility\Html;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("node_export")
 */
class NodeExport extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {
    return [];
  }

  public function customRender(ResultRow $values) {
  	$output = array();
    
    // Get node object, could be a relationship
    if ($values->_relationship_entities) {
    	$node = array_shift($values->_relationship_entities);
    }
    else {
    	$node = $values->_entity;
    }
    $nodeArray = json_decode(json_encode($node->toArray()), true);
    
    // Gather fields and additional processing
    $fields = $node->getFieldDefinitions();
    $this->process($node, $nodeArray, $fields);

	// i18n processing will include all translations in the object
    $nodeArray['language'] = $node->language()->getId();
    foreach ($node->getTranslationLanguages(false) as $lang) {
      $obj = $node->getTranslation($lang->getId());
      $curr = json_decode(json_encode($obj->toArray()), true);
      $node_l = $obj;
      $fields_l = $node_l->getFieldDefinitions();
      $this->process($node_l, $curr, $fields_l);
      $nodeArray['i18n'][$lang->getId()] = $curr;
    }

    // When Node Objects are converted to JSON, they will sometimes contain
    // non-standard characters that will cause the View to fail to render
    // The json_encode function can properly handle these
    $output = json_decode(json_encode($nodeArray), true);

    return $output;
  }
  
  private function process(&$node, &$nodeArray, &$fields) {
  	$refArray = array();
  	$imageArray = array();
  	$fileArray = array();
    $textArray = array();
  	// Image styles are gathered to create specific URLs
  	$thmb_style = \Drupal::entityManager()->getStorage('image_style')->load('thumbnail');
  	$thmb2x_style = \Drupal::entityManager()->getStorage('image_style')->load('thumbnail2x');
  	$mobile_style = \Drupal::entityManager()->getStorage('image_style')->load('mobile');
  	$desktop_style = \Drupal::entityManager()->getStorage('image_style')->load('desktop');
  	$banner_style = \Drupal::entityManager()->getStorage('image_style')->load('banner');
    
    foreach ($fields as $item) {
    	// only grab entity references that are fields
    	if (($item->getType() == 'entity_reference' || $item->getType() == 'reference_value_pair') && strpos($item->getName(), 'field_') !== false) {
    		$refArray[] = $item->getName();
    	}
    	// image fields are gathered to generate additional URLs
    	else if ($item->getType() == 'image') {
    		$imageArray[] = $item->getName();
    	}
    	// file fields are also gathered to include additional information and URLs
    	else if ($item->getType() == 'file') {
    		$fileArray[] = $item->getName();
    	}
    }

    foreach ($refArray as $entry) {
        foreach ($node->get($entry) as $delta => $term) {
            if(is_object($term->entity)) {
                $ent = $term->entity->toArray();
                $nodeArray[$entry][$delta]['name'] = $term->entity->label();
                $nodeArray[$entry][$delta]['src'] = $ent;
                // We grab the complete Node Object for any referenced Nodes as well. This cuts down on API calls
                if (array_key_exists('status', $ent)) {
                    $nodeArray[$entry][$delta]['status'] = $ent['status'][0]['value'];
                    $obj = $term->entity;
                    $curr = json_decode(json_encode($obj->toArray()), true);
                    $node_l = $obj;
                    $fields_l = $node_l->getFieldDefinitions();
                    $this->process($node_l, $curr, $fields_l);
                    $nodeArray[$entry][$delta]['src'] = $curr;

                    // i18n processing
                    foreach ($term->entity->getTranslationLanguages(false) as $lang) {
                      $obj = $term->entity->getTranslation($lang->getId());
                      $curr = json_decode(json_encode($obj->toArray()), true);
                      $node_l = $obj;
                      $fields_l = $node_l->getFieldDefinitions();
                      $this->process($node_l, $curr, $fields_l);
                      $nodeArray[$entry][$delta]['src']['i18n'][$lang->getId()] = $curr;
                    }
                }
            }
        }
    }

    foreach ($imageArray as $entry) {
    	foreach ($node->get($entry) as $delta => $term) {
            $image_uri = $term->entity->getFileUri();
            // We include additional information in the URL to keep from running into any
            // CORS based issues when serving files to multiple domains
            $nodeArray[$entry][$delta]['url'] = file_create_url($image_uri) . "?t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['filename'] = $term->entity->filename->value;
            $nodeArray[$entry][$delta]['thumbnail'] = $thmb_style->buildUrl($image_uri) . "&t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['thumbnail2x'] = $thmb2x_style->buildUrl($image_uri) . "&t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['mobile'] = $mobile_style->buildUrl($image_uri) . "&t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['desktop'] = $desktop_style->buildUrl($image_uri) . "&t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['banner'] = $banner_style->buildUrl($image_uri) . "&t=" . intval($_SERVER['HTTP_ORIGIN'],36);
        }
    }

    foreach ($fileArray as $entry) {
        foreach ($node->get($entry) as $delta => $term) {
            $file_uri = $term->entity->getFileUri();
            // We include additional information in the URL to keep from running into any
            // CORS based issues when serving files to multiple domains
            $nodeArray[$entry][$delta]['url'] = file_create_url($file_uri) . "?t=" . intval($_SERVER['HTTP_ORIGIN'],36);
            $nodeArray[$entry][$delta]['filename'] = $term->entity->filename->value;
            $nodeArray[$entry][$delta]['filemime'] = $term->entity->filemime->value;
        }
    }
  }
}
