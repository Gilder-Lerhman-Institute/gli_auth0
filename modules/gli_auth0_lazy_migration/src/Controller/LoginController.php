<?php

namespace Drupal\gli_auth0_lazy_migration\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Password\PasswordInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Login Controller used for the Auth0 Lazy Migration.
 */
final class LoginController extends ControllerBase {

  /**
   * Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Database Connection Service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Password Hasher Interface.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $password;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('password')
    );
  }

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request Stack Service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database Connection Service.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   Password Hasher Service.
   */
  public function __construct(
    RequestStack $requestStack,
    Connection $connection,
    PasswordInterface $password
  ) {
    $this->requestStack = $requestStack;
    $this->connection = $connection;
    $this->password = $password;
  }

  /**
   * Migration Access.
   */
  public function migrationAccess(AccountInterface $account) {
    $bearer = $this->requestStack->getCurrentRequest()->headers->get('Authorization');
    $token = $this->state()->get('gli_auth0_lazy_migration_token', 'changeme');
    $check = substr($bearer, 7);
    return AccessResult::allowedIf($token === $check);
  }

  /**
   * Validate the User Login.
   */
  public function migrationLogin() {
    $email = $this->requestStack->getCurrentRequest()->request->get('email');
    $password = $this->requestStack->getCurrentRequest()->request->get('pass');

    if (is_null($email) || is_null($password)) {
      return new JsonResponse(NULL, 401);
    }

    $users = $this->findUser($email);

    if (empty($users)) {
      return new JsonResponse(NULL, 401);
    }

    $user = array_shift($users);

    if (!$this->password->check($password, $user['pass'])) {
      return new JsonResponse(NULL, 401);
    }

    return new JsonResponse($this->getUser($user));
  }

  /**
   * Get information about the user.
   */
  public function migrationGetUser() {
    $email = $this->requestStack->getCurrentRequest()->request->get('email');
    $users = $this->findUser($email);

    if (empty($users)) {
      return new JsonResponse(NULL, 401);
    }
    $user = array_shift($users);
    return new JsonResponse($this->getUser($user));
  }

  /**
   * Search for a user by email.
   *
   * @param string $email
   *   The email address to search for.
   *
   * @return mixed
   *   The database record.
   */
  protected function findUser(string $email) {
    return $this->connection->select('gli_auth0_records')
      ->fields('gli_auth0_records')->condition('mail', $email, 'LIKE')
      ->execute()->fetchAllAssoc('mail', \PDO::FETCH_ASSOC);
  }

  /**
   * Return the structured data of a user.
   */
  protected function getUser(array $user) {
    $user['data'] = Json::decode($user['data']);
    return [
      'user_id' => md5(strtolower($user['mail'])),
      'email' => $user['mail'],
    ] + $user['data'];
  }

}
