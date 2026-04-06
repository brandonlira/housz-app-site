<?php

namespace Drupal\bee_hotel;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Serialization\Json;

/**
 * Generic logger service for storing and retrieving log entries.
 */
class Logger {

  protected $database;
  protected $currentUser;

  public function __construct(Connection $database, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->currentUser = $current_user;
  }

  /**
   * Log an event.
   */
  public function log($entity_type, $entity_id, $operation, array $details = []) {
    return $this->database->insert('bee_hotel_log')
      ->fields([
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'operation' => $operation,
        'details' => Json::encode($details),
        'created' => \Drupal::time()->getRequestTime(),
        'uid' => $this->currentUser->id(),
      ])
      ->execute();
  }



  public function getLatest($entity_type, $entity_id, $operation = NULL, $limit = NULL) {
    $query = $this->database->select('bee_hotel_log', 'l')
      ->fields('l')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->orderBy('created', 'DESC');

    if ($operation !== NULL) {
      $query->condition('operation', $operation);
    }

    if ($limit !== NULL) {
      $query->range(0, $limit);
    }

    $result = $query->execute()->fetchAll();
    foreach ($result as $row) {
      $row->details = Json::decode($row->details);
    }
    return $result;
  }

}
