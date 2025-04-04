<?php

/**
 * @file
 * Views integration for the gnikolovski_payment_log module.
 */

declare(strict_types=1);

/**
 * Implements hook_views_data().
 */
function gnikolovski_payment_log_views_data(): array {
  $data = [];

  $data['gnikolovski_payment_log'] = [
    'table' => [
      'group' => t('Payment Log'),
      'provider' => 'gnikolovski_payment_log',
      'base' => [
        'field' => 'id',
        'title' => t('Payment Log'),
        'help' => t('Payment gateway request and response logs.'),
        'weight' => 10,
      ],
    ],
    'id' => [
      'title' => t('ID'),
      'help' => t('The payment log ID.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
      'argument' => [
        'id' => 'numeric',
      ],
    ],
    'order_id' => [
      'title' => t('Order ID'),
      'help' => t('The order ID for which payment was processed.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ],
    'gateway_name' => [
      'title' => t('Gateway Name'),
      'help' => t('The name of the payment gateway used for processing.'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
    ],
    'request_time' => [
      'title' => t('Request Time'),
      'help' => t('The Unix timestamp when the request was sent to payment gateway.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
    ],
    'request_data' => [
      'title' => t('Request Data'),
      'help' => t('JSON request data sent to payment gateway.'),
      'field' => [
        'id' => 'standard',
        'click sortable' => FALSE,
      ],
    ],
    'response_time' => [
      'title' => t('Response Time'),
      'help' => t('The Unix timestamp when the response was received from payment gateway.'),
      'field' => [
        'id' => 'date',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
    ],
    'response_data' => [
      'title' => t('Response Data'),
      'help' => t('JSON response data received from payment gateway.'),
      'field' => [
        'id' => 'standard',
        'click sortable' => FALSE,
      ],
    ],
    'canceled' => [
      'title' => t('Canceled'),
      'help' => t('Indicates whether the payment was canceled.'),
      'field' => [
        'id' => 'boolean',
      ],
    ],
    'attempt_count' => [
      'title' => t('Attempt Count'),
      'help' => t('The number of attempts made to get the payment status.'),
      'field' => [
        'id' => 'numeric',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'numeric',
      ],
    ],
  ];

  return $data;
}
