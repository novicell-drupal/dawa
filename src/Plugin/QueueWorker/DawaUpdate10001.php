<?php

namespace Drupal\dawa\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\SuspendQueueException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Refreshes the addresses queued by dawa_update_10001().
 *
 * This queue worker exists only to move the remote work from update 10001 out
 * of deployments. It is not intended as a general-purpose address sync.
 *
 * @QueueWorker(
 *   id = "dawa_update_10001",
 *   title = @Translation("DAWA update 10001 address refresh"),
 *   cron = {"time" = 30}
 * )
 */
class DawaUpdate10001 extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($address_id) {
    try {
      $address = \Drupal::service('dawa.api.addresses')
        ->addressLookup($address_id, 'mini', NULL, TRUE);
    }
    catch (RequestException $exception) {
      if ($exception->getResponse()?->getStatusCode() === 404) {
        \Drupal::logger('dawa')->notice('Skipped missing address @id while completing update 10001.', [
          '@id' => $address_id,
        ]);
        return;
      }
      throw new SuspendQueueException(
        'Adressevaelger is unavailable; update 10001 will resume later.',
        0,
        $exception
      );
    }
    catch (GuzzleException $exception) {
      throw new SuspendQueueException(
        'Adressevaelger is unavailable; update 10001 will resume later.',
        0,
        $exception
      );
    }

    if ($address === FALSE) {
      \Drupal::logger('dawa')->warning('Skipped invalid address @id while completing update 10001.', [
        '@id' => $address_id,
      ]);
      return;
    }

    $fields = ['field_dawa_address_data' => serialize($address)];
    if (!empty($address['betegnelse'])) {
      $fields['field_dawa_address_value'] = $address['betegnelse'];
    }

    $database = \Drupal::database();
    $cache_tags = [];
    foreach ([
      'node__field_dawa_address',
      'node_revision__field_dawa_address',
      'user__field_dawa_address',
    ] as $table) {
      if ($database->schema()->tableExists($table)) {
        $entity_type = str_starts_with($table, 'user__') ? 'user' : 'node';
        $query = $database->select($table, 'd')
          ->fields('d', ['entity_id'])
          ->condition('field_dawa_address_id', $address_id);
        foreach ($query->execute()->fetchCol() as $entity_id) {
          $cache_tags[] = $entity_type . ':' . $entity_id;
        }

        $database->update($table)
          ->fields($fields)
          ->condition('field_dawa_address_id', $address_id)
          ->execute();
      }
    }

    if ($cache_tags) {
      \Drupal::service('cache_tags.invalidator')
        ->invalidateTags(array_unique($cache_tags));
    }
  }

}
