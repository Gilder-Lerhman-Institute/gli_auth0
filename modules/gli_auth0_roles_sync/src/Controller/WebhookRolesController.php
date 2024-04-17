<?php

namespace Drupal\gli_auth0_roles_sync\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\gli_auth0\Service\Auth0Service;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The following controller is used as an endpoint to ping and sync user roles.
 */
final class WebhookRolesController extends ControllerBase {

  /**
   * Request Stack Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Auth0 Service.
   *
   * @var \Drupal\gli_auth0\Service\Auth0Service
   */
  protected Auth0Service $auth0Service;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('gli_auth0')
    );
  }

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\gli_auth0\Service\Auth0Service $auth0Service
   *   The auth0 service.
   */
  public function __construct(RequestStack $requestStack, Auth0Service $auth0Service) {
    $this->requestStack = $requestStack;
    $this->auth0Service = $auth0Service;
  }

  /**
   * Webhook used for Reading in Logs.
   */
  public function webhook(Request $request) {
    $changes = Json::decode($request->getContent());

    if (isset($changes['auth0_id'])) {
      $this->auth0Service->updateUserRoles($changes['auth0_id']);
    }

    return new JsonResponse("ok");
  }

}
