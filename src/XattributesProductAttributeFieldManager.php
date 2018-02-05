<?php

namespace Drupal\commerce_xattributes;

use Drupal\commerce_product\ProductAttributeFieldManager;
use Drupal\commerce_product\Entity\ProductAttributeInterface;

/**
 * Overrides the ProductAttributeFieldManager.
 */
class XattributesProductAttributeFieldManager extends ProductAttributeFieldManager {

  /**
   * {@inheritdoc}
   */
  protected function buildFieldMap() {
    $field_map = [];
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('commerce_product_variation');
    foreach (array_keys($bundle_info) as $bundle) {
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      $form_display = commerce_get_entity_display('commerce_product_variation', $bundle, 'form');
      foreach ($this->getFieldDefinitions($bundle) as $field_name => $definition) {
        $handler_settings = $definition->getSetting('handler_settings');
        $component = $form_display->getComponent($field_name);
        foreach ($handler_settings['target_bundles'] as $target_bundle) {
          $field_map[$bundle][] = [
            'attribute_id' => $target_bundle,
            'field_name' => $field_name,
            'weight' => $component ? $component['weight'] : 0,
          ];
        }
      }

      if (!empty($field_map[$bundle])) {
        uasort($field_map[$bundle], ['\Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
        // Remove the weight keys to reduce the size of the cached field map.
        $field_map[$bundle] = array_map(function ($map) {
          return array_diff_key($map, ['weight' => '']);
        }, $field_map[$bundle]);
      }
    }

    return $field_map;
  }

  /**
   * {@inheritdoc}
   */
  public function createField(ProductAttributeInterface $attribute, $variation_type_id) {
    if ($label = $attribute->getElementLabel()) {
      $attribute->set('label', $label);
    }
    parent::createField($attribute, $variation_type_id);
  }

}
