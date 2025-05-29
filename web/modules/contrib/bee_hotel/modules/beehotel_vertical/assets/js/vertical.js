(function (Drupal, drupalSettings) {
  Drupal.behaviors.beehotel_vertical = {
    attach: function (context, drupalSettings) {
      once('beehotel_vertical', 'html', context).forEach(function (context) {
        //console.log("js del vertical");
      });
    }
  }
} (Drupal, drupalSettings));
