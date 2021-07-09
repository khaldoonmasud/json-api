<?php

namespace Drupal\smc_api\Resource;

use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use Exception;

/**
 * Processes a request for SMC API Bus Tracker.
 *
 * @internal
 */
final class BusTracker extends EntityQueryResourceBase {

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

    $address = trim($request->attributes->get('address'));
    $radius = trim($request->attributes->get('radius'));
    $http_code = 200;
    $nids = [];
    $meta = [];
    try {
      if (!is_numeric($radius)) {
        $http_code = Response::HTTP_BAD_REQUEST;
        throw new Exception("Invalid radius used {$radius}.");
      }

      $provider_ids = ['googlemaps'];
      $providers = \Drupal::entityTypeManager()->getStorage('geocoder_provider')->loadMultiple($provider_ids);
      $addressCollection = \Drupal::service('geocoder')->geocode($address, $providers);

      if (!$addressCollection) {
        $http_code = Response::HTTP_NOT_FOUND;
        throw new Exception("Zipcode entered is invalid.");
      }

      $latitude = $addressCollection->first()->getCoordinates()->getLatitude();
      $longitude = $addressCollection->first()->getCoordinates()->getLongitude();
      $radius = intval($radius);
      $expression = '(
        111.045 * DEGREES(
          ACOS(
            COS(RADIANS(:latitude)) * COS(RADIANS(nfg.field_geolocation_lat)) *
            COS(RADIANS(:longitude) - RADIANS(nfg.field_geolocation_lon)) + SIN(RADIANS(:latitude)) *
            SIN(RADIANS(nfg.field_geolocation_lat)))
          )
        )';
      $database = \Drupal::database();
      $query = $database->select('node_field_data', 'nfd');
      $query->fields('nfg', ['entity_id']);
      $query->join('node__field_geolocation', 'nfg', 'nfd.nid = nfg.entity_id');
      $query->addExpression($expression, 'distance');
      $query->condition('type', 'bus_location');
      $query->condition('status', NodeInterface::PUBLISHED);
      $query->where($expression . '<= :radius', [
        ':radius' => $radius,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
      ]);
      $query->orderBy('distance');
      $query->range(0, 100);
      $nids = $query->execute()->fetchAllKeyed(0, 0);

      if (empty($nids)) {
        $http_code = Response::HTTP_NOT_FOUND;
        throw new Exception("No bus locations available with that Zipcode.");
      }
    }
    catch (\Exception $e) {
      $meta['error_message'] = $e->getMessage();
      watchdog_exception('smc_api_bus_tracker', $e, $e->getMessage());
    }

    $data = $this->loadResourceObjectsByEntityIds('node', $nids, FALSE, TRUE);
    $response = $this->createJsonapiResponse($data, $request, $http_code, [], NULL, $meta);
    $response->getCacheableMetadata()->setCacheTags(['node_list']);
    return $response;
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
