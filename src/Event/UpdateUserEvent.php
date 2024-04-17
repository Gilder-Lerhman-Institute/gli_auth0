<?php

namespace Drupal\gli_auth0\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Update User Event.
 */
class UpdateUserEvent extends Event {

  /**
   * Constructor.
   */
  public function __construct(protected UserInterface $user, protected string $auth0Id, protected array $userData = [], protected array $roles = []) {

  }

  /**
   * Return Auth0 ID.
   */
  public function getAuth0Id() {
    return $this->auth0Id;
  }

  /**
   * Return Auth0 User Data.
   */
  public function getUserData(string $key) {
    return $this->userData[$key] ?? null;
  }

  /**
   * Return the user.
   *
   * @return UserInterface
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * Return Auth0 Roles.
   */
  public function getUserRoles() {
    return $this->roles;
  }

  /**
   * @return array
   */
  public function getAllUserData() {
    return $this->userData;
  }

}
