<?php

namespace Drupal\gli_auth0\Service;

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Store\SessionStore;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Auth0 Service Class used for communication.
 */
class Auth0Service {

  /**
   * Auth0 Settings.
   *
   * @var array
   */
  protected array $settings = [];

  /**
   * Manager Settings API.
   *
   * @var array
   */
  protected array $managerSettings = [];

  /**
   * Return list of array mapping.
   *
   * @var array
   */
  protected array $roles = [];

  /**
   * Auth0 SDK Instance.
   *
   * @var \Auth0\SDK\Auth0
   */
  protected ?Auth0 $sdk = NULL;

  /**
   * Instance for Manager API.
   *
   * @var \Auth0\SDK\Auth0
   */
  protected ?Auth0 $manager = NULL;

  /**
   * Database Service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * User Storage Service.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * User Data Service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected UserDataInterface $userData;

  /**
   * Language Manager Service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory Service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database Service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager Service.
   * @param \Drupal\user\UserDataInterface $userData
   *   User Data Storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   Language Manager Service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    UserDataInterface $userData,
    LanguageManagerInterface $languageManager
  ) {
    $settings = $configFactory->get('gli_auth0.settings');
    $this->settings = $settings->get('auth0');
    $this->managerSettings = $settings->get('manager');
    $this->roles = $settings->get('roles') ?? [];
    $this->database = $database;
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->userData = $userData;
    $this->languageManager = $languageManager;
  }

  /**
   * Return the sdk configuration.
   *
   * @return \Auth0\SDK\Auth0
   *   The Auth0 manager.
   */
  public function getSdk(): Auth0 {
    if (is_null($this->sdk)) {
      $this->sdk = $this->createSdk($this->settings);
    }
    return $this->sdk;
  }

  /**
   * Create the manager SDK.
   *
   * @return \Auth0\SDK\Auth0
   *   The Auth0 manager.
   */
  protected function getManager(): Auth0 {
    if (is_null($this->manager)) {
      $this->manager = $this->createSdk($this->managerSettings);
    }
    return $this->manager;
  }

  /**
   * Create a new SDK Instance.
   *
   * @param array $settings
   *   The manager settings.
   *
   * @return \Auth0\SDK\Auth0
   *   The Auth0 manager.
   */
  protected function createSdk(array $settings): Auth0 {
    $configuration = new SdkConfiguration($settings);
    $configuration->setSessionStorage(new SessionStore($configuration, $configuration->getSessionStorageId()));
    $configuration->setTransientStorage(new SessionStore($configuration, $configuration->getTransientStorageId()));
    $configuration->pushScope('offline_access');
    return new Auth0($configuration);
  }

  /**
   * Find the User by Auth0 ID.
   *
   * @param string $id
   *   The Auth0 ID of the user.
   *
   * @return \Drupal\user\UserInterface|null
   *   Return the user if found or null if not.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function findUserByAuth0Id(string $id): ?UserInterface {
    $query = $this->database
      ->select('users_data', 'ud')
      ->fields('ud')
      ->condition('module', 'gli_auth0')
      ->condition('name', 'auth0_id')
      ->condition('value', $id);

    $result = $query->execute()->fetchAssoc();

    if (empty($result)) {
      return NULL;
    }

    return $this->userStorage->load($result['uid']);
  }

  /**
   * Check to see if the User is an Auth0 User.
   *
   * @param int $uid
   *   User ID to check against.
   *
   * @return bool
   *   TRUE if user is Auth0 user. FALSE if otherwise.
   */
  public function isAuth0User(int $uid) {
    return $this->getUserAuth0Id($uid) !== NULL;
  }

  /**
   * Return the user's Auth0 ID.
   *
   * @param int $uid
   *   ID of the user used to fetch data.
   *
   * @return array|mixed
   *   The user data.
   */
  public function getUserAuth0Id(int $uid) {
    return $this->userData->get('gli_auth0', $uid, 'auth0_id');
  }

