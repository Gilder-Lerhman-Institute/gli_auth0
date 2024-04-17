var initESW = function(gslbBaseURL) {
  embedded_svc.settings.displayHelpButton = true; //Or false
  embedded_svc.settings.language = ''; //For example, enter 'en' or 'en-US'

  embedded_svc.settings.defaultMinimizedText = 'Registration'; //(Defaults to Chat with an Expert)
  //embedded_svc.settings.disabledMinimizedText = '...'; //(Defaults to Agent Offline)

  //embedded_svc.settings.loadingText = ''; //(Defaults to Loading)
  //embedded_svc.settings.storageDomain = 'yourdomain.com'; //(Sets the domain for your deployment so that visitors can navigate subdomains during a chat session)

  // Settings for Flows

  embedded_svc.settings.enabledFeatures = ['Flows'];
  embedded_svc.settings.entryFeature = 'Flows';

  embedded_svc.init(
    'https://gilderlehrman--auth0.sandbox.my.salesforce.com',
    'https://gilderlehrman--auth0.sandbox.my.site.com/auth0',
    gslbBaseURL,
    '00D770000004chk',
    'Registration_Service',
    {

    }
  );
};

if (!window.embedded_svc) {
  var s = document.createElement('script');
  s.setAttribute('src', 'https://gilderlehrman--auth0.sandbox.my.salesforce.com/embeddedservice/5.0/esw.min.js');
  s.onload = function() {
    initESW(null);
  };
  document.body.appendChild(s);
} else {
  initESW('https://service.force.com');
}
