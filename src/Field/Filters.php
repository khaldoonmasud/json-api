<?php

namespace Drupal\smc_api\Field;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * My computed item list class.
 */
class Filters extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if ($entity->getEntityTypeId() == 'paragraph' && $entity->bundle() == 'listing_grid' && !$entity->field_filters->isEmpty()) {
      $filters = $entity->field_filters->getValue();
      $filters = array_column($filters, 'value');
      $filter = str_replace('field_filter_', '', $this->getName());
      $label = $this->getFieldDefinition()->getLabel();
      $label = 'All ' . $label . 's';
      if (in_array($filter, $filters)) {
        $this->setFilterOptions($filter, $label);
      }
    }

  }

  /**
   * Function to set filter options.
   *
   * @param string $filter
   *   Filter name.
   * @param string $label
   *   Filter first option.
   */
  private function setFilterOptions(string $filter, string $label) {
    $database = \Drupal::database();
    switch ($filter) {
      case 'event_type':
      case 'venue_type':
      case 'promotion_type':
      case 'game_type':
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($filter);
        if (!empty($terms)) {
          $result[] = (object) ['id' => 0, 'title' => $label];
          foreach ($terms as $term) {
            $result[] = (object) ['id' => $term->tid, 'title' => $term->name];
          }
          $this->setValue(Json::encode($result));
        }
        break;

      case 'game':
      case 'venue':
        $query = $database->select('node_field_data', 'n');
        $query->condition('status', 1);
        $query->condition('type', $filter);
        $query->addField('n', 'nid', 'id');
        $query->fields('n', ['title']);
        $query->range(0, 50);
        $query->orderBy('title');
        $result = $query->execute()->fetchAll();
        if (!empty($result)) {
          array_unshift($result, (object) ['id' => 0, 'title' => $label]);
          $this->setValue(Json::encode($result));
        }
        break;
    }
  }

}
