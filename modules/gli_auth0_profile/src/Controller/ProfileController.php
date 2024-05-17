<?php

namespace Drupal\gli_auth0_profile\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\gli_auth0\Event\GLIAuth0Events;
use Drupal\gli_auth0\Event\UpdateUserEvent;
use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profile Controller class.
 */
final class ProfileController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gli_auth0'),
      $container->get('request_stack'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Constructor.
   */
  public function __construct(protected Auth0Service $auth0Service, protected RequestStack $requestStack, protected EventDispatcherInterface $eventDispatcher) {

  }

  /**
   * Profile Title.
   */
  public function profileTitle(UserInterface $user = NULL) {
    if (empty($user->auth0_id) || !isset($user->auth0_id)) {
      return $user ? ['#markup' => $user->getDisplayName(), '#allowed_tags' => Xss::getHtmlTagList()] : '';
    }
    return ['#markup' => t('Update Website Credentials')];
  }

  /**
   * Confirm if user is registered.
   */
  public function registrationComplete() {
    $response = 'ok';
    try {
      $auth0User = $this->auth0Service->getSdk()->getUser();
      $auth0Id = $auth0User['sub'];
      $fullUser = $this->auth0Service->getManagerUser($auth0Id);
      if (!isset($fullUser['app_metadata']['registration_complete'])) {
        throw new \Exception('Registration not complete');
      }

      // Get the current user fromm the user id.
      $currentUser = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());

      // How many tries to get the roles.
      $tries = 5;

      // How many seconds should it wait in between.
      $sleepTime = 2;

      // Try to get all the records.
      for ($count = 0; $count < $tries; $count++) {
        $roles = $this->auth0Service->getUserRoles($auth0Id);
        if (!empty($roles)) {
          break;
        }

        if ($count < ($tries - 1)) { // Prevent sleep on the last iteration
          sleep($sleepTime);
        }
      }

      // Dispatch Event.
      $event = new UpdateUserEvent($currentUser, $auth0Id, $fullUser, $roles);
      $this->eventDispatcher->dispatch($event, GLIAuth0Events::AUTH0_USER_UPDATE);
    } catch (\Throwable $throwable) {
      $response = 'no';
    }

    return new Response($response);

  }

  /**
   * Salesforce Flow Registration Endpoint.
   */
  public function registration() {
    // If anonymous redirect to login.
    if ($this->currentUser()->isAnonymous()) {
      return $this->redirect('gli_auth0.authorize');
    }

    $auth0User = $this->auth0Service->getSdk()->getUser();
    // If registration complete redirect to front.
    if (isset($auth0User['gli/app_metadata']['registration_complete'])) {
      $this->messenger()->addStatus('Registration has already been completed.');
      return $this->redirect('<front>');
    }

    return [
      '#theme' => 'gli_auth0_profile_registration',
    ];
  }

  /**
   * Redirect Profile to Edit Page.
   *
   * @param \Drupal\user\Entity\User $user
   *   Current user.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect.
   */
  public function redirectProfile(User $user) {
    return new RedirectResponse(Url::fromRoute('entity.user.edit_form', ['user' => $user->id()])->toString());
  }

}
