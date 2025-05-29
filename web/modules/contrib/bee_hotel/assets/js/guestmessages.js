(function (Drupal, drupalSettings) {
  Drupal.behaviors.bee_hotel = {
    attach: function (context, drupalSettings) {
      once('beehotel_vertical', 'html', context).forEach(function (context) {
        const elements = once('bee_hotel', '#guest-messages', context);
        elements.forEach(function (element) {
          let elements = document.getElementsByClassName("message-copier");
          for(let i = 0; i < elements.length; i++) {
            elements[i].onclick = function () {
              var id = 'message-' + elements[i].dataset.id ;
              CopyToClipboard(id);
            }
          }

          function CopyToClipboard(id) {
            var r = document.createRange();
            r.selectNode(document.getElementById(id));
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(r);
            try {
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
            } catch (err) {
                console.log('Unable to copy!');
            }
          }
        });
      });
    }
  }
} (Drupal, drupalSettings));
