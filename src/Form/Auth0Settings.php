<?php

namespace Drupal\gli_auth0\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gli_auth0\Service\Auth0Service;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auth0 Settings.
 */
final class Auth0Settings extends ConfigFormBase {

  /**
   * Auth0 Service.
   *
   * @var \Drupal\gli_auth0\Service\Auth0Service
   */
  protected Auth0Service $auth0Service;

  /**
   * Entity Type Manager Service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Auth0Service $auth0Service,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct($config_factory);
    $this->auth0Service = $auth0Service;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('gli_auth0'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gli_auth0_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('gli_auth0.settings');

    $form['auth0'] = [
      '#type' => 'details',
      '#title' => $this->t('Auth0 Settings'),
      '#description' => $this->t('Following settings are used for connecting to Auth0 Services'),
      '#tree' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['auth0']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('Auth0 domain for your tenant, found in your Auth0 Application settings.'),
      '#default_value' => $config->get('auth0')['domain'] ?? '',
    ];

    $form['auth0']['clientId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('Client ID, found in the Auth0 Application settings.'),
      '#default_value' => $config->get('auth0')['clientId'] ?? '',
    ];

    $form['auth0']['clientSecret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('Client Secret, found in the Auth0 Application settings.'),
      '#default_value' => $config->get('auth0')['clientSecret'] ?? '',
    ];

    $form['auth0']['cookieSecret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie Secret'),
      '#description' => $this->t('The secret used to derive an encryption key for the user identity in a session cookie and to sign the transient cookies used by the login callback.'),
      '#default_value' => $config->get('auth0')['cookieSecret'] ?? '',
    ];

    $form['auth0']['cookieExpires'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cookie Expires'),
      '#description' => $this->t('Defaults to 0. How long, in seconds, before cookies expire. If set to 0 the cookie will expire at the end of the session (when the browser closes).'),
      '#default_value' => $config->get('auth0')['cookieExpires'] ?? '',
    ];

    $form['manager'] = [
      '#type' => 'details',
      '#title' => $this->t('Management Settings'),
      '#description' => $this->t('Following settings are used for the management API'),
      '#tree' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['manager']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#description' => $this->t('Auth0 domain for your tenant, found in your Auth0 Application settings.'),
      '#default_value' => $config->get('manager')['domain'] ?? '',
    ];

    $form['manager']['clientId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('Client ID, found in the Auth0 Application settings.'),
      '#default_value' => $config->get('manager')['clientId'] ?? '',
    ];

    $form['manager']['clientSecret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('Client Secret, found in the Auth0 Application settings.'),
      '#default_value' => $config->get('manager')['clientSecret'] ?? '',
    ];

    $form['roles'] = [
      '#type' => 'details',
      '#title' => $this->t('Role Mapping'),
      '#description' => $this->t('Following settings are used for role mapping new users'),
      '#collapsed' => FALSE,
    ];

    $form['roles']['roles'] = [
      '#type' => 'table',
      '#title' => $this->t('Roles'),
      '#header' => [
        $this->t('Auth0 Role Label'),
        $this->t('Drupal Role'),
      ],
    ];

    $roles = $this->auth0Service->getAllRoles();
    $drupalRoles = $this->getDrupalRoles();
    foreach ($roles as $role) {
      $id = $role['id'];
      $form['roles']['roles'][$id]['label'] = [
        '#type' => 'markup',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#markup' => $role['name'] . '<br/>' . $role['description'],
      ];
      $form['roles']['roles'][$id]['role'] = [
        '#type' => 'select',
        '#title' => $this->t('Role'),
        '#title_display' => 'invisible',
        '#options' => $drupalRoles,
        '#default_value' => $config->get('roles')[$role['id']] ?? '',
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Return a list of all Drupal Roles.
   *
   * @return array
   *   Return an array of roles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getDrupalRoles(): array {
    /** @var \Drupal\user\RoleInterface[] $drupalRoles */
    $drupalRoles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    unset($drupalRoles['anonymous']);
    uasort($drupalRoles, function (RoleInterface $a, RoleInterface $b) {
      if ($a->getWeight() == $b->getWeight()) {
        return 0;
      }
      return $a->getWeight() > $b->getWeight() ? 1 : -1;
    });

    $drupalRoles = array_map(function (RoleInterface $role) {
      return $role->label();
    }, $drupalRoles);

    return array_merge([NULL => $this->t('- None -')], $drupalRoles);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    $roles = array_map(function (array $map) {
      return empty($map['role']) ? NULL : $map['role'];
    }, $form_values['roles']);

    $this->config('gli_auth0.settings')
      ->set('auth0', $form_values['auth0'])
      ->set('manager', $form_values['manager'])
      ->set('roles', $roles)
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gli_auth0.settings',
    ];
  }

}
