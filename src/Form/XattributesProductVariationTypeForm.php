<?php

namespace Drupal\commerce_xattributes\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce\EntityTraitManagerInterface;
use Drupal\commerce_product\Form\ProductVariationTypeForm;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;

/**
 * Extend attribute type labels on a variation type edit form.
 *
 * Builds two component clickable labels, one is for the current variation type
 * referenced attribute field and another for the attribute edit pages.
 */
class XattributesProductVariationTypeForm extends ProductVariationTypeForm {

  /**
   * {@inheritdoc}
   *
   * The __construct() uses different $attribute_field_manager object.
   */
  public function __construct(EntityTraitManagerInterface $trait_manager, ProductAttributeFieldManagerInterface $attribute_field_manager) {
    parent::__construct($trait_manager, $attribute_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.commerce_entity_trait'),
      $container->get('commerce_xattributes.attribute_field_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\commerce_product\Entity\ProductVariationTypeInterface $variation_type */
    $variation_type = $this->entity;
    $used_attributes = $attribute_field = [];
    $path = $definitions = '';
    if (!$variation_type->isNew()) {
      $attribute_map = $this->attributeFieldManager->getFieldMap($variation_type->id());
      $used_attributes = array_column($attribute_map, 'attribute_id');
      if (!empty($used_attributes)) {
        $attribute_field = array_combine($used_attributes, array_column($attribute_map, 'field_name'));
        $bundle = $variation_type->getEntityType()->getBundleOf();
        $path = $this->getDestinationArray()['destination'] . "/fields/{$bundle}.{$variation_type->id()}.";
        $definitions = $this->attributeFieldManager->getFieldDefinitions($variation_type->id());
      }
    }
    /** @var \Drupal\commerce_product\Entity\ProductAttributeInterface[] $attributes */
    $attributes = $this->entityTypeManager->getStorage('commerce_product_attribute')->loadMultiple();
    $attributes_options = array_map(function ($attribute) use ($path, $attribute_field, $definitions) {
       /** @var \Drupal\commerce_product\Entity\ProductAttributeInterface $attribute */
      $id = $attribute->id();
      $url = $attribute->url();
      $label = $attribute->label();

      if (isset($attribute_field[$id])) {
        $label = $definitions[$attribute_field[$id]]->label();
        // Link referenced attribute field label to the field edit page.
        $label = $this->t('<a href=":href" target="_blank">%label</a>', [
          '%label' => $label,
          ':href' => $path . $attribute_field[$id],
        ]);

        return $label . ' => ' . $this->t('<a href=":href" target="_blank">@id</a>', [
          ':href' => $url,
          '@id' => $id,
        ]);
      }

      // Attributes not referenced by the variation type presented as an
      // attribute non-clickable admin name (%label).
      return $this->t('%label => <a href=":href" target="_blank">@id</a>', [
        '%label' => $label,
        ':href' => $url,
        '@id' => $id,
      ]);
    }, $attributes);

    if ($attributes_options) {
      $options = [];
      if (!empty($form['attributes']['#default_value'])) {
        foreach ($attributes_options as $key => $value) {
          // Move attributes referenced by the variation type to the top.
          if (in_array($key, $form['attributes']['#default_value'])) {
            $options = [$key => $value] + $options;
          }
          else {
            $options += [$key => $value];
          }
        }
      }
      else {
        $options = $attributes_options;
      }
      $form['attributes']['#options'] = $options;
    }

    return $form;
  }

}