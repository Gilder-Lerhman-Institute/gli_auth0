<?php

namespace Drupal\gli_auth0_profile\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route Subscriber used for altering the title of the user edit form.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.user.edit_form')) {
      $defaults = $route->getDefaults();
      $defaults['_title_callback'] = '\Drupal\gli_auth0_profile\Controller\ProfileController:profileTitle';
      $route->setDefaults($defaults);
    }
    // Redirect User Profile Page to edit Page.
    if ($route = $collection->get('entity.user.canonical')) {
      $route->setDefaults([
        '_controller' => '\Drupal\gli_auth0_profile\Controller\ProfileController:redirectProfile',
      ]);
    }
  }

}
