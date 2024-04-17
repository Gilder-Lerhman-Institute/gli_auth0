<?php

namespace Drupal\gli_auth0\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Zpier Ping Service Class.
 */
final class ZapierPing {

  /**
   * Client Service.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * Logger service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger service.
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->logger = $loggerChannelFactory->get('zapier');
  }

  /**
   * Create a new guzzle client.
   */
  protected function createClient() {
    return new Client([
      'base_uri' => 'https://hooks.zapier.com/hooks/catch/',
      'headers' => [
        'Content-type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ]);
  }

  /**
   * Get the Guzzle Client.
   */
  protected function getClient() {
    if (!isset($this->client)) {
      $this->client = $this->createClient();
    }
    return $this->client;
  }

  /**
   * Ping an end.
   *
   * @param string $endpoint
   *   The endpoint to ping.
   * @param array $data
   *   The data to post.
   * @param string $method
   *   The method to run. Defaults to POST.
   */
  public function ping(string $endpoint, array $data = [], string $method = 'POST') {
    $data = empty($data) ? [] : ['json' => $data];

    try {
      $this->getClient()->request($method, $endpoint, $data);
    }
    catch (GuzzleException $guzzleException) {
      $this->logger->error($guzzleException->getMessage());
    }
  }

}