  /**
   * Find the user by email.
   *
   * @param string $email
   *   The email address to search for.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user within the system or null if not found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function findUserByEmail(string $email): ?UserInterface {
    $results = $this->userStorage->loadByProperties([
      'mail' => $email,
    ]);

    if (empty($results)) {
      return NULL;
    }

    /** @var \Drupal\user\UserInterface $user */
    $user = array_pop($results);

    return $user;
  }

  /**
   * Create the Auth0 Shell.
   *
   * @return \Drupal\user\UserInterface
   *   The newly created user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createAuth0User(): UserInterface {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->create();
    $user->enforceIsNew();

    $language = $this->languageManager->getCurrentLanguage()->getId();
    $user->set('langcode', $language);
    $user->set('preferred_langcode', $language);
    $user->set('preferred_admin_langcode', $language);

    return $user;
  }

  /**
   * Search for the user using the ID.
   *
   * @param string $id
   *   The user's Auth0 ID.
   * @param string $email
   *   The user's email address.
   *
   * @return \Drupal\user\UserInterface
   *   The user that was found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function findTheUser(string $id, string $email): UserInterface {
    $user = $this->findUserByAuth0Id($id);

    // Search if the User exists.
    if (is_null($user)) {
      $user = $this->findUserByEmail($email);
      if (is_null($user)) {
        /** @var \Drupal\user\UserInterface $user */
        $user = $this->createAuth0User();
      }
    }

    $random = new Random();
    $user->setPassword($random->string(16, TRUE));
    $user->setEmail($email);
    $user->setUsername($email);
    $user->activate();
    $user->save();

    $this->userData
      ->set('gli_auth0', $user->id(), 'auth0_id', $id);

    // Update the app metadata with the drupal user id.
    $this->getManager()->management()->users()->update($id, ['app_metadata' => ['drupal_id' => $user->id()]]);

    return $user;
  }

  /**
   * Return a list of user roles for Auth0 user.
   *
   * @param string $id
   *   The user's id within Auth0.
   *
   * @return array
   *   Return the list of all user role's.
   *
   * @throws \Auth0\SDK\Exception\ArgumentException
   *   Auth0 SDK throws this error if the $id argument is missing.
   * @throws \Auth0\SDK\Exception\NetworkException
   *   Auth0 SDK throws this error if the API request fails.
   */
  public function getUserRoles(string $id): array {
    $data = $this->getManager()->management()->users()->getRoles($id);
    return $this->getParseData($data);
  }

  /**
   * Get a list of all roles.
   *
   * @return array
   *   Return array of roles.
   *
   * @throws \Auth0\SDK\Exception\NetworkException
   *   Auth0 SDK throws this error if the API request fails.
   * @throws \RuntimeException
   */
  public function getAllRoles(): array {
    $data = $this->getManager()->management()->roles()->getAll();
    return $this->getParseData($data);
  }

  /**
   * Parse a response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response to parse.
   *
   * @return array
   *   Return information for the response.
   *
   * @throws \RuntimeException
   */
  protected function getParseData(ResponseInterface $response): array {
    if ($response->getStatusCode() !== 200) {
      return [];
    }

    return Json::decode($response->getBody()->getContents());
  }

  /**
   * Update a user's password on Auth0.
   *
   * @param string $auth0id
   *   Auth0 ID of user to update.
   * @param string $newPassword
   *   New password to pass through for the user.
   *
   * @return bool
   *   Returns TRUE if all is good.
   *
   * @throws \Exception
   *   If there are any issues throws a generic exception.
   */
  public function updateUserPassword(string $auth0id, string $newPassword): bool {
    $body = [
      'password' => $newPassword,
    ];
    $response = $this->getManager()->management()->users()->update($auth0id, $body);
    $body = $response->getBody()->getContents();
    $json = JSON::decode($body);

    if ($response->getStatusCode() !== 200) {
      $message = $json['message'];
      throw new \Exception($message, $response->getStatusCode());
    }

    return TRUE;
  }

  /**
   * Update the user's email on Auth0.
   *
   * @param string $auth0id
   *   Auth0 ID of user to update.
   * @param string $newEmail
   *   New password to pass through for the user.
   *
   * @return bool
   *   Returns TRUE if all is good.
   *
   * @throws \Exception
   *   If there are any issues throws a generic exception.
   */
  public function updateUserEmail(string $auth0id, string $newEmail): bool {
    $user = $this->findUserByAuth0Id($auth0id);

    if (is_null($user)) {
      throw new \Exception('Cannot find user in Drupal system');
    }

    $this->getManager()->management()->users()->update($auth0id, [
      'email' => $newEmail,
      'app_metadata' => [
        'lms_update' => TRUE,
      ],
    ]);
    return TRUE;
  }

  /**
   * Update the user's roles.
   *
   * @param string $id
   *   Auth0 ID.
   *
   * @return bool
   *   Return true if successful, false if issues.
   */
  public function updateUserRoles(string $id): bool {
    $user = $this->findUserByAuth0Id($id);

    if (!is_null($user)) {

      // Get all of users roles.
      $auth0UserRoles = array_map(function (array $role) {
        return $role['id'];
      }, $this->getUserRoles($id));
      // Get all the mapped roles.
      $roleMapping = array_filter($this->roles);
      // Get all the drupal roles that are mapped.
      $mappedDrupalRoles = array_unique(array_values($roleMapping));

      // Keep track if we should save the user.
      $save = FALSE;

      // Loop through all currently mapped roles and remove them from the user.
      foreach ($mappedDrupalRoles as $drupalRole) {
        if ($user->hasRole($drupalRole)) {
          $save = TRUE;
          $user->removeRole($drupalRole);
        }
      }

      // Loop through all the Auth0 Roles and apply them to the user.
      foreach ($auth0UserRoles as $roleId) {
        if (!empty($roleMapping[$roleId])) {
          $save = TRUE;
          $user->addRole($roleMapping[$roleId]);
        }
      }

      if ($save) {
        try {
          $user->save();
        }
        catch (EntityStorageException $e) {
          return FALSE;
        }
      }

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the user from auth0.
   *
   * @param string $auth0Id
   *   The Auth0 ID.
   *
   * @return array
   *   The Auth0 User Data.
   */
  public function getManagerUser(string $auth0Id) {
    $body = $this->getManager()->management()->users()->get($auth0Id)->getBody();
    return Json::decode($body);
  }

  /**
   * Get the user from auth0 based on Drupal user id.
   *
   * @param int $uid
   *   The Drupal user ID to fetch from Auth0.
   *
   * @return array
   *   The Auth0 User Data.
   */
  public function getManagerUserFromDrupalUid(int $uid) {
    $auth0Id = $this->getUserAuth0Id($uid);
    if (!$auth0Id) {
      return [];
    }
    $body = $this->getManager()->management()->users()->get($auth0Id)->getBody();
    return Json::decode($body);
  }

  /**
   * Return if the registration is complete.
   *
   * @param string $auth0Id
   *   The auth0 ID.
   *
   * @return bool
   *   Return true if the registration_complete is done.
   */
  public function isRegistrationComplete(string $auth0Id) {
    $auth0User = $this->getManagerUser($auth0Id);
    return isset($auth0User['app_metadata']['registration_complete']);
  }

  /**
   * Return the Salesforce ID.
   *
   * @param string $auth0Id
   *   The Auth0 ID.
   *
   * @return string
   *   Return the salesforce id.
   */
  public function getSalesforceId(string $auth0Id) {
    $auth0User = $this->getManagerUser($auth0Id);
    return ($auth0User['app_metadata']['salesforce_id'] ?? '');
  }

}
