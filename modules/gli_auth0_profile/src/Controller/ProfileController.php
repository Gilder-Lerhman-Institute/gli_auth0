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

      $currentUser = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
      $roles = $this->auth0Service->getUserRoles($auth0Id);
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

    $auth0UserId = $this->auth0Service->getUserAuth0Id($this->currentUser()->id());

    return [
      '#type' => 'inline_template',
      '#template' => '
        <div id="registration-form">
          <style>
          .slds-scope .slds-select_container .slds-select {
              height: auto;
              padding-top: 0.5rem;
              padding-bottom: 0.5rem;
          }
          </style>
          <div id="container"></div>
          <script type="text/javascript" src="https://embeddedflow-developer-edition.ap24.force.com/lightning/lightning.out.js"></script>
          <script type="text/javascript">
            const init = function(){
                $Lightning.use("c:{{ app_name }}",
                    function() {
                        $Lightning.createComponent(
                            "c:{{ component_name }}",
                            { "Auth0ID": "{{ auth0_id }}", "EmailAddress": "{{ auth0_email }}", "DrupalID": "{{ auth0_drupal_id }}", "WebsiteSource": "{{ auth0_website }}" },
                            "container",
                            function(cmp) {}
                        );
                    },
                    "{{ experience_cloud }}"  // Experience Cloud site endpoint
                );
            };
            setTimeout(init, 100);

            // Check to see if OK then redirect.
            function setRedirect() {
              // Go to the endpoint and if the response is ok then redirect to the provided destination.
              setTimeout(function() {
                fetch("/auth0/registration-complete")
                  .then((response) => response.text())
                  .then(function(text) {
                    if (text === "ok") {
                      window.location = "{{ auth0_destination_url }}";
                    } else {
                      setRedirect();
                    }
                  })
              }, 2000);
            }

            // Once the registration is complete redirect.
            document.addEventListener("registration_complete", event => {
              setRedirect();
            });
          </script>
		    </div>',
      '#context' => [
        'experience_cloud' => $this->config('gli_auth0.settings')->get('profile')['url'],
        'app_name' => $this->config('gli_auth0.settings')->get('profile')['flow']['registration']['app_name'],
        'component_name' => $this->config('gli_auth0.settings')->get('profile')['flow']['registration']['component_name'],
        'auth0_id' => $auth0UserId,
        'auth0_email' => $this->currentUser()->getEmail(),
        'auth0_drupal_id' => $this->currentUser()->id(),
        'auth0_destination_url' => $this->requestStack->getCurrentRequest()->query->get('redirect_after', '/'),
        'auth0_website' => $this->requestStack->getCurrentRequest()->getHttpHost(),
      ],
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
