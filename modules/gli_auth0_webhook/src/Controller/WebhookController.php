<?php

namespace Drupal\gli_auth0_webhook\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\gli_auth0_webhook\Events\Auth0LogEvent;
use Drupal\gli_auth0_webhook\Events\Auth0LogEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Webhook Controller class.
 */
final class WebhookController extends ControllerBase {

  /**
   * Request Stack Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Event Dispatcher Service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event Dispatcher Service.
   */
  public function __construct(RequestStack $requestStack, EventDispatcherInterface $eventDispatcher) {
    $this->requestStack = $requestStack;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Webhook used for Reading in Logs.
   */
  public function webhook(Request $request) {
    $changes = Json::decode($request->getContent());

    foreach ($changes as $change) {
      if (($eventType = Auth0LogEvents::getEvent($change['data']['description'])) !== NULL) {
        $event = new Auth0LogEvent($eventType, $change['data']['details']['request']);
        $this->eventDispatcher->dispatch($event, $eventType);
      }
    }

    return new JsonResponse("ok");
  }

  /**
   * Webhook Access Check.
   */
  public function webhookAccess(AccountInterface $account) {
    $bearer = $this->requestStack->getCurrentRequest()->headers->get('Authorization');
    $token = $this->state()->get('gli_auth0_webhook_token', 'changeme');
    $check = sprintf('Bearer %s', $token);
    return AccessResult::allowedIf($bearer === $check);
  }

}
