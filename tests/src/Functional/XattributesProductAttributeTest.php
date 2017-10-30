<?php

namespace Drupal\Tests\commerce_xattributes\Functional;

use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\Tests\commerce_product\Functional\ProductBrowserTestBase;

/**
 * Tests administering extended product attributes.
 *
 * @group commerce
 */
class XattributesProductAttributeTest extends ProductBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_xattributes',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_product_attribute',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests administering product attributes.
   */
  public function testAdministerProductAttributes() {
    $this->drupalGet('admin/commerce/product-attributes/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'id' => 'colors_id',
      'label' => 'Color Names',
      'elementLabel' => 'Choose Color',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created the Color Names product attribute.');
    $this->assertSession()->addressMatches('/\/admin\/commerce\/product-attributes\/manage\/colors_id$/');
    $attribute = ProductAttribute::load('colors_id');
    // Check if elementLabel is saved and then diplayed as the default value.
    $this->assertEquals('Choose Color', $attribute->getElementLabel());
    $elementLabel = $this->getSession()->getPage()->findField('elementLabel')->getValue();
    $this->assertEquals('Choose Color', $elementLabel);

    $colors = [
      [
        'attribute' => 'colors_id',
        'name' => 'Red',
        'weight' => 1,
      ],
      [
        'attribute' => 'colors_id',
        'name' => 'ForestGreen',
        'weight' => 2,
      ],
      [
        'attribute' => 'colors_id',
        'name' => 'Blue',
        'weight' => 3,
      ],
    ];

    foreach ($colors as $values) {
      $this->createEntity('commerce_product_attribute_value', $values);
    }

    $this->drupalGet('admin/commerce/product-attributes/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'id' => 'sizes_id',
      'label' => 'Size Names',
      'elementLabel' => 'Choose Size',
    ], 'Save');

    $this->assertSession()->pageTextContains('Created the Size Names product attribute.');
    $this->assertSession()->addressMatches('/\/admin\/commerce\/product-attributes\/manage\/sizes_id$/');
    $attribute = ProductAttribute::load('sizes_id');
    $this->assertEquals('Choose Size', $attribute->getElementLabel());
    $elementLabel = $this->getSession()->getPage()->findField('elementLabel')->getValue();
    $this->assertEquals('Choose Size', $elementLabel);

    foreach (range(1, 102) as $i) {
      $values = [
        'attribute' => 'sizes_id',
        'name' => $i,
        'weight' => $i,
      ];
      $this->createEntity('commerce_product_attribute_value', $values);
    }

    $this->drupalGet('admin/commerce/product-attributes');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('colors_id');
    $this->assertSession()->pageTextContains('Color Names');
    $this->assertSession()->pageTextContains('Choose Color');
    $this->assertSession()->pageTextContains('sizes_id');
    $this->assertSession()->pageTextContains('Size Names');
    $this->assertSession()->pageTextContains('Choose Size');
    // Attribute names are truncated if they exceed 10 characters.
    $this->assertSession()->pageTextContains('Red, ForestGre…, Blue');
    // Only one hundred attribute names can be displayed. A counter (N more
    // values…) displayed for all the rest of the names.
    $this->assertSession()->pageTextContains('1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100 (2 more values…)');

    $this->drupalGet('admin/commerce/config/product-variation-types/default/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Color Names');
    $this->assertSession()->linkNotExistsExact('Color Names');
    $this->assertSession()->pageTextContains('Size Names');
    $this->assertSession()->linkNotExistsExact('Size Names');
    $this->assertSession()->linkExistsExact('colors_id');
    $this->assertSession()->linkExistsExact('sizes_id');

    $this->getSession()->getPage()->clickLink('colors_id');
    $this->assertSession()->addressMatches('/\/admin\/commerce\/product-attributes\/manage\/colors_id$/');

    $this->drupalGet('admin/commerce/config/product-variation-types/default/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([
      'attributes[colors_id]' => 'colors_id',
      'attributes[sizes_id]' => 'sizes_id',
    ], t('Save'));

    $this->drupalGet('admin/commerce/config/product-variation-types/default/edit');
    $this->assertSession()->statusCodeEquals(200);
    // Now 'Color Names'  and 'Size Names' should turn into links with
    // 'Choose Color' and 'Choose Size' elementLabel text.
    $this->assertSession()->linkExistsExact('Choose Color');
    $this->assertSession()->linkExistsExact('Choose Size');
    $this->assertSession()->linkExistsExact('colors_id');
    $this->assertSession()->linkExistsExact('sizes_id');

    $this->getSession()->getPage()->clickLink('Choose Color');
    $this->assertSession()->addressMatches('/\/admin\/commerce\/config\/product-variation-types\/default\/edit\/fields\/commerce_product_variation\.default\.attribute_colors_id$/');
    $this->submitForm([
      'label' => 'My Color',
    ], t('Save settings'));

    $this->assertSession()->addressMatches('/\/admin\/commerce\/config\/product-variation-types\/default\/edit\/fields$/');
    $this->assertSession()->pageTextContains('My Color', 'The customer facing label is changed on the default variation type attribute field');

    $this->drupalGet('admin/commerce/config/product-variation-types/default/edit');
    $this->assertSession()->statusCodeEquals(200);
    // After the 'Choose Color' is changed the link text should also be changed
    // to 'My Color' elementLabel text.
    $this->assertSession()->linkExistsExact('My Color');
    $this->assertSession()->linkExistsExact('Choose Size');
    $this->assertSession()->linkExistsExact('colors_id');
    $this->assertSession()->linkExistsExact('sizes_id');
  }

}
