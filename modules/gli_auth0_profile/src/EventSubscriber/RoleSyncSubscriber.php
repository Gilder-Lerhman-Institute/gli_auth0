<?php

namespace Drupal\gli_auth0_profile\EventSubscriber;

use Drupal\gli_auth0\Event\AuthenticationEvent;
use Drupal\gli_auth0\Event\GLIAuth0Events;
use Drupal\gli_auth0\Event\UpdateUserEvent;
use Drupal\gli_auth0\Service\Auth0Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Authentication Subscriber Service.
 */
class RoleSyncSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * @param Auth0Service $auth0Service
   */
  public function __construct(protected Auth0Service $auth0Service) {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[GLIAuth0Events::AUTH0_LOGIN][] = ['onAuth0Login'];
    $events[GLIAuth0Events::AUTH0_USER_UPDATE][] = ['onAuth0UserUpdate'];
    return $events;
  }

  /**
   * Get the Users Salesforce ID.
   */
  public function onAuth0Login(AuthenticationEvent $event) {
    $this->updateUser($event);
  }

  /**
   * Update the User.
   */
  public function onAuth0UserUpdate(UpdateUserEvent $event) {
    $this->updateUser($event);
  }

  /**
   * Central Function used for updating user.
   */
  protected function updateUser(UpdateUserEvent|AuthenticationEvent $event) {
    $this->auth0Service->updateUserRoles($event->getAuth0Id());
  }

}
