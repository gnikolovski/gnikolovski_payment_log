<?php

declare(strict_types=1);

namespace Drupal\gnikolovski_payment_log;

/**
 * Interface for payment log service.
 */
interface PaymentLogServiceInterface {

  /**
   * Logs a payment gateway request.
   *
   * @param string $email
   *   The email address of the user who made the payment.
   * @param string $order_id
   *   The order ID.
   * @param array $additional_data
   *   Additional data for the payment.
   * @param string $gateway_name
   *   The name of the payment gateway.
   * @param string $request_data
   *   Request data sent to payment gateway.
   *
   * @return int
   *   The log entry ID or 0 if the operation failed.
   */
  public function logRequest(
    string $email,
    string $order_id,
    array $additional_data,
    string $gateway_name,
    string $request_data,
  ): int;

  /**
   * Logs a payment gateway response.
   *
   * @param string $order_id
   *   The order ID to update.
   * @param string $response_data
   *   Response data received from payment gateway.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  public function logResponse(
    string $order_id,
    string $response_data,
  ): bool;

  /**
   * Logs a payment as canceled.
   *
   * @param string $order_id
   *   The order ID to update.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  public function logCanceled(string $order_id): bool;

  /**
   * Logs an attempt to get the payment status.
   *
   * @param string $order_id
   *   The order ID to update.
   *
   * @return bool
   *   TRUE if the update was successful, FALSE otherwise.
   */
  public function logAttempt(string $order_id): bool;

  /**
   * Get order IDs that have pending payment responses.
   *
   * @param int $time_threshold
   *   The time threshold in seconds. Orders without a response for longer than
   *   this duration will be considered pending.
   * @param int $max_attempts
   *   The maximum number of attempts to get the payment status.
   *
   * @return array
   *   The order IDs with pending payment responses.
   */
  public function getPendingOrderIds(
    int $time_threshold = 1200,
    int $max_attempts = 5,
  ): array;

}
