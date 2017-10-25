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
   * Constructs a new ProductAttributeFieldManager object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
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
