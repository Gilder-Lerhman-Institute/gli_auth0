<?php

namespace Drupal\gli_auth0\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\gli_auth0\Event\AuthenticationEvent;
use Drupal\gli_auth0\Event\GLIAuth0Events;
use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authentication Subscriber Service.
 */
class AuthenticationSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   *
   * @param Auth0Service $auth0Service
   * @param ModuleHandlerInterface $moduleHandler
   */
  public function __construct(protected Auth0Service $auth0Service, protected ModuleHandlerInterface $moduleHandler) {

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[GLIAuth0Events::AUTH0_LOGIN][] = ['authentication'];
    return $events;
  }

  /**
   * Authenticate the user.
   *
   * @param AuthenticationEvent $event
   */
  public function authentication(AuthenticationEvent $event) {
    $user = $this->auth0Service->findTheUser($event->getAuth0Id(), $event->getUserData('email'));
    user_login_finalize($user);

    $url = $this->getCallbackUrl(
      $user,
      $event->getRequest(),
      [
        'auth0User' => $event->getAllUserData(),
      ]
    );

    $event->setUser($user);
    $event->setRedirectUrl($url);
  }

  /**
   * Get the callback URL for the Callback to redirect.
   */
  protected function getCallbackUrl(
    UserInterface $user,
    Request $request,
    array $data = []
  ) {

    $destination = $request->query->get('destination', '/');
    $request->query->remove('destination');

    // If someone tries to use a full url for the destination throw an exception.
    if (str_starts_with($destination, 'http://') || str_starts_with($destination, 'https://')) {
      throw new \Exception('Full urls are not supported for destination parameter');
    }

    // Prepend the url with a `/` if it isn't already included.
    if (!str_starts_with($destination, '/')) {
      $destination = '/' . $destination;
    }

    $url = Url::fromUserInput($destination);

    $data = [
      'request' => $request,
      'user' => $user,
      'data' => $data,
    ];

    $this->moduleHandler->alter('auth0_callback_url', $url, $data);
    return $url;
  }

}
