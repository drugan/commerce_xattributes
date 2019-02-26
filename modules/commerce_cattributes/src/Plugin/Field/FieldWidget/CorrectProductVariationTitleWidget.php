<?php

namespace Drupal\commerce_cattributes\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationTitleWidget;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Overrides the 'commerce_product_variation_title' widget.
 *
 * @FieldWidget(
 *   id = "commerce_product_variation_title",
 *   label = @Translation("Correct Product variation title"),
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
    $form_object = $form_state->getFormObject();
    $base_id = $form_object->getBaseFormId();
    $form_id = $form_object->getFormId();
    // To reduce the attribute '#id' cut out base form ID from the form ID.
    $id = Html::getClass(strtr($form_id, [$base_id => '']));

    $form_object->wrapperId = $wrapper_id = Html::getClass($form_id);
    $form_state->setFormObject($form_object);
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    if (!($product = $form_state->get('product'))) {
      if (!isset($form['#entity'])
        || !($form['#entity'] instanceof OrderItemInterface)
        || !($purchased_entity = $form['#entity']->getPurchasedEntity())
        || !($product = $purchased_entity->getProduct())
      ) {
        return;
      }
    }
    else {
      $form_state->set('product', $product);
    }

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
