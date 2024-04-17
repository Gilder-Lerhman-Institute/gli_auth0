<?php

namespace Drupal\gli_auth0\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authentication Event.
 */
class AuthenticationEvent extends Event {

  /**
   * Url used for Redirect.
   *
   * @var Url|null
   */
  protected ?Url $url = null;

  /**
   * @var UserInterface|null
   */
  protected ?UserInterface $user = null;

  /**
   * Constructor.
   */
  public function __construct(protected Request $request, protected string $auth0Id, protected array $userData = [], protected array $roles = []) {

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
   * Set the current user.
   *
   * @param UserInterface $user
   *
   * @return $this
   */
  public function setUser(UserInterface $user) {
    $this->user = $user;
    return $this;
  }

  /**
   * Return the user.
   *
   * @return UserInterface|null
   */
  public function getUser(): ?UserInterface {
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

  /**
   * Set The Redirect URL for the user.
   *
   * @param Url $url
   *   Url to set.
   *
   * @return self
   */
  public function setRedirectUrl(Url $url) {
    $this->url = $url;
    return $this;
  }

  /**
   * Return the redirect URL.
   *
   * @return Url|null
   */
  public function getRedirectUrl() {
    return $this->url;
  }

  /**
   * Return the current request.
   *
   * @return Request
   */
  public function getRequest() {
    return $this->request;
  }
}
