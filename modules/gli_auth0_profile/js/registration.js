(function (Drupal, drupalSettings) {
  'use strict';
  let registrationShown = false;
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
      const observer = new MutationObserver((mutationList) => {
        for (const { addedNodes } of mutationList) {
          for (const node of addedNodes) {
            if (!node.tagName) continue; // not an element
            if (node.classList.contains('cEmbeddedFlow')) {
              // Remove placeholder content.
              const placeholderEl = document.querySelector('#container .registration-form-placeholder');
              if (placeholderEl != null) {
                placeholderEl.parentNode.removeChild(placeholderEl);
              }
            }
          }
        }
      });
      observer.observe(document.querySelector('#container'), {
        childList: true
      });

      const resizeObserver = new ResizeObserver((entries) => {
        for (const entry of entries) {
          // Scroll up when the form is updated.
          if (window.scrollY > 0) {
            window.scrollTo({ top: 0 });
          }
        }
      });
      resizeObserver.observe(document.querySelector('#container'));

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
      if (!registrationShown && document.querySelector('#container .cEmbeddedFlow') == null) {
        setTimeout(init, 100);
        registrationShown = true;
      }

      document.addEventListener("registration_complete", () => {
        Drupal.gliAuth0Profile.setRedirect(drupalSettings.gli_auth0_profile_registration.redirect_url ?? '/');
      });
    }
  };
})(Drupal, drupalSettings);
