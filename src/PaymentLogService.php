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
   * {@inheritdoc}
   */
  public function logRequest(string $order_id, string $gateway_name, array|string $request_data): int {
    try {
      $id = $this->database->insert('gnikolovski_payment_log')
        ->fields([
          'order_id' => $order_id,
          'gateway_name' => $gateway_name,
          'request_time' => $this->time->getRequestTime(),
          'request_data' => is_string($request_data) ? $request_data : json_encode($request_data),
        ])
        ->execute();

      return (int) $id;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to log payment request: @message', ['@message' => $e->getMessage()]
      );
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logResponse(string $order_id, array|string $response_data): bool {
    try {
      $updated = $this->database->update('gnikolovski_payment_log')
        ->fields([
          'response_time' => $this->time->getRequestTime(),
          'response_data' => is_string($response_data) ? $response_data : json_encode($response_data),
        ])
        ->condition('order_id', $order_id)
        ->condition('canceled', 0)
        ->execute();

      return $updated > 0;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to log payment response: @message', ['@message' => $e->getMessage()]
      );
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function logCanceled(string $order_id): bool {
    return $this->database->update('gnikolovski_payment_log')
      ->fields(['canceled' => 1])
      ->condition('order_id', $order_id)
      ->condition('response_time', NULL, 'IS NULL')
      ->condition('canceled', 0)
      ->execute() > 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingOrderIds(int $time_threshold = 1200): array {
    try {
      return $this->database->select('gnikolovski_payment_log', 'pl')
        ->fields('pl', ['order_id'])
        ->condition('request_time', $this->time->getRequestTime() - $time_threshold, '<')
        ->condition('response_time', NULL, 'IS NULL')
        ->condition('canceled', 0)
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('gnikolovski_payment_log')->error(
        'Failed to get pending order IDs: @message', ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

}
