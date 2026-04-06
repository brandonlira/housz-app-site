/**
 * @file
 * Custom JavaScript for the theme.
 */

(function ($, Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Global behavior for the theme.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.myThemeBehavior = {
    attach: function (context, settings) {

      if (!document.getElementById('bybeehotel')) {

        const html = `<div id='bybeehotel'><a href='https://beehotel.pro' target='_blank'><img class='bee' src='https://beehotel.pro/sites/beehotel.pro/files/bee_2_36.png' style='width:24px;'><br/>by BeeHotel</a></div>`;

        // Crea un elemento temporaneo e inseriscilo
        const temp = document.createElement('div');
        temp.innerHTML = html;
        document.body.appendChild(temp.firstChild);
      }

      // BeeHotel banner functionality - usando once() di Drupal 11.
      once('beehotel-banner', '#bybeehotel', context).forEach(function (element) {
        var beeHotelDiv = element;

        var proIcon = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/%3E%3C/svg%3E";

        var proIconComplete = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'/%3E%3C/svg%3E";

        // Alternativa 2: Icona stella (premium)
        var proIconStar = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'/%3E%3C/svg%3E";

        // Alternativa 3: Icona supporto/assistenza
        var proIconSupport = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z'/%3E%3C/svg%3E";

        var freeIcon = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'/%3E%3C/svg%3E";

        var freeIconDownload = "data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'/%3E%3C/svg%3E";

        beeHotelDiv.innerHTML =
          '<a href="#" class="main-link">' +
            '<div class="bee-container">' +
              '<img class="bee" src="https://beehotel.pro/sites/beehotel.pro/files/bee_2_36.png" alt="BeeHotel">' +
              '<div class="main-text">' +
                '<span class="brand-name">BeeHotel</span>' +
              '</div>' +
              '<div class="sub-text">No fees | Dynamic prices | One-click messages</div>' +
            '</div>' +
          '</a>' +
          '<div class="options-container">' +
            '<a href="https://beehotel.pro" class="option-link" target="_blank">' +
              '<img src="' + proIconStar + '" class="option-icon" alt="Pro version">' +
              '<span>Full support [#beehotel.pro]</span>' +
            '</a>' +
            '<a href="https://www.drupal.org/project/bee_hotel" class="option-link" target="_blank">' +
              '<img src="' + freeIconDownload + '" class="option-icon" alt="Free version">' +
              '<span>Free to use [#Drupal.org]</span>' +
            '</a>' +
          '</div>';

        var mainLink = beeHotelDiv.querySelector('.main-link');
        var proLink = beeHotelDiv.querySelector('.option-link:first-child');
        var freeLink = beeHotelDiv.querySelector('.option-link:last-child');
        var isExpanded = false;
        var autoCloseTimeout;

        // Main banner click handler.
        mainLink.addEventListener('click', function (e) {
          e.preventDefault();

          if (!isExpanded) {
            beeHotelDiv.classList.add('expanded');
            isExpanded = true;

            // Auto close after 8 seconds.
            clearTimeout(autoCloseTimeout);
            autoCloseTimeout = setTimeout(function () {
              if (isExpanded) {
                beeHotelDiv.classList.remove('expanded');
                isExpanded = false;
              }
            }, 8000);
          }
          else {
            beeHotelDiv.classList.remove('expanded');
            isExpanded = false;
            clearTimeout(autoCloseTimeout);
          }
        });

        // Pro version link handler.
        proLink.addEventListener('click', function (e) {
          e.stopPropagation();
          beeHotelDiv.classList.remove('expanded');
          isExpanded = false;
          clearTimeout(autoCloseTimeout);
        });

        // Free version link handler.
        freeLink.addEventListener('click', function (e) {
          e.stopPropagation();
          beeHotelDiv.classList.remove('expanded');
          isExpanded = false;
          clearTimeout(autoCloseTimeout);
        });

        // Close when clicking outside.
        document.addEventListener('click', function (e) {
          if (isExpanded && !beeHotelDiv.contains(e.target)) {
            beeHotelDiv.classList.remove('expanded');
            isExpanded = false;
            clearTimeout(autoCloseTimeout);
          }
        });

        // Close with ESC key.
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && isExpanded) {
            beeHotelDiv.classList.remove('expanded');
            isExpanded = false;
            clearTimeout(autoCloseTimeout);
          }
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
