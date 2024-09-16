<?php

namespace Drupal\gli_auth0\Controller;

use Auth0\SDK\Exception\StateException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\gli_auth0\Event\AuthenticationEvent;
use Drupal\gli_auth0\Event\GLIAuth0Events;
use Drupal\gli_auth0\Service\Auth0Service;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth0 Controller Class.
 */
final class Auth0Controller extends ControllerBase {

  /**
   * Auth0 Service.
   *
   * @var \Drupal\gli_auth0\Service\Auth0Service
   */
  protected Auth0Service $auth0Service;

  /**
   * Page Cache Service.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected KillSwitch $killSwitch;

  /**
   * Event Dispatcher Service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Request Stack Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gli_auth0'),
      $container->get('page_cache_kill_switch'),
      $container->get('event_dispatcher'),
      $container->get('request_stack'),
      $container->get('current_user')
    );
  }

  /**
   * Constructor.
   *
   * @param \Drupal\gli_auth0\Service\Auth0Service $auth0Service
   *   Auth0Service Service.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   KillSwitch Service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event Dispatcher Service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request Stack Service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Drupal\Core\Session\AccountProxy definition.
   */
  public function __construct(
    Auth0Service $auth0Service,
    KillSwitch $killSwitch,
    EventDispatcherInterface $eventDispatcher,
    RequestStack $requestStack,
    AccountProxyInterface $currentUser
  ) {
    $this->auth0Service = $auth0Service;
    $this->killSwitch = $killSwitch;
    $this->eventDispatcher = $eventDispatcher;
    $this->requestStack = $requestStack;
    $this->currentUser = $currentUser;
  }

  /**
   * Does the destination param exists.
   *
   * @return bool
   *   Returns TRUE if destination parameter is found.
   */
  protected function hasDestination() {
    return $this->requestStack->getCurrentRequest()->query->has('destination');
  }

  /**
   * Return the Redirect Destination.
   *
   * @return array
   *   Return the destination as an array.
   */
  protected function getDestinationArr() {
    if (!$this->hasDestination()) {
      return [];
    }
    return $this->getRedirectDestination()->getAsArray();
  }

  /**
   * Login Functionality.
   */
  public function login() {
    $this->auth0Service->getSdk()->clear();
    $query = $this->getDestinationArr();
    $url = Url::fromRoute('gli_auth0.callback', [],
      ['absolute' => TRUE, 'https' => TRUE, 'query' => $query]
    );

    // Construct and return a redirect response to avoid exceptions.
    // NOTE: https://drupal.stackexchange.com/questions/249791/d8-how-to-get-destination-without-compromising-trustedredirectresponse
    $response = new Response();
    // This method of getting a URL string lets us handle the caching metadata
    // and avoid an early render (caused by calling toString() w/o a param).
    // @see https://www.drupal.org/project/drupal/issues/2638686#comment-12282657
    $callback = $this->auth0Service
      ->getSdk()
      ->login($url->toString(TRUE)->getGeneratedUrl());
    $response->setStatusCode(302);
    $response->headers->set('LOCATION', $callback);
    return $response;
  }

  /**
   * Registration Endpoint.
   */
  public function register() {
    $this->killSwitch->trigger();
    $query = $this->getDestinationArr();
    $this->auth0Service->getSdk()->clear();
    $url = Url::fromRoute('gli_auth0.callback', [],
      ['absolute' => TRUE, 'https' => TRUE, 'query' => $query]
    );
    $callback = $this->auth0Service
      ->getSdk()
      ->signup($url->toString(TRUE)->getGeneratedUrl());
    $response = new Response();
    $response->setStatusCode(302);
    $response->headers->set('LOCATION', $callback);
    return $response;
  }

  /**
   * Callback Functionality.
   */
  public function callback(Request $request) {
    $this->killSwitch->trigger();

    $url = Url::fromRoute('gli_auth0.callback', [],
      ['absolute' => TRUE, 'https' => TRUE]
    );

    try {
      $this->auth0Service
        ->getSdk()
        ->exchange($url->toString(TRUE)->getGeneratedUrl());

      $auth0User = $this->auth0Service->getSdk()->getUser();
      $auth0id = $auth0User['sub'];

      $roles = $this->auth0Service->getUserRoles($auth0id);
      $fullUser = $this->auth0Service->getManagerUser($auth0id);

      $event = new AuthenticationEvent($request, $auth0id, $fullUser, $roles);
      $this->eventDispatcher->dispatch($event, GLIAuth0Events::AUTH0_LOGIN);

      $redirectUrl = $event->getRedirectUrl();
      if ($redirectUrl) {
        return new RedirectResponse($redirectUrl->setAbsolute()->toString());
      }

      return new RedirectResponse(Url::fromRoute('<front>')->setAbsolute()->toString());
    }
    catch (StateException $stateException) {
      $this->getLogger('gli_auth0')->error($stateException->getMessage());
      $this->messenger()->addError($stateException->getMessage());
    }

    return $this->redirect('user.login');
  }

  /**
   * Logout Functionality.
   */
  public function logout() {
    user_logout();
    $url = Url::fromRoute('<front>', [], ['absolute' => TRUE, 'https' => TRUE]);
    $callback = $this->auth0Service
      ->getSdk()
      ->logout($url->toString(TRUE)->getGeneratedUrl());
    $response = new Response();
    $response->setStatusCode(302);
    $response->headers->set('LOCATION', $callback);
    return $response;
  }

  /**
   * Create job to send verification email.
   */
  public function verifyEmail() {
    $auth0Id = $this->auth0Service->getUserAuth0Id($this->currentUser->id());
    $result = $this->auth0Service->createSendVerificationEmail($auth0Id);
    if ($result) {
      $this->messenger()->addMessage($this->t("Verification email has been sent. Please check your inbox and follow the instructions to verify your email. If you don't see it, check your spam or junk folder."));
    }
    else {
      $this->messenger()->addError($this->t('An error occurred while attempting to send the verification email. Please try again or contact support at support@gilderlehrman.org for assistance.'));
    }

    if ($this->moduleHandler->moduleExists('gli_user_dashboard')) {
      return $this->redirect('gli_user_dashboard.self');
    }
    else {
      return $this->redirect('<front>');
    }
  }

}
