<?php

namespace Drupal\commerce_cattributes\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html as HtmlUtility;
use Drupal\commerce_product\Element\CommerceProductRenderedAttribute;

/**
 * Overrides the CommerceProductRenderedAttribute class.
 */
class CorrectCommerceProductRenderedAttribute extends CommerceProductRenderedAttribute {

  /**
   * Expands a radios element into individual radio elements.
   */
  public static function processRadios(&$element, FormStateInterface $form_state, &$complete_form) {
  //tmpin($element);
//     $rendered_element = parent::processRadios($element, $form_state, $complete_form);
//     return $rendered_element;

    if (count($element['#options']) > 0) {
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value');
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product_attribute_value');
      /** @var \Drupal\Core\Render\RendererInterface $renderer */
      $renderer = \Drupal::service('renderer');
      $attribute_values = $storage->loadMultiple(array_keys($element['#options']));

      $weight = 0;
      foreach ($element['#options'] as $key => $label) {
        if ($exists = isset($attribute_values[$key])) {
          $rendered_attribute = $view_builder->view($attribute_values[$key], 'add_to_cart');
          $title = $renderer->render($rendered_attribute);
        }
        else {
          $title = $label;
        }
        $attributes = $element['#attributes'];
        if (isset($element['#default_value']) && $element['#default_value'] == $key) {
          $attributes['class'][] = 'product--rendered-attribute__selected';
        }
        // Maintain order of options as defined in #options, in case the element
        // defines custom option sub-elements, but does not define all option
        // sub-elements.
        $weight += 0.001;

        $element += [$key => []];
        // Generate the parents as the autogenerator does, so we will have a
        // unique id for each radio button.
        $parents_for_id = array_merge($element['#parents'], [$key]);
        $element[$key] += [
          '#type' => 'radio',
          '#title' => $title,
          '#return_value' => $key,
          '#default_value' => isset($element['#default_value']) ? $element['#default_value'] : FALSE,
          '#attributes' => $attributes,
          '#parents' => $element['#parents'],
          '#id' => HtmlUtility::getUniqueId('edit-' . implode('-', $parents_for_id)),
          '#ajax' => isset($element['#ajax']) ? $element['#ajax'] : NULL,
          // Errors should only be shown on the parent radios element.
          '#error_no_message' => TRUE,
          '#weight' => $weight,
        ];
      }
    }

    $element['#attached']['library'][] = 'commerce_product/rendered-attributes';
    $element['#attributes']['class'][] = 'product--rendered-attribute';

    return $element;
  }

}
