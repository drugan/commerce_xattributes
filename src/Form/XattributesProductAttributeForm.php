<?php

namespace Drupal\commerce_xattributes\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Form\ProductAttributeForm;

class XattributesProductAttributeForm extends ProductAttributeForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\commerce_product\Entity\ProductAttributeInterface $attribute */
    $attribute = $this->entity;

    $form['label']['#weight'] = -10;
    $form['label']['#description'] = $this->t('The admin facing attribute name which appears on administrative pages (e.g.- My T-shirt Colors).');

    $form['elementLabel'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#weight' => -9,
      '#default_value' => $attribute->getElementLabel(),
      '#description' => $this->t('The customer facing label which appears on any Add to Cart form (e.g.- Color). Leave empty to use admin facing name for this purpose. Note that this label can be overridden for each attribute reference field saved on a variation type.'),
    ];

    return $form;
  }

}
