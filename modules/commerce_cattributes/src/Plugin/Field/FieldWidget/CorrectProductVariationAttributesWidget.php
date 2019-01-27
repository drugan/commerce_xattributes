<?php

namespace Drupal\commerce_cattributes\Plugin\Field\FieldWidget;

use Drupal\commerce_bulk\BulkVariationsCreatorInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationWidgetBase;
use Drupal\commerce_cart\CartManagerInterface;

/**
 * Overrides the 'commerce_product_variation_attributes' widget.
 *
 * @FieldWidget(
 *   id = "commerce_product_variation_attributes",
 *   label = @Translation("Correct product variation attributes"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class CorrectProductVariationAttributesWidget extends ProductVariationWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The temporary substitution for the attribute '_none' value.
   *
   * The '0.0' instead of '0' value is taken because when the '_none' option is
   * the only in array of options then it is rendered as $options['Empty label']
   * but with '0.0' it looks like $options['0.0' => 'Empty label'].
   *
   * @var string|float
   */
  protected $noneId = '0.0';

  /**
   * The product variation bulk creator.
   *
   * @var \Drupal\commerce_bulk\BulkVariationsCreatorInterface
   */
  protected $creator;

  /**
   * The attribute field manager.
   *
   * @var \Drupal\commerce_product\ProductAttributeFieldManagerInterface
   */
  protected $attributeFieldManager;

  /**
   * The product attribute storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $attributeStorage;

  /**
   * Constructs a new ProductVariationAttributesWidget object.
   *
   * @param \Drupal\commerce_bulk\BulkVariationsCreatorInterface $creator
   *   The variation bulk creator service.
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $attribute_field_manager
   *   The attribute field manager.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cort manager.
   */
  public function __construct(BulkVariationsCreatorInterface $creator, $plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, ProductAttributeFieldManagerInterface $attribute_field_manager, CartManagerInterface $cart_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $entity_type_manager, $entity_repository);

    $this->attributeFieldManager = $attribute_field_manager;
    $this->attributeStorage = $entity_type_manager->getStorage('commerce_product_attribute');
    $this->creator = $creator;
    $this->cartManager = $cart_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('commerce_bulk.variations_creator'),
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('commerce_product.attribute_field_manager'),
      $container->get('commerce_cart.cart_manager')
    );
  }

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
      // If there is 1 variation but there are attribute fields, then the
      // customer should still see the attribute widgets, to know what they're
      // buying (e.g a product only available in the Small size).
      if (empty($this->attributeFieldManager->getFieldDefinitions($selected_variation->bundle()))) {
        $element['variation'] = [
          '#type' => 'value',
          '#value' => $selected_variation->id(),
        ];
        return $element;
      }
    }

    // If selected variation is already found in self->massageFormValues().
    if ($selected_variation = $form_state->getValue('selected_variation')) {
      $variations = $form_state->getValue('attributes_combinations');
      $form_state->unsetValue('selected_variation');
      $form_state->unsetValue('attributes_combinations');
    }
    // Otherwise load from the current context.
    else {
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $items->getEntity();
      if (!$order_item->isNew()) {
        $selected_variation = $order_item->getPurchasedEntity();
      }
      else {
        $selected_variation = $this->variationStorage->loadFromContext($product);
        // The returned variation must also be enabled.
        if (!in_array($selected_variation, $variations)) {
          $selected_variation = reset($variations);
        }
      }
    }

    $element['variation'] = [
      '#type' => 'value',
      '#value' => $selected_variation->id(),
    ];
    // Set the selected variation in the form state for our AJAX callback.
    $form_state->set('selected_variation', $selected_variation->id());

    $element['attributes'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['attribute-widgets'],
      ],
    ];

    foreach ($this->getAttributeInfo($selected_variation, $variations) as $field_name => $attribute) {
      $element['attributes'][$field_name] = [
        '#id' => 'edit-purchased-entity-0-attributes-' . Html::getClass($field_name . $id),
        '#type' => $attribute['element_type'],
        '#title' => $attribute['title'],
        '#options' => $attribute['values'],
        '#required' => $attribute['required'],
        '#default_value' => $attribute['default_value'],
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxRefresh'],
          'wrapper' => $form['#wrapper_id'],
        ],
      ];
      // Convert the '0' option into #empty_value.
      if (isset($element['attributes'][$field_name]['#options']['0'])) {
        if (!$element['attributes'][$field_name]['#required']) {
          // The '#empty_value' is 'No, thanks!' value. Needs testing.
          // $element['attributes'][$field_name]['#empty_value'] = '0';.
        }
        unset($element['attributes'][$field_name]['#options']['_none']);
      }
      // 1 required value -> Disable the element to skip unneeded ajax calls.
      if ($attribute['required'] && count($attribute['values']) === 1) {
        $element['attributes'][$field_name]['#disabled'] = TRUE;
        // Support for option values keyed by just one optgroup label which may
        // have multiple values.
        $count = 1;
        foreach ($attribute['values'] as $value) {
          $counted = count($value);
          if (($counted > 1) || ($count > 1)) {
            $element['attributes'][$field_name]['#disabled'] = FALSE;
            break;
          }
          elseif ($counted === 1) {
            $count++;
          }
        }
      }
      // Optimize the UX of optional attributes:
      // - Hide attributes that have no values.
      // - Require attributes that have a value on each variation.
      if (empty($element['attributes'][$field_name]['#options'])) {
        $element['attributes'][$field_name]['#access'] = FALSE;
      }
      // The '#empty_value' is 'No, thanks!' value.
      if (!isset($element['attributes'][$field_name]['#empty_value'])) {
        // TODO: decide what we should to do in this case.
        // $element['attributes'][$field_name]['#required'] = TRUE;.
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $form_state->get('product');
    $variations = $this->variationStorage->loadEnabled($product);
    $all = [];
    $all['all'] = NULL;
    $trigger = NULL;
    if (count($variations) > 1 && ($trigger = $form_state->getTriggeringElement())) {
      $any = $this->creator->getUsedAttributesCombinations($variations);
      $form_state->setValue('attributes_combinations', $any);
      $all['all'] = empty($any['used_combinations']) ? NULL : $any['used_combinations'];
      $all['trigger_value'] = $trigger['#value'];
    }

    foreach ($values as &$value) {
      $selected_variation = $this->selectVariationFromUserInput($variations, $value + $all);
      $trigger && $form_state->setValue('selected_variation', $selected_variation);
      $value['variation'] = $selected_variation->id();
    }

    return parent::massageFormValues($values, $form, $form_state);
  }

  /**
   * Selects a product variation from user input.
   *
   * This method exists just for the sake to have less collissions when
   * applying this patch.
   *
   * If there's no user input (form viewed for the first time), the default
   * variation is returned.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   An array of product variations.
   * @param array $user_input
   *   The user input.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The selected variation.
   */
  protected function selectVariationFromUserInput(array $variations, array $user_input) {
    $selected_variation = NULL;
    if (!empty($user_input['attributes'])) {
      $user_input['valid'] = $user_input['attributes'];
      if ($user_input['all']) {
        $user_input['valid'] = [];
        // The $creator returns '_none' for combinations with optional fields,
        // but $user_input contains '0' for those fields, so change '0' to
        // '_none' for filtering out unrelevant combinations properly.
        $none_id = $this->noneId;
        $trigger_name = array_search($user_input['trigger_value'], $user_input['attributes']);
        $trigger_value = $user_input['trigger_value'] == $none_id ? '_none' : $user_input['trigger_value'];
        $user_input['attributes'] = array_map(function ($value) use ($none_id) {
          return($value == $none_id ? '_none' : $value);
        }, $user_input['attributes']);

        foreach ($user_input['all'] as $index => $combination) {
          // For some reasons the first view of the first product on a newly
          // installed site throw a fatal error because $trigger_name === FALSE.
          // @see https://www.drupal.org/project/commerce_xattributes/issues/2956665
          if (!$trigger_name || ($combination[$trigger_name] != $trigger_value)) {
            unset($user_input['all'][$index]);
          }
          else {
            foreach ($user_input['attributes'] as $key => $value) {
              $value = $value == $none_id ? '_none' : $value;
              if ($combination[$key] == $value) {
                $user_input['valid'][$key] = $value;
              }
            }
          }
        }
        foreach ($user_input['all'] as $index => $combination) {
          $merged = array_merge($combination, $user_input['valid']);
          // The exact attributes combination selected by a user is found.
          if ($combination == $merged) {
            $user_input['attributes'] = $combination;
          }
        }
      }
      unset($user_input['all']);
      $attributes = $user_input['attributes'];
      foreach ($variations as $variation) {
        $values = [];
        foreach ($attributes as $field_name => $value) {
          $id = $variation->getAttributeValueId($field_name);
          $values[$field_name] = is_null($id) ? '_none' : $id;
          $merged = array_merge($user_input['valid'], $values);
          // Select variation having at least some valid attribute ids.
          if ($user_input['valid'] == $merged) {
            $selected_variation = $variation;
          }
        }
        $merged = array_merge($attributes, $values);
        // The exact selected variation is found.
        if ($attributes == $merged) {
          $selected_variation = $variation;
          break;
        }
      }
    }

    return $selected_variation ?: reset($variations);
  }

  /**
   * Gets the attribute information for the selected product variation.
   *
   * This method exists just for the sake to have less collissions when
   * applying this patch.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation
   *   The selected product variation.
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   The available product variations.
   *
   * @return array[]
   *   The attribute information, keyed by field name.
   */
  protected function getAttributeInfo(ProductVariationInterface $selected_variation, array $variations) {
    $bundle = $selected_variation->bundle();
    $field_definitions = $this->attributeFieldManager->getFieldDefinitions($bundle);
    $field_map = $this->attributeFieldManager->getFieldMap($bundle);
    $field_names = array_unique(array_column($field_map, 'field_name'));
    if (($first = reset($variations)) && $first instanceof ProductVariationInterface) {
      $variations = $this->creator->getUsedAttributesCombinations($variations);
    }
    $all = $variations['used_combinations'];
    $options = $variations['attributes']['options'];
    $attributes = $attribute_groups = $values = $settings = [];
    // As fields with _none value are not returned so we need to restore them.
    $selected_ids = array_merge(array_fill_keys($field_names, '_none'), $selected_variation->getAttributeValueIds());
    $previous_field_name = array_keys($selected_ids)[0];
    $previous_field_id = reset($selected_ids);
    $settings['skip_option_label'] = $this->t('No, thanks!');
    $settings['no_options_label'] = $this->t('No options available ...');
    $settings['hide_no_options'] = FALSE;
    $settings['reorder_labels'] = TRUE;
    $order_item = $this->cartManager->createOrderItem($selected_variation);
    // "Correct product variation attributes" widget settings.
    $form_display = entity_get_form_display($order_item->getEntityTypeId(), $order_item->bundle(), 'add_to_cart');
    if (($purchased_entity = $form_display->getComponent('purchased_entity')) && !empty($purchased_entity['settings']['skip_option_label'])) {
      $settings = $purchased_entity['settings'] + $settings;
    }
    // Try to fetch "Correct attributes default" widget settings from the
    // variation type's order item default form display mode.
    // TODO: remove this after a while.
    elseif (($form_display = entity_get_form_display($order_item->getEntityTypeId(), $order_item->bundle(), 'default')) && ($purchased_entity = $form_display->getComponent('purchased_entity')) && !empty($purchased_entity['settings'])) {
      $settings = $purchased_entity['settings'] + $settings;
    }
    // The id of an empty option.
    $none_id = $this->noneId;
    // Prevent memory exhaustion as $variations array can be quite heavy.
    unset($variations, $selected_variation, $order_item, $form_display);

    foreach ($field_names as $index => $field_name) {
      $field_id = $selected_ids[$field_name];
      $values[$field_name] = [];
      $field = $field_definitions[$field_name];
      $bundles = $field->getItemDefinition()->getSettings()['handler_settings']['target_bundles'];
      // An attribute may be combined from multiple attributes and set up on
      // a variation type's Commerce select list widget to display labels of the
      // combined attributes as optgroups titles in the dropdown select list.
      // @see https://developer.mozilla.org/en/docs/Web/HTML/Element/optgroup
      // @see https://www.drupal.org/node/2831739
      foreach ($bundles as $attribute_id) {
        /** @var \Drupal\commerce_product\Entity\ProductAttributeInterface $attribute */
        $attribute = $this->attributeStorage->load($attribute_id);
        $attribute_label = '';
        if (!empty($settings['show_optgroups_labels']) && method_exists($attribute, 'getElementLabel')) {
          $attribute_label = $attribute->getElementLabel();
        }
        $attribute_groups[$field_name][$attribute_label] = array_keys($attribute->getValues());
      }
      $type = $attribute->getElementType();
      $attributes[$field_name] = [
        'field_name' => $field_name,
        'title' => $field_definitions[$field_name]->getLabel(),
        'required' => $field_definitions[$field_name]->isRequired(),
        'element_type' => $type,
        'default_value' => $field_id == '_none' ? $none_id : $field_id,
      ];
      unset($field_definitions[$field_name]);
      // The first attribute gets all values. Every next attribute gets only
      // the values from variations matching the previous attribute value.
      // For 'Color' and 'Size' attributes that means getting the colors of all
      // variations, but only the sizes of variations with the selected color.
      foreach ($all as $indeks => $combination) {
        if ($index && $combination[$previous_field_name] != $previous_field_id) {
          // Improve perfomance unsetting unrelevant combinations.
          unset($all[$indeks]);
          continue;
        }
        else {
          $option = [];
          // Add dummy empty option to choose nothing on an optional field.
          if ($combination[$field_name] == '_none') {
            $option_id = $none_id;
            $label = $settings['skip_option_label'];
          }
          else {
            $option_id = $combination[$field_name];
            $label = $options[$field_name][$combination[$field_name]];
          }
          $option[$option_id] = $label;
          // In order to avoid weird results after reordering attribute fields
          // ensure that selected option is at the top of the list.
          // @see https://www.drupal.org/node/2707721
          // @see https://www.drupal.org/files/issues/_add%20to%20cart.png
          if ($settings['reorder_labels'] && $combination[$field_name] == $field_id) {
            $values[$field_name] = $option + $values[$field_name];
          }
          else {
            $values[$field_name] += $option;
          }
        }
      }
      $single = count($values[$field_name]) == 1;
      $no_options = $single && array_keys($values[$field_name])[0] == $none_id;
      if (empty($values[$field_name]) || ($no_options && $settings['hide_no_options'])) {
        unset($attributes[$field_name]);
        continue;
      }
      if ($no_options) {
        $values[$field_name][$none_id] = $settings['no_options_label'];
      }
      $attributes[$field_name]['required'] = $single ?: $attributes[$field_name]['required'];
      $optgroups = $values[$field_name];
      foreach ($attribute_groups[$field_name] as $optgroup => $ids) {
        if (empty($optgroup) || empty($ids)) {
          continue;
        }
        foreach ($values[$field_name] as $id => $label) {
          if (in_array($id, $ids)) {
            unset($optgroups[$id]);
            $optgroups[$optgroup][$id] = $label;
          }
        }
      }
      $attributes[$field_name]['values'] = $optgroups;
      $previous_field_id = $field_id;
      $previous_field_name = $field_name;
    }

    return $attributes;
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
