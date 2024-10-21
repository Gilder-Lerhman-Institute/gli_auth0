<?php

namespace Drupal\gli_auth0_profile\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\gli_auth0\Service\Auth0Service;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Profile Completed Event Subscriber class.
 */
class ProfileCompletedEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Auth0 Service.
   *
   * @var \Drupal\gli_auth0\Service\Auth0Service
   */
  protected Auth0Service $auth0Service;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Current user Service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;


  /**
   * The Masquerade service if it exists, or NULL.
   *
   * @var \Drupal\masquerade\Masquerade|null
   */
  protected $masquerade;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The Current User service.
   * @param \Drupal\gli_auth0\Service\Auth0Service $auth0Service
   *   Auth0 Service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack Service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger Service.
   * @param null $masquerade
   *   Masquerade Service.
   */
  public function __construct(
    AccountProxyInterface $currentUser,
    Auth0Service $auth0Service,
    RequestStack $requestStack,
    MessengerInterface $messenger,
    $masquerade = NULL
  ) {
    $this->currentUser = $currentUser;
    $this->auth0Service = $auth0Service;
    $this->request = $requestStack->getCurrentRequest();
    $this->messenger = $messenger;
    $this->masquerade = $masquerade;
  }

  /**
   * Event Callback to check if someone completed registration.
   */
  public function checkForCompletedRegistration(RequestEvent $event) {
    $route_name = $this->request->attributes->get(RouteObjectInterface::ROUTE_NAME);
    $ignore_route = in_array($route_name, [
      // System Routes:
      'system.403',
      // 'entity.user.edit_form',
      // 'entity.user.view',
      'system.ajax',
      'user.logout',
      'admin_toolbar_tools.flush',
      'user.pass',
      'media.oembed_iframe',
      // Contrib Routes:
      // 'user_current_paths.edit_redirect',
      // Gli Auth0 Routes:
      'gli_auth0.callback',
      // 'gli_auth0_profile.profile_update',
      'gli_auth0_profile.complete_registration',
      'gli_auth0.logout',
      'gli_auth0_profile.registration_done'
    ]);

    // Ignore route for jsonapi calls.
    if (strpos($route_name, 'jsonapi') !== FALSE) {
      return;
    }

    $is_ajax = $this->request->isXmlHttpRequest();

    // There needs to be an explicit check for non-anonymous or else
    // this will be tripped and a forced redirect will occur.
    if ($this->currentUser->isAuthenticated() && !$ignore_route && !$is_ajax) {

      // Bail early if the current user is masquerading. Masquerade should not
      // force a password reset.
      if (isset($this->masquerade)) {
        if ($this->masquerade->isMasquerading()) {
          return;
        }
      }

      $registration_complete = FALSE;
      $auth0Id = $this->auth0Service->getUserAuth0Id($this->currentUser->id());
      $is_auth0_user = $auth0Id !== NULL;

      if ($is_auth0_user) {
        $registration_complete = $this->auth0Service->isRegistrationComplete($auth0Id);
      }

      if ($is_auth0_user && !$registration_complete) {
        $url = gli_auth0_profile_get_registration_url(
          Url::fromUserInput($event->getRequest()->getRequestUri())->toString()
        );
        $url = $url->setAbsolute()->toString();
        $event->setResponse(new RedirectResponse($url));
        $this->messenger->addError(
          $this->t('Registration must be completed before unlocking other parts of the site.')
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['checkForCompletedRegistration', 20];
    return $events;
  }

}
