<?php

namespace Drupal\gli_auth0_lazy_migration\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;

/**
 * Drush command to migrate users for lazy migration.
 */
class MigrateUsersCommand extends DrushCommands implements CustomEventAwareInterface {

  use CustomEventAwareTrait;

  /**
   * Database Connection Service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database Connection service.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Migrate the users into special table.
   *
   * @command gli:auth0:migrate-users
   *
   * @return string
   *   Return command status.
   */
  public function migrateUsers() {
    $query = "
SELECT
ufd.uid,
ufd.mail,
ufd.pass,
CONCAT(f_name.field_u_first_name_value,' ', l_name.field_u_last_name_value) as 'full_name',
f_name.field_u_first_name_value as 'first_name',
l_name.field_u_last_name_value as 'last_name',
term.name as 'school',
sf.salesforce_id as 'salesforce_id',
home.field_u_phone_home_value AS 'home',
work.field_u_phone_work_value AS 'work',
other.field_u_phone_other_value AS 'other',
mobile.field_u_phone_mobile_value AS 'mobile',
(SELECT GROUP_CONCAT(roles_target_id) from user__roles ur WHERE ur.entity_id = ufd.uid) AS 'roles'

FROM users_field_data ufd
LEFT JOIN user__field_u_first_name f_name ON (f_name.entity_id = ufd.uid)
LEFT JOIN user__field_u_last_name l_name ON (l_name.entity_id = ufd.uid)
LEFT JOIN user__field_u_affiliate_school a_school ON (a_school.entity_id = ufd.uid)
LEFT JOIN taxonomy_term_field_data term ON (term.tid = a_school.field_u_affiliate_school_target_id)
LEFT JOIN salesforce_mapped_object sf ON (sf.salesforce_mapping = 'drupal_user_salesforce_contact' AND sf.drupal_entity__target_type = 'user' AND sf.drupal_entity__target_id = ufd.uid)
LEFT JOIN user__field_u_phone_home home ON (home.entity_id = ufd.uid)
LEFT JOIN user__field_u_phone_mobile mobile ON (mobile.entity_id = ufd.uid)
LEFT JOIN user__field_u_phone_other other ON (other.entity_id = ufd.uid)
LEFT JOIN user__field_u_phone_work work ON (work.entity_id = ufd.uid)

WHERE ufd.login > UNIX_TIMESTAMP('2022-10-01 00:00:00') and ufd.mail IS NOT NULL
ORDER BY ufd.uid DESC";

    $result = $this->connection->query($query);

    if ($result) {

      while ($row = $result->fetchAssoc()) {
        $tmp = [];

        if (!empty($row['full_name'])) {
          $tmp['name'] = $row['full_name'];
        }
        if (!empty($row['last_name'])) {
          $tmp['family_name'] = $row['last_name'];
        }
        if (!empty($row['first_name'])) {
          $tmp['given_name'] = $row['first_name'];
        }

        $tmp['app_metadata'] = [
          "registration_complete" => TRUE,
          "drupal_id" => $row['uid'],
          "salesforce_id" => $row['salesforce_id'],
        ];

        $tmp['user_metadata'] = [
          "school_name" => $row['school'],
          "mobile_phone_number" => $row['mobile'],
          "home_phone_number" => $row['home'],
          "work_phone_number" => $row['work'],
          "other_phone_number" => $row['other'],
        ];

        $record = [
          'mail' => $row['mail'],
          'pass' => $row['pass'],
          'salesforce_id' => $row['salesforce_id'],
          'data' => Json::encode($tmp),
        ];

        $this->connection->upsert('gli_auth0_records')
          ->fields(['mail', 'pass', 'salesforce_id', 'data'])
          ->key('mail')->values($record)->execute();
      }
    }

    return dt('Users Migrated');
  }

}
