<?php

namespace Drupal\commerce_xattributes;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\commerce_product\ProductAttributeFieldManager;
use Drupal\commerce_product\Entity\ProductAttributeInterface;

/**
 * Overrides the ProductAttributeFieldManager.
 */
class XattributesProductAttributeFieldManager extends ProductAttributeFieldManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, CacheBackendInterface $cache) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->cache = $cache;
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
