<?php

namespace Drupal\commerce_cattributes\Plugin\Field\FieldWidget;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;

/**
 * Plugin implementation of the 'commerce_options_select' widget.
 *
 * @FieldWidget(
 *   id = "commerce_cattributes_select",
 *   label = @Translation("Deprecated Correct attributes select list"),
 *   field_types = {
 *     "entity_reference",
 *     "list_integer",
 *     "list_float",
 *     "list_string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class CorrectOptionsSelectWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $product = $form_state->get('product');
    if ($product instanceof ProductInterface) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
      $variation = $product->getDefaultVariation();
      $definition = $variation->getTypedData()->getPluginDefinition();
      $options = $element['#options'];

      // Remove attribute options unrelevent to the current variation type.
      if (isset($definition['label']) && !empty($element['#options'])) {
        foreach ($element['#options'] as $label => $options) {
          if ($definition['label'] != $label) {
            unset($element['#options'][$label]);
          }
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'skip_option_label' => t('No, thanks!'),
      'reorder_labels' => '1',
      'no_options_label' => t('No options available ...'),
      'hide_no_options' => '0',
      'show_optgroups_labels' => '0',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['warning'] = [
      '#type' => 'markup',
      '#markup' => "<h1><mark>THIS WIDGET IS GOING TO BE DEPRECATED!</mark></h1> Instead, use the <em>(Correct) Product variation attributes</em> of an order item type widget on the <em>Add to cart</em> form display mode. For the <em>Default</em> form display mode of the same order item type it is recommended to use the <em>Select list</em> widget." ,
    ];
    $settings = $this->getSettings();
    $warning = $this->t('Leaving this field empty is not recommended.');

    $element['skip_option_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skip option label'),
      '#default_value' => $settings['skip_option_label'],
      '#description' => $this->t('Indicates for a user that choosing this option will totally skip an optional field.'),
      '#placeholder' => $warning,
    ];
    $element['reorder_labels'] = [
      '#type' => 'checkbox',
      '#title_display' => 'before',
      '#title' => $this->t('Reorder Labels'),
      '#description' => $this->t('You may uncheck this box if all (or almost all) variations on a product are going to be created. If checked, the element labels will be reordered each time after choosing another option on an attribute. That introduces UX issue but helps to avoid adding wrong variation to a cart or even system crash on the complex attributes use cases. See more: <a href=":href">Incorrect display of attribute field values on the Add To Cart form</a>', [':href' => 'https://www.drupal.org/node/2707721']),
      '#default_value' => $settings['reorder_labels'],
    ];
    $element['hide_no_options'] = [
      '#type' => 'checkbox',
      '#title_display' => 'before',
      '#title' => $this->t('Hide empty field'),
      '#description' => $this->t('If checked, the element having only one empty option will be hidden. Not recommended. Instead set up an explanatory No options label below.'),
      '#default_value' => $settings['hide_no_options'],
    ];
    $element['no_options_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No options label'),
      '#default_value' => $settings['no_options_label'],
      '#description' => $this->t('Indicates for a user that there is no options to choose from on an optional field.'),
      '#placeholder' => $warning,
      '#states' => [
        'visible' => [':input[name*="hide_no_options"]' => ['checked' => FALSE]],
      ],
    ];
    $element['show_optgroups_labels'] = [
      '#type' => 'checkbox',
      '#title_display' => 'before',
      '#title' => $this->t('Show option groups labels'),
      '#description' => $this->t('If the element options combined from multiple option groups, then a label for each group will be shown above its options in the dropdown list.'),
      '#default_value' => $settings['show_optgroups_labels'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary['deprecated'] = 'DEPRECATED!!!';
    $none = $this->t('None');
    $yes = $this->t('Yes');
    $settings = $this->getSettings();
    $settings['reorder_labels'] = $settings['reorder_labels'] ? $yes : $none;
    $hidden = $settings['hide_no_options'];
    $settings['hide_no_options'] = $hidden ? $this->t('Hidden') : $this->t('Not hidden');
    $settings['no_options_label'] = $hidden ? '' : $settings['no_options_label'];
    foreach ($settings as $name => $value) {
      $value = empty($settings[$name]) ? $none : $value;
      $summary[] = "{$name}: {$value}";
    }

    return $summary;
  }

}
