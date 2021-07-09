<?php

namespace Drupal\smc_api\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Represents the computed field to check if the user is logged in.
 */
class UserLoggedIn extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $entity_bundle = $entity->bundle();
    $current_uri = \Drupal::request()->getRequestUri();
    $is_valid_path = preg_match('/^\/jsonapi\/node\/' . $entity_bundle . '\/.+$/', $current_uri);
    if ($is_valid_path) {
      $is_anonymous = \Drupal::currentUser()->isAnonymous();
      if ($is_anonymous) {
        $this->setValue(FALSE);
      }
      else {
        $this->setValue(TRUE);
      }
    }
  }

}
