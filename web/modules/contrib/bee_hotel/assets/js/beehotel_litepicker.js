(function (Drupal, drupalSettings) {
  Drupal.behaviors.bee_hotel = {
    attach: function (context) {
      const elements = once('bee_hotel', '.bee_hotel_search_availability', context);
      elements.forEach(function (element) {
        function getYesterdaysDate() {
          var date = new Date();
          date.setDate(date.getDate() - 1);
          var pieces = new Array(date.getFullYear(), (date.getMonth() + 1), date.getDate());
          return pieces.join('-');
        }
        let format = 'YYYY-MM-DD';
        let yestarday = getYesterdaysDate();
        const disallowedDates = [['2001-01-01', yestarday]];
        new Litepicker({
          element: element,
          singleMode: 0,
          format: 'D MMM YYYY',
          tooltipText: {
            one: 'night',
            other: 'nights'
          },
          tooltipNumber: (totalDays) => {
            return totalDays - 1;
          },
          lockDaysFormat:format,
          lockDays:disallowedDates,
        });
      });
    }
  }
} (Drupal, drupalSettings));
