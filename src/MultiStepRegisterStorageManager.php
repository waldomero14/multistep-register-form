<?php


namespace Drupal\multistep_register_form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Exception;

class MultiStepRegisterStorageManager {

  /**
   * @var \Drupal\Core\Database\Connection $database
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  protected $messenger;

  /**
   * @var string $table
   */
  protected $table;

  /**
   * MultiStepStorageManager constructor.
   */
  public function __construct(Connection $connection, MessengerInterface $messenger) {
    $this->connection = $connection;
    $this->messenger = $messenger;
    $this->table = 'multistep_registration_form_fields';
  }

  public function insert(array $values) {
    $transaction = $this->connection->startTransaction('insert');
    try {
      $id = $this->connection->insert($this->table)
        ->fields($values)
        ->execute();
      return $id;
    }
    catch (Exception $e) {
      $transaction->rollBack();
      $this->messenger->addError($e->getMessage());
    }
  }

}
