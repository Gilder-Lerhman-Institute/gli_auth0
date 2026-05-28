<?php

namespace Drupal\gli_auth0_profile\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\user\UserDataInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks completed users from re-accessing the registration form.
 */
class RegistrationAccessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\gli_auth0\Service\Auth0Service $auth0Service
   *   The Auth0 service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected Auth0Service $auth0Service,
    protected RequestStack $requestStack,
    protected MessengerInterface $messenger,
    protected UserDataInterface $userData,
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Redirect completed users away from the registration form.
   */
  public function onRequest(RequestEvent $event) {
    $request = $this->requestStack->getCurrentRequest();
    $route_name = $request->attributes->get(RouteObjectInterface::ROUTE_NAME);

    if ($route_name !== 'gli_registration.form') {
      return;
    }

    if (!$this->moduleHandler->moduleExists('gli_registration')) {
      return;
    }

    if ($this->currentUser->isAnonymous()) {
      return;
    }

    $config = $this->configFactory->get('gli_registration.settings');
    if ($config->get('testing_mode') && $config->get('allow_registered_users')) {
      return;
    }

    $auth0Id = $this->auth0Service->getUserAuth0Id($this->currentUser->id());
    if (!$auth0Id) {
      return;
    }

    $completed = $this->auth0Service->isRegistrationComplete($auth0Id)
      || $this->userData->get('gli_registration', $this->currentUser->id(), 'completed');

    if ($completed) {
      $event->setResponse(new RedirectResponse(
        Url::fromRoute('<front>')->setAbsolute()->toString()
      ));
      $this->messenger->addStatus(
        $this->t('Registration has already been completed.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 20];
    return $events;
  }

}
