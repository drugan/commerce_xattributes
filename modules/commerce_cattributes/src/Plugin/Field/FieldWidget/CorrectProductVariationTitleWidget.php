<?php

namespace Drupal\commerce_cattributes\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationTitleWidget;

/**
 * Plugin implementation of the 'commerce_product_variation_title' widget.
 *
 * @FieldWidget(
 *   id = "commerce_product_variation_title",
 *   label = @Translation("Product variation title"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CorrectProductVariationTitleWidget extends ProductVariationTitleWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $form_state->get('product');
    $variations = $this->loadEnabledVariations($product);
    if (count($variations) === 0) {
      // Nothing to purchase, tell the parent form to hide itself.
      $form_state->set('hide_form', TRUE);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => 0,
      ];
      return $element;
    }
    elseif (count($variations) === 1) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = reset($variations);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => $selected_variation->id(),
      ];
      return $element;
    }

    // Build the variation options form.
    $wrapper_id = Html::getUniqueId('commerce-product-add-to-cart-form');
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    if ($form_state->isRebuilding()) {
      $parents = array_merge($element['#field_parents'], [$items->getName(), $delta]);
      $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $parents);
      $selected_variation = $this->selectVariationFromUserInput($variations, $user_input);
    }
    else {
      $selected_variation = $this->variationStorage->loadFromContext($product);
      // The returned variation must also be enabled.
      if (!in_array($selected_variation, $variations)) {
        $selected_variation = reset($variations);
      }
    }
    // Set the selected variation in the form state for our AJAX callback.
    $form_state->set('selected_variation', $selected_variation->id());

    $variation_options = [];
    foreach ($variations as $option) {
      $variation_options[$option->id()] = $option->label();
    }
    // To reduce the ID cut out base form ID from the form ID.
    $base_id = $form_state->getFormObject()->getBaseFormId();
    $id = Html::getClass(strtr($form_state->getFormObject()->getFormId(), [$base_id => '']));
    $element['variation'] = [
      '#id' => "edit-purchased-entity-0{$id}",
      '#type' => 'select',
      '#title' => $this->getSetting('label_text'),
      '#options' => $variation_options,
      '#required' => TRUE,
      '#default_value' => $selected_variation->id(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
    ];
    if ($this->getSetting('label_display') == FALSE) {
      $element['variation']['#title_display'] = 'invisible';
    }

    return $element;
  }

}
