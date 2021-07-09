<?php

namespace Drupal\smc_api\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Represents the computed recurring date for the siderbar widget countdown.
 */
class CountdownDate extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    if ($entity->getEntityTypeId() == 'paragraph' && $entity->bundle() == 'hero') {
      if ($entity->id()) {
        $countdown_source = $entity->field_countdown_source ?
          $entity->field_countdown_source->getValue() : '';
        $countdown_source = array_column($countdown_source, 'value') ?? '';
        $countdown_source = $countdown_source[0] ?? '';
        $this->setCountdownDateValue($entity->id(), $countdown_source);
      }
    }
  }

  /**
   * Function to set filter options.
   *
   * @param int $entity_id
   *   Entity ID.
   * @param string $countdown_source
   *   Countdown Source value.
   */
  private function setCountdownDateValue(int $entity_id, string $countdown_source) {
    $date = new DrupalDateTime();
    $current_datetime = $date->format('Y-m-d\TH:i:s');
    $timezone = $date->format('P');

    $database = \Drupal::database();
    $result = [];

    switch ($countdown_source) {
      case 'multiple-dates':
        $expression = "DATE_FORMAT(CONVERT_TZ(field_multiple_dates_value,'+00:00','$timezone'), '%Y-%m-%dT%T$timezone')";
        $query = $database->select('paragraph__field_multiple_dates', 'fmd');
        $query->addExpression($expression, 'field_multiple_dates_value');
        $query->condition('fmd.entity_id', $entity_id, '=');
        $query->orderBy('field_multiple_dates_value');
        $query->where("CONVERT_TZ(field_multiple_dates_value,'+00:00','$timezone') > :current_date", [
          ':current_date' => $current_datetime,
        ]);
        $query->range(0, 1);
        $result = $query->execute()->fetchField(0);
        break;

      case 'recurring':
        $expression = "DATE_FORMAT(CONVERT_TZ(field_recurring_end_value,'+00:00','$timezone'), '%Y-%m-%dT%T$timezone')";
        $query = $database->select('date_recur__paragraph__field_recurring', 'rpf');
        $query->addExpression($expression, 'field_recurring_end_value');
        $query->condition('rpf.entity_id', $entity_id, '=');
        $query->where("CONVERT_TZ(field_recurring_end_value,'+00:00','$timezone') > :current_date", [
          ':current_date' => $current_datetime,
        ]);
        $query->orderBy('field_recurring_end_value');
        $query->range(0, 1);
        $result = $query->execute()->fetchField(0);
        break;
    }

    if (!empty($result)) {
      global $_smc_api_cache_tags;
      $this->setValue($result);
      $_smc_api_cache_tags[] = ['tag' => 'paragraph:' . $entity_id, 'time' => strtotime($result) - time()];
    }
  }

}
