<?php

namespace Drupal\gli_auth0_profile\Plugin\Validation\Constraint;

use Drupal\user\Plugin\Validation\Constraint\ProtectedUserFieldConstraint;

/**
 * Extends an existing Constraint to override the email field for users.
 *
 * @see Drupal\user\Plugin\Validation\Constraint\ProtectedUserFieldConstraint
 */
class Auth0ProtectedUserFieldConstraint extends ProtectedUserFieldConstraint {}
