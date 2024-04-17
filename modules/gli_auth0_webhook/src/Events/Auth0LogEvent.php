<?php

namespace Drupal\gli_auth0_webhook\Events;

use Drupal\Component\EventDispatcher\Event;

/**
 * Auth0 Log Event class.
 */
class Auth0LogEvent extends Event {

  /**
   * Return the type of event.
   *
   * @var string
   */
  protected string $type;

  /**
   * Details about the request.
   *
   * @var array
   */
  protected array $request;

  /**
   * Constructor.
   *
   * @param string $type
   *   Event Type.
   * @param array $request
   *   Event Request Details.
   */
  public function __construct(string $type, array $request) {
    $this->type = $type;
    $this->request = $request;
  }

  /**
   * Return the type of event.
   *
   * @return string
   *   Return the type of the event.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Return the method of the request.
   *
   * @return string
   *   Return the request method.
   */
  public function getRequestMethod(): string {
    return $this->request['method'] ?? '';
  }

  /**
   * Return the path of the request.
   *
   * @return string
   *   The path of the request.
   */
  public function getRequestPath(): string {
    return $this->request['path'] ?? '';
  }

  /**
   * Return the request's body.
   *
   * @return array
   *   Return the details of the body.
   */
  public function getRequestBody(): array {
    return $this->request['body'] ?? [];
  }

}
