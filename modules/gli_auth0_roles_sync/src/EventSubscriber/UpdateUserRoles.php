<?php

namespace Drupal\gli_auth0_roles_sync\EventSubscriber;

use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\gli_auth0_webhook\Events\Auth0LogEvent;
use Drupal\gli_auth0_webhook\Events\Auth0LogEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Run updates on the user roles.
 */
class UpdateUserRoles implements EventSubscriberInterface {

  /**
   * Auth0 Service.
   *
   * @var \Drupal\gli_auth0\Service\Auth0Service
   */
  protected Auth0Service $auth0Service;

  /**
   * Constructor.
   *
   * @param \Drupal\gli_auth0\Service\Auth0Service $auth0Service
   *   Auth0 Service.
   */
  public function __construct(Auth0Service $auth0Service) {
    $this->auth0Service = $auth0Service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];

    $events[Auth0LogEvents::USER_ADDED_TO_ROLE][] = ['updateRoles'];
    $events[Auth0LogEvents::ROLE_ADDED_TO_USER][] = ['updateRoles'];
    $events[Auth0LogEvents::USER_REMOVED_FROM_ROLE][] = ['updateRoles'];
    $events[Auth0LogEvents::ROLE_REMOVED_FROM_USER][] = ['updateRoles'];

    return $events;
  }

  /**
   * Update a user's roles.
   */
  public function updateRoles(Auth0LogEvent $event) {
    $ids = [];
    switch ($event->getType()) {
      case Auth0LogEvents::USER_ADDED_TO_ROLE:
      case Auth0LogEvents::USER_REMOVED_FROM_ROLE:
        $ids = $event->getRequestBody()['users'];
        break;

      case Auth0LogEvents::ROLE_ADDED_TO_USER:
      case Auth0LogEvents::ROLE_REMOVED_FROM_USER:
        $paths = explode('/', $event->getRequestPath());
        $id = urldecode($paths[4] ?? '');
        if (!empty($id)) {
          $ids[] = $id;
        }
        break;
    }

    /** @var string $id */
    foreach ($ids as $id) {
      $this->auth0Service->updateUserRoles($id);
    }

  }

}
