# GLI Auth0 Migration - Lazy Migration

The following module acts as a lazy migration for Auth0. Part of the issue with
this is that there isn't a way to bring the Drupal hashed password over as part
of the bulk user import.

## Replacing the Authorization token

Currently, when a request is made to either of the endpoints it requires an
Authorization token.

This token starts off as `changeme` but to change it drush can be used.

```shell
terminus drush gliweb.[env] -- sset gli_auth0_lazy_migration_token 'newtoken'
```

That token should be updated within Auth0's Database section so that the service
continues to work.
