<?php

namespace Drupal\commerce_xattributes;

use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\commerce_product\ProductAttributeListBuilder;

/**
 * Overrides the list builder for product attributes.
 */
class XattributesProductAttributeListBuilder extends ProductAttributeListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $markup = "{$this->t('Attribute ID')}<br />{$this->t('name')}<br />{$this->t('label')}";
    $header['id_name_label'] = Markup::create($markup);
    $header['values'] = $this->t('Attribute values');
    $parent_header = parent::buildHeader();

    return $header + [$parent_header['operations']];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $values = [];
    $i = 0;
    foreach ($entity->getValues() as $attribute_value) {
      if ($i < 100) {
        $value = $attribute_value->getName();
        $values[] = strlen($value) > 10 ? substr_replace($value, '...', 7) : $value;
      }
      $i++;
    }
    if (($count = count($values)) && $i > $count) {
      $more = Markup::create('<strong> (' . ($i - $count) . ' ' . $this->t('more values') . ' ...)</strong>');
      $values = $this->t('@values @more', ['@values' => implode(', ', $values), '@more' => $more]);
    }
    else {
      $values = implode(', ', $values);
    }
    $id = $entity->id();
    $name = $entity->label();
    $label = $entity->getElementLabel();
    $label = empty($label) ? $name : $label;
    $markup = "<strong>{$id}</strong><hr><em>{$name}</em><hr><em>{$label}</em>";
    $row['id_name_label'] = Markup::create($markup);
    $row['values'] = $values;
    $parent_row = parent::buildRow($entity);

    return $row + [$parent_row['operations']];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => [],
      '#empty' => $this->t('There is no @label yet.', ['@label' => $this->entityType->getLabel()]),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    foreach ($this->load() as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build['table']['#rows'][$entity->id()] = $row;
      }
    }

    // @see http://php.net/manual/en/function.natsort.php
    ksort($build['table']['#rows'], SORT_NATURAL);
    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }
    return $build;
  }

}
