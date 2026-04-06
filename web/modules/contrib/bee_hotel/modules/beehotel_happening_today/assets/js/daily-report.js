/**
 * @file
 * Interactive functionality for the daily report.
 */

(function($, Drupal) {
  'use strict';

  Drupal.behaviors.dailyReport = {
    attach: function(context, settings) {
      $('.daily-report', context).once('daily-report').each(function() {
        const $report = $(this);

        // Add refresh button functionality
        const $refreshButton = $('<button/>', {
          class: 'daily-report__refresh',
          html: '<span class="icon">🔄</span> Refresh',
          click: function() {
            location.reload();
          }
        });

        $('.daily-report__header', $report).append($refreshButton);

        // Add print button functionality
        const $printButton = $('<button/>', {
          class: 'daily-report__print',
          html: '<span class="icon">🖨️</span> Print',
          click: function() {
            window.print();
          }
        });

        $('.daily-report__header', $report).append($printButton);

        // Add expand/collapse functionality for sections
        $('.daily-report__section-title', $report).click(function() {
          const $section = $(this).closest('.daily-report__section');
          const $content = $section.find('.daily-report__grid, .daily-report__cleaning-grid, .daily-report__notes-grid, .daily-report__empty');

          $content.slideToggle(300);
          $section.toggleClass('daily-report__section--collapsed');
        });

        // Add room status updates
        $('.daily-report__cleaning-card', $report).click(function() {
          const $card = $(this);
          const $statusBadge = $card.find('.status-badge');

          if ($statusBadge.hasClass('status-badge--pending')) {
            $statusBadge
              .removeClass('status-badge--pending')
              .addClass('status-badge--in-progress')
              .text(Drupal.t('In progress'));
            $card.css('opacity', '0.7');
          } else if ($statusBadge.hasClass('status-badge--in-progress')) {
            $statusBadge
              .removeClass('status-badge--in-progress')
              .addClass('status-badge--completed')
              .text(Drupal.t('Completed'));
            $card.css('opacity', '0.5');
          } else {
            $statusBadge
              .removeClass('status-badge--completed')
              .addClass('status-badge--pending')
              .text(Drupal.t('To clean'));
            $card.css('opacity', '1');
          }
        });

        // Add keyboard navigation
        $report.on('keydown', function(e) {
          if (e.key === 'r' && e.ctrlKey) {
            e.preventDefault();
            location.reload();
          }
          if (e.key === 'p' && e.ctrlKey) {
            e.preventDefault();
            window.print();
          }
        });

        // Initialize tooltips for contact info
        $('.daily-report__contact').each(function() {
          const $contact = $(this);
          const text = $contact.text().trim();
          $contact.attr('title', text);
        });
      });
    }
  };

})(jQuery, Drupal);
