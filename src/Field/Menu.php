<?php

namespace Drupal\smc_api\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Represents the computed field menu for an Menu component.
 */
class Menu extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if ($entity->getEntityTypeId() == 'paragraph' && $entity->bundle() == 'menu') {
      $location_id = $entity->field_location_id->getValue() ?? '';
      $location_id = array_column($location_id, 'value') ?? '';
      $location_id = $location_id[0] ?? '';
      $menu_service = \Drupal::service('smc_api.menu_service_consumer');
      $body = $menu_service->getMenus($location_id);
      if (!empty($body)) {
        $this->setMenuList($body);
      }
    }
  }

  /**
   * Sets the menu list items.
   *
   * @param array $body
   *   Menu's response body.
   */
  private function setMenuList(array $body) {
    $menus = $body['data']['menus'] ?? [];
    $data = [];

    $section_items = function ($sections) {
      foreach ($sections as $section) {
        foreach ($section['items'] as $item) {
          $new_items[] = [
            'name' => $item['name'],
            'description' => $item['description'],
            'choice_name' => $item['choices'][0]['name'] ?? '',
            'price' => $item['choices'][0]['prices']['min'] ?? '',
          ];
        }
        $new_sections[] = [
          'name' => $section['name'],
          'description' => $section['description'],
          'items' => $new_items,
        ];
        $new_items = [];
      }

      return $new_sections;
    };

    foreach ($menus as $menu) {
      $data = [
        'name' => $menu['name'],
        'footnote' => $menu['footnote'],
        'currency' => $menu['currency'],
        'sections' => $section_items($menu['sections']),
      ];
      $this->list[] = $this->createItem(0, $data);
    }
  }

}
