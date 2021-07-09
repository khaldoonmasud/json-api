<?php

namespace Drupal\smc_api\Resource;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\jsonapi_resources\Exception\RouteDefinitionException;

/**
 * Processes a request for a collection of weekly events.
 *
 * @internal
 */
final class ListEvents extends EntityQueryResourceBase {

  /**
   * Determine the maximum elements that will be retrieved.
   *
   * @var number
   */
  const PAGINATION_ITEMS = 12;

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request): ResourceResponse {
    $date = new DrupalDateTime($request->attributes->get('date'));

    if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
      if (!$request->query->has('include')) {
        $request->query->set('include', 'field_venue,field_event_type');
      }
      $request->query->set('page', $request->attributes->get('page'));
      $start_date = $date->format('Y-m-d');
      $timezone = $date->format('P');

      $filters = [];
      if ($request->query->has('filter')) {
        $filters = $request->query->get('filter');
      }

      $database = \Drupal::database();
      $query = $database->select('node_field_data', 'nfd');
      $query->join('date_recur__node__field_event_date', 'drn', 'nfd.nid = drn.entity_id');
      $query->condition('type', 'event');
      $query->condition('status', NodeInterface::PUBLISHED);
      // $query->condition('field_event_date_value', $start_date, '>=');
      $query->where("CONVERT_TZ(field_event_date_value,'+00:00','$timezone') > :current_date", [
        ':current_date' => $start_date,
      ]);
      $query->fields('nfd', ['vid', 'nid']);
      // $query->fields('drn', ['field_event_date_value', 'field_event_date_end_value']);
      $query->addExpression("DATE_FORMAT(CONVERT_TZ(field_event_date_value,'+00:00','$timezone'), '%Y-%m-%dT%T$timezone')", 'field_event_date_value');
      $query->addExpression("DATE_FORMAT(CONVERT_TZ(field_event_date_end_value,'+00:00','$timezone'), '%Y-%m-%dT%T$timezone')", 'field_event_date_end_value');
      $query->orderBy('field_event_date_value');

      // Filters query.
      if (!empty($filters)) {
        $this->addQueryFilters($query, $filters);
      }

      $pager_query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(self::PAGINATION_ITEMS);
      $result = $pager_query->execute();
      $i = 0;
      $ids = [];
      $meta = [];
      $meta['reference_events'] = TRUE;
      foreach ($result as $record) {
        $key = array_search($record->nid, $ids);
        if ($key === FALSE) {
          $ids[$i] = $record->nid;
          $key = $i++;
        }
        $meta['events'][] = ['key' => $key, 'start_date' => $record->field_event_date_value, 'end_date' => $record->field_event_date_end_value];
      }

      $pager = \Drupal::service('pager.manager')->getPager(0);
      $totalItems = $pager->getTotalItems();
      if (($pager->getCurrentPage() < $pager->getTotalPages()) && ($totalItems > self::PAGINATION_ITEMS)) {
        $meta['next'] = $request->getSchemeAndHttpHost() . '/jsonapi/list-events/' . $start_date . '/' . ($pager->getCurrentPage() + 1);
      }

      $data = $this->loadResourceObjectsByEntityIds('node', $ids, FALSE, TRUE);

      $response = $this->createJsonapiResponse($data, $request, 200, [], NULL, $meta);
      $response->getCacheableMetadata()->setCacheTags(['node_list']);
      return $response;
    }
    else {
      throw new RouteDefinitionException("Request date format is not valid.");
    }
  }

  /**
   * Add query filters.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query.
   * @param string[] $filters
   *   The filters.
   */
  private function addQueryFilters(SelectInterface $query, array $filters) {
    if (isset($filters['field_event_type.drupal_internal__tid']) && is_numeric($filters['field_event_type.drupal_internal__tid'])) {
      $query->join('node__field_event_type', 'et', 'nfd.nid = et.entity_id');
      $query->condition('field_event_type_target_id', (int) $filters['field_event_type.drupal_internal__tid']);
    }
    if (isset($filters['field_venue.drupal_internal__nid']) && is_numeric($filters['field_venue.drupal_internal__nid'])) {
      $query->join('node__field_venue', 'v', 'nfd.nid = v.entity_id');
      $query->condition('field_venue_target_id', (int) $filters['field_venue.drupal_internal__nid']);
    }
    if (isset($filters['field_event_date.value']) && $filters['field_event_date.value']) {
      $filter_date = new DrupalDateTime($filters['field_event_date.value']);
      if ($filter_date instanceof DrupalDateTime && !$filter_date->hasErrors()) {
        $timezone = $filter_date->format('P');
        // $query->condition('field_event_date_value', [$filter_date->format('Y-m-d') . 'T00:00:00', $filter_date->format('Y-m-d') . 'T23:59:59'], 'BETWEEN');
        $query->where("CONVERT_TZ(field_event_date_value,'+00:00','$timezone') BETWEEN :filter_start_first AND :filter_start_last", [
          ':filter_start_first' => $filter_date->format('Y-m-d') . 'T00:00:00',
          ':filter_start_last' => $filter_date->format('Y-m-d') . 'T23:59:59',
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('node');
  }

  /**
   * Loads and access checks entities loaded by ID as JSON:API resource objects.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entities to load.
   * @param int[] $ids
   *   An array of entity IDs, keyed by revision ID if the entity type is
   *   revisionable.
   * @param bool $load_latest_revisions
   *   (optional) Whether to load the latest revisions instead of the defaults.
   *   Defaults to FALSE.
   * @param bool $check_access
   *   (optional) Whether to check access on the loaded entities or not.
   *   Defaults to TRUE.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceObjectData
   *   A ResourceObjectData object containing a resource object with unlimited
   *   cardinality. This corresponds to a top-level document's primary
   *   data on a collection response.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function loadResourceObjectsByEntityIds($entity_type_id, array $ids, $load_latest_revisions = FALSE, $check_access = TRUE): ResourceObjectData {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    if ($load_latest_revisions) {
      assert($storage instanceof RevisionableStorageInterface);
      $entities = $storage->loadMultipleRevisions(array_keys($ids));
    }
    else {
      $entities = $storage->loadMultiple($ids);
    }
    return $this->createCollectionDataFromEntities($entities, $check_access);
  }

}
