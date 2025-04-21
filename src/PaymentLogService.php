<?php

declare(strict_types=1);

namespace Drupal\gnikolovski_payment_log;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for logging payment gateway requests and responses.
 */
final class PaymentLogService implements PaymentLogServiceInterface {

  /**
   * Constructs a new PaymentLogService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    #[Autowire(service: 'database')]
    protected Connection $database,
    #[Autowire(service: 'logger.factory')]
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'datetime.time')]
    protected TimeInterface $time,
  ) {}

  /**
   * Gets a query with common conditions for pending payment logs.
   *
   * @param \Drupal\Core\Database\Query\UpdateInterface|\Drupal\Core\Database\Query\SelectInterface $query
   *   The database query object.
   * @param string $order_id
   *   The order ID to filter by.
   *
   * @return \Drupal\Core\Database\Query\UpdateInterface|\Drupal\Core\Database\Query\SelectInterface
   *   The query with conditions applied.
   */
  protected function addPendingConditions($query, string $order_id) {
    return $query
      ->condition('order_id', $order_id)
      ->condition('response_time', NULL, 'IS NULL')
      ->condition('canceled', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function logRequest(
    string $email,
    string $order_id,
    string $remote_order_id,
    string $remote_session_id,
    string $gateway_name,
    string $request_data,
  ): int {
    try {
      $id = $this->database->insert('gnikolovski_payment_log')
        ->fields([
          'email' => $email,
          'order_id' => $order_id,
          'remote_order_id' => $remote_order_id,
          'remote_session_id' => $remote_session_id,
          'gateway_name' => $gateway_name,
          'request_time' => $this->time->getRequestTime(),
          'request_data' => $request_data,
        ])
        ->execute();
        return (int) $id;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to log payment request for order ID: @order_id. Message: @message', [
          '@order_id' => $order_id,
          '@message' => $e->getMessage(),
        ],
      );
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logResponse(
    string $order_id,
    string $response_data,
  ): bool {
    try {
      $updated = $this->database->update('gnikolovski_payment_log')
        ->fields([
          'response_time' => $this->time->getRequestTime(),
          'response_data' => $response_data,
        ]);
      $updated = $this->addPendingConditions($updated, $order_id)->execute();
      return $updated > 0;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to log payment response for order ID: @order_id. Message: @message', [
          '@order_id' => $order_id,
          '@message' => $e->getMessage(),
        ],
      );
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logCanceled(string $order_id): bool {
    $query = $this->database->update('gnikolovski_payment_log')
      ->fields([
        'canceled' => 1,
      ]);
    return $this->addPendingConditions($query, $order_id)->execute() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function logAttempt(string $order_id): bool {
    $query = 'SELECT attempt_count FROM {gnikolovski_payment_log} WHERE order_id = :order_id AND response_time IS NULL AND canceled = 0';
    $attempt_count = $this->database->query($query, [
      'order_id' => $order_id,
    ])->fetchField();

    $update = $this->database->update('gnikolovski_payment_log')
      ->fields([
        'attempt_count' => (int) $attempt_count + 1,
      ]);
    return $this->addPendingConditions($update, $order_id)->execute() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingOrderIds(int $time_threshold = 1200, int $max_attempts = 5): array {
    try {
      return $this->database->select('gnikolovski_payment_log', 'pl')
        ->fields('pl', ['order_id', 'remote_order_id', 'remote_session_id'])
        ->condition('request_time', $this->time->getRequestTime() - $time_threshold, '<')
        ->condition('response_time', NULL, 'IS NULL')
        ->condition('canceled', 0)
        ->condition('attempt_count', $max_attempts, '<')
        ->execute()
        ->fetchAll();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to get pending order IDs. Message: @message', [
          '@message' => $e->getMessage(),
        ],
      );
      return [];
    }
  }

}
