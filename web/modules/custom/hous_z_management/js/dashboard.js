(function ($, Drupal) {
  'use strict';

  /**
   * Dashboard behavior.
   */
  Drupal.behaviors.houzDashboard = {
    attach: function (context, settings) {
      // Add any dashboard-specific JavaScript functionality here
      $('.houz-dashboard', context).once('houz-dashboard').each(function () {
        console.log('Houz dashboard loaded');
      });
    }
  };

})(jQuery, Drupal);