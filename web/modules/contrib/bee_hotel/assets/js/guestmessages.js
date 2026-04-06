(function (Drupal, drupalSettings) {
  Drupal.behaviors.bee_hotel = {
    attach: function (context, drupalSettings) {
      // Apply only once to the guest-messages container.
      once('bee_hotel', '#guest-messages', context).forEach(function (container) {

        // ============================================
        // 1. Message copier functionality
        // ============================================
        const copyButtons = container.querySelectorAll('.message-copier');
        copyButtons.forEach(function (button) {
          button.addEventListener('click', function (e) {
            e.preventDefault();
            var id = 'message-' + button.dataset.id;
            copyToClipboard(id);
          });
        });

        function copyToClipboard(id) {
          var element = document.getElementById(id);
          if (!element) {
            console.log('Element not found: ' + id);
            return;
          }

          var range = document.createRange();
          range.selectNode(element);
          window.getSelection().removeAllRanges();
          window.getSelection().addRange(range);

          try {
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            // Show temporary feedback.
            var copyButton = document.querySelector('.message-copier[data-id="' + id.replace('message-', '') + '"]');
            if (copyButton) {
              var originalText = copyButton.textContent;
              copyButton.textContent = Drupal.t('copied!');
              setTimeout(function() {
                copyButton.textContent = originalText;
              }, 2000);
            }
          } catch (err) {
            console.log('Unable to copy!', err);
          }
        }

        // ============================================
        // 2. Toggle email sends list functionality
        // ============================================
        const emailHeaders = container.querySelectorAll('.email-sends-header');

        emailHeaders.forEach(function (header) {
          // Remove any existing listeners to avoid duplicates.
          header.removeEventListener('click', toggleEmailSends);
          header.addEventListener('click', toggleEmailSends);
        });

        function toggleEmailSends(event) {
          var header = event.currentTarget;
          var targetId = header.getAttribute('data-toggle');

          if (!targetId) {
            console.log('No data-toggle attribute found');
            return;
          }

          var content = document.getElementById(targetId.substring(1)); // Remove the # from the ID

          if (!content) {
            console.log('Content element not found: ' + targetId);
            return;
          }

          // Toggle classes.
          header.classList.toggle('open');
          content.classList.toggle('collapsed');
          content.classList.toggle('expanded');

          // Optional: Log for debugging.
          // console.log('Toggled:', targetId, 'collapsed:', content.classList.contains('collapsed'));
        }
      });
    }
  };
})(Drupal, drupalSettings);
