<?php

namespace Drupal\gli_auth0_webhook\Events;

/**
 * Events that happen for the webhooks.
 */
final class Auth0LogEvents {

  const ROLE_ADDED_TO_USER = "gli_auth0.role_added_to_user";
  const ROLE_REMOVED_FROM_USER = "gli_auth0.role_removed_from_user";

  const USER_ADDED_TO_ROLE = "gli_auth0.user_added_to_role";
  const USER_REMOVED_FROM_ROLE = "gli_auth0.user_removed_from_role";

  /**
   * Return a list of all the events and their mapping.
   *
   * @return array
   *   The mapping of events to events from Auth0.
   */
  public static function getMapping(): array {
    return [
      "Assign roles to a user" => self::ROLE_ADDED_TO_USER,
      "Assign users to a role" => self::USER_ADDED_TO_ROLE,
      "Removes roles from a user" => self::ROLE_REMOVED_FROM_USER,
      "Remove users from a role" => self::USER_REMOVED_FROM_ROLE,
    ];
  }

  /**
   * Return the event associated with the description.
   *
   * @param string $description
   *   The description of the event from Auth0 Log.
   *
   * @return string|null
   *   The event associated with the log event. Returns null if not set.
   */
  public static function getEvent(string $description): ?string {
    $list = self::getMapping();

    if (!isset($list[$description])) {
      return NULL;
    }

    return $list[$description];
  }

}
