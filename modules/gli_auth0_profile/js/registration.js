(function (Drupal, drupalSettings) {
  'use strict';
  Drupal.gliAuth0Profile = Drupal.gliAuth0Profile || {};
  Drupal.gliAuth0Profile.setRedirect = function (redirectUrl) {
    // Go to the endpoint and if the response is ok then redirect to the provided destination.
    setTimeout(function () {
      fetch("/auth0/registration-complete")
        .then((response) => response.text())
        .then(function (text) {
          if (text === "ok") {
            window.location = redirectUrl;
          } else {
            Drupal.gliAuth0Profile.setRedirect(redirectUrl);
          }
        });
    }, 2000);
  };

  Drupal.behaviors.completeListener = {
    attach: function () {
      const init = function () {
        $Lightning.use(drupalSettings.gli_auth0_profile_registration.app_name,
          function () {
            $Lightning.createComponent(
              drupalSettings.gli_auth0_profile_registration.component_name,
              drupalSettings.gli_auth0_profile_registration.form_data,
              "container",
              function (cmp) {}
            );
          },
          drupalSettings.gli_auth0_profile_registration.experience_cloud
        );
      };
      if (!document.querySelector('#container').hasChildNodes()) {
        setTimeout(init, 100);
      }

      document.addEventListener("registration_complete", () => {
        Drupal.gliAuth0Profile.setRedirect(drupalSettings.gli_auth0_profile_registration.redirect_url ?? '/');
      });
    }
  };
})(Drupal, drupalSettings);
