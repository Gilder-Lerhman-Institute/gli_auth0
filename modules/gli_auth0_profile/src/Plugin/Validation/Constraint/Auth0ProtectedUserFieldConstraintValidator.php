<?php

namespace Drupal\gli_auth0_profile\Plugin\Validation\Constraint;

use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\user\Plugin\Validation\Constraint\ProtectedUserFieldConstraintValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the ProtectedUserFieldConstraint constraint.
 *
 * @see ProtectedUserFieldConstraintValidator
 */
class Auth0ProtectedUserFieldConstraintValidator extends ProtectedUserFieldConstraintValidator {

  /**
   * Auth0 Service.
   */
  protected Auth0Service $auth0Service;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->auth0Service = $container->get('gli_auth0');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!isset($items)) {
      return;
    }

    /** @var \Drupal\user\UserInterface|NULL $account */
    $account = $items->getEntity();
    if (!isset($account) || !empty($account->_skipProtectedUserFieldConstraint)) {
      // Looks like we are validating a field not being part of a user, or the
      // constraint should be skipped, so do nothing.
      return;
    }

    // If this user is an auth0 user skip the validation.
    if ($this->auth0Service->isAuth0User($account->id())) {
      return;
    }

    // Otherwise use the parent class for validation.
    parent::validate($items, $constraint);
  }

}
