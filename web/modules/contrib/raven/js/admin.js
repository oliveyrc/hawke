/**
 * @file
 * Sends test message to Sentry.
 */

((Drupal, Sentry) => {
  const printOutput = (element, message) => {
    const output = document.createElement('output');
    output.setAttribute('for', element.id);
    output.setAttribute('role', 'status');
    output.style.display = 'block';
    output.innerHTML = message;
    element.parentNode.insertBefore(output, element.nextSibling);
  };
  const printLog = (element, logs) => {
    logs.forEach((log) => {
      printOutput(
        element,
        Drupal.t('Logged @level: @message', {
          '@level': log.level,
          '@message': log.message,
        }),
      );
    });
  };
  const jsButton = document.getElementById('edit-raven-js-test');
  if (Sentry.isInitialized() && jsButton) {
    jsButton.disabled = false;
    jsButton.classList.remove('is-disabled');
    jsButton.addEventListener('click', (event) => {
      event.preventDefault();
      const id = Sentry.captureMessage(
        Drupal.t('Test message @time.', { '@time': new Date() }),
      );
      printOutput(
        jsButton,
        Drupal.t('Message sent as event %id.', { '%id': id }),
      );
    });
  }
  const phpButton = document.getElementById('edit-raven-php-test');
  if (phpButton) {
    phpButton.disabled = false;
    phpButton.classList.remove('is-disabled');
    phpButton.addEventListener('click', (event) => {
      event.preventDefault();
      fetch(Drupal.url('raven/test'), {
        method: 'POST',
        // The route requires this non-safelisted MIME type for purposes of
        // blocking cross-origin requests.
        headers: { 'Content-Type': 'application/json' },
      })
        .then((response) => response.json())
        .then((data) => {
          printLog(phpButton, data.log);
          printOutput(
            phpButton,
            Drupal.t(data.id ? 'Message sent as event %id.' : 'Send failed.', {
              '%id': data.id,
            }),
          );
        });
    });
  }
  const logsButton = document.getElementById('edit-raven-php-test-logs');
  if (logsButton) {
    logsButton.disabled = false;
    logsButton.classList.remove('is-disabled');
    logsButton.addEventListener('click', (event) => {
      event.preventDefault();
      fetch(Drupal.url('raven/test/logs'), {
        method: 'POST',
        // The route requires this non-safelisted MIME type for purposes of
        // blocking cross-origin requests.
        headers: { 'Content-Type': 'application/json' },
      })
        .then((response) => response.json())
        .then((data) => {
          printLog(logsButton, data.log);
          printOutput(
            logsButton,
            Drupal.t(data.id ? 'Log sent as event %id.' : 'Send failed.', {
              '%id': data.id,
            }),
          );
        });
    });
  }
})(Drupal, window.Sentry);
