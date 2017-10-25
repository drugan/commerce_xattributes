<?php

namespace Drupal\commerce_xattributes\Entity;

use Drupal\commerce_product\Entity\ProductAttribute;

/**
 * Overrides the product attribute entity class.
 */
class XattributesProductAttribute extends ProductAttribute {

  /**
   * The customer facing attribute label.
   *
   * @var string
   */
  protected $elementLabel;

  /**
   * {@inheritdoc}
   */
  public function getElementLabel() {
    return $this->elementLabel;
  }

}
