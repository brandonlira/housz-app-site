(function (Drupal, drupalSettings) {
  Drupal.behaviors.bee = {
    attach: function (context, drupalSettings) {
      once('bee', 'html', context).forEach(function (context) {
        // window.onload = function() {
        // };
      });
    }
  }
}
(Drupal, drupalSettings));
