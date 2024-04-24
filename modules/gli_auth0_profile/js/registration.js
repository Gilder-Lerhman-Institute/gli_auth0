(function(Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.completeListener = {
    attach: function () {
      const init = function(){
        $Lightning.use("c:" + drupalSettings.gli_auth0_profile.app_name,
          function() {
            $Lightning.createComponent(
              "c:" + drupalSettings.gli_auth0_profile.component_name,
              JSON.stringify(drupalSettings.gli_auth0_profile.form_data),
              "container",
              function(cmp) {}
            );
          },
          drupalSettings.gli_auth0_profile.experience_cloud
        );
      };
      setTimeout(init, 100);

      Drupal.gliAuth0Profile = Drupal.gliAuth0Profile || {};
      Drupal.gliAuth0Profile.setRedirect = function(redirectUrl) {
        // Go to the endpoint and if the response is ok then redirect to the provided destination.
        setTimeout(function() {
          fetch("/auth0/registration-complete")
            .then((response) => response.text())
            .then(function(text) {
              if (text === "ok") {
                window.location = redirectUrl;
              } else {
                Drupal.gliAuth0Profile.setRedirect(redirectUrl);
              }
            })
        }, 2000);
      };

      document.addEventListener("registration_complete", event => {
        Drupal.gliAuth0Profile.setRedirect(drupalSettings.gli_auth0_profile.redirect_url ?? '/');
      });
    }
  };
})(Drupal, drupalSettings);
