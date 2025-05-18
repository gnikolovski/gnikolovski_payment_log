<?php

declare(strict_types=1);

namespace Drupal\Tests\gnikolovski_payment_log\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\gnikolovski_payment_log\PaymentLogServiceInterface;

/**
 * Tests the payment log service functionality.
 *
 * @group gnikolovski_payment_log
 */
class PaymentLogServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'gnikolovski_payment_log',
  ];

  /**
   * The payment log service.
   */
  protected PaymentLogServiceInterface $paymentLogService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('gnikolovski_payment_log', ['gnikolovski_payment_log']);
    $this->paymentLogService = $this->container->get('gnikolovski_payment_log.service');
  }

  /**
   * Tests logging a payment request.
   */
  public function testLogRequest(): void {
    // Test basic request logging.
    $email = 'test@example.com';
    $order_id = 'ORDER123';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $log_id = $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    $this->assertNotEquals(0, $log_id, 'Request was successfully logged');

    // Verify the log entry was created in the database.
    $result = $this->getPaymentLogById($log_id);
    $this->assertEquals($email, $result->email);
    $this->assertEquals($order_id, $result->order_id);
    $this->assertEquals(json_encode($additional_data), $result->additional_data);
    $this->assertEquals($gateway_name, $result->gateway_name);
    $this->assertEquals($request_data, $result->request_data);
    $this->assertNotEmpty($result->request_time);
    $this->assertNull($result->response_time);
    $this->assertNull($result->response_data);
    $this->assertEquals(0, $result->canceled);
    $this->assertEquals(0, $result->attempt_count);
  }

  /**
   * Tests logging a payment response.
   */
  public function testLogResponse(): void {
    // First create a request log entry.
    $email = 'test@example.com';
    $order_id = 'ORDER123';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $log_id = $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    // Then log a response for the request.
    $response_data = json_encode(['status' => 'success', 'transaction_id' => 'TXN123']);
    $result = $this->paymentLogService->logResponse($order_id, $response_data);

    $this->assertTrue($result, 'Response was successfully logged');

    // Verify the log entry was updated in the database.
    $log_entry = $this->getPaymentLogById($log_id);
    $this->assertEquals($response_data, $log_entry->response_data);
    $this->assertNotEmpty($log_entry->response_time);
  }

  /**
   * Tests logging a canceled payment.
   */
  public function testLogCanceled(): void {
    // First create a request log entry.
    $email = 'test@example.com';
    $order_id = 'ORDER123';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $log_id = $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    // Mark the payment as canceled.
    $result = $this->paymentLogService->logCanceled($order_id);

    $this->assertTrue($result, 'Payment was successfully marked as canceled');

    // Verify the log entry was updated in the database.
    $log_entry = $this->getPaymentLogById($log_id);
    $this->assertEquals(1, $log_entry->canceled);
  }

  /**
   * Tests logging an error message.
   */
  public function testLogError(): void {
    // First create a request log entry.
    $email = 'test@example.com';
    $order_id = 'ORDER123';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $log_id = $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    // Log an error message.
    $error_message = 'Payment failed: Invalid card number';
    $result = $this->paymentLogService->logError($order_id, $error_message);

    $this->assertTrue($result, 'Error message was successfully logged');

    // Verify the log entry was updated in the database.
    $log_entry = $this->getPaymentLogById($log_id);
    $this->assertEquals($error_message, $log_entry->last_error);
  }

  /**
   * Tests logging an attempt to get payment status.
   */
  public function testLogAttempt(): void {
    // First create a request log entry.
    $email = 'test@example.com';
    $order_id = 'ORDER123';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $log_id = $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    // Log multiple attempts.
    $this->paymentLogService->logAttempt($order_id);
    $this->paymentLogService->logAttempt($order_id);
    $result = $this->paymentLogService->logAttempt($order_id);

    $this->assertTrue($result, 'Attempt was successfully logged');

    // Verify the log entry was updated in the database.
    $log_entry = $this->getPaymentLogById($log_id);
    $this->assertEquals(3, $log_entry->attempt_count);
  }

  /**
   * Tests retrieving pending order IDs.
   */
  public function testGetPendingOrderIds(): void {
    // Create several request log entries.
    $this->paymentLogService->logRequest(
      'test1@example.com',
      'ORDER1',
      ['amount' => '10.00'],
      'test_gateway',
      json_encode(['amount' => '10.00'])
    );

    $this->paymentLogService->logRequest(
      'test2@example.com',
      'ORDER2',
      ['amount' => '20.00'],
      'test_gateway',
      json_encode(['amount' => '20.00'])
    );

    $this->paymentLogService->logRequest(
      'test3@example.com',
      'ORDER3',
      ['amount' => '30.00'],
      'test_gateway',
      json_encode(['amount' => '30.00'])
    );

    // Mark one order as canceled.
    $this->paymentLogService->logCanceled('ORDER2');

    // Log a response for one order.
    $this->paymentLogService->logResponse('ORDER3', json_encode(['status' => 'success']));

    // Set the request time manually to be older than the threshold.
    $connection = $this->container->get('database');
    $connection->update('gnikolovski_payment_log')
      ->fields([
        'request_time' => time() - 1500,
      ])
      ->condition('order_id', 'ORDER1')
      ->execute();

    // Get the pending order IDs.
    $pending_orders = $this->paymentLogService->getPendingOrderIds();

    // We should only have ORDER1 as pending.
    $this->assertCount(1, $pending_orders, 'Only one order should be pending');
    $this->assertEquals('ORDER1', $pending_orders[0]->order_id, 'ORDER1 should be pending');
  }

  /**
   * Tests that edge cases are handled correctly.
   */
  public function testEdgeCases(): void {
    // Test with invalid order ID.
    $result = $this->paymentLogService->logResponse('NONEXISTENT', 'some data');
    $this->assertFalse($result, 'Response should not be logged for non-existent order');

    // Test canceled order not receiving response.
    $email = 'test@example.com';
    $order_id = 'CANCELED_ORDER';
    $additional_data = ['product_id' => 'PROD123', 'amount' => '99.99'];
    $gateway_name = 'test_gateway';
    $request_data = json_encode(['amount' => '99.99', 'currency' => 'USD']);

    $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    $this->paymentLogService->logCanceled($order_id);

    $result = $this->paymentLogService->logResponse($order_id, 'some data');
    $this->assertFalse($result, 'Response should not be logged for canceled order');

    // Test order with response not accepting further responses.
    $order_id = 'COMPLETED_ORDER';
    $this->paymentLogService->logRequest(
      $email,
      $order_id,
      $additional_data,
      $gateway_name,
      $request_data
    );

    $this->paymentLogService->logResponse($order_id, 'initial response');
    $result = $this->paymentLogService->logResponse($order_id, 'another response');
    $this->assertFalse($result, 'Second response should not be logged');
  }

  /**
   * Helper method to retrieve a payment log entry by ID.
   *
   * @param int $id
   *   The log entry ID.
   *
   * @return object|null
   *   The log entry object or NULL if not found.
   */
  protected function getPaymentLogById(int $id): ?object {
    $connection = $this->container->get('database');
    return $connection->select('gnikolovski_payment_log', 'pl')
      ->fields('pl')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();
  }

}
