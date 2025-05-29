(function (Drupal, drupalSettings) {
  Drupal.behaviors.bat_fullcalendar = {
    attach: function (context, drupalSettings) {
      once('div', 'html', context).forEach(function (context) {
        window.onload = function(){
          function handyDates() {
            var dates = [];
            var today = new Date();
            var start = new Date();
            var end = new Date();
            start.setDate(today.getDate() - Math.abs(drupalSettings.batCalendar[0].bat_timerange_start));
            end.setDate(today.getDate() + drupalSettings.batCalendar[0].bat_timerange_end);
            dates['today'] = today.toISOString().split(".")[0];
            dates['start'] = start.toISOString().split(".")[0];
            dates['end'] = end.toISOString().split(".")[0];
            return dates;
          }

          function produceCall() {
            var dates = handyDates();
            var call = '/bat_api/rest/calendar-events?_format=json&unit_types=' + drupalSettings.batCalendar[0].unitType + '&unit_ids=' + drupalSettings.batCalendar[0].unitIds + '&event_types=' + drupalSettings.batCalendar[0].eventType + '&background=' + drupalSettings.batCalendar[0].background + '&start=' + dates['start'] + '&end=' + dates['end'];
            return call;
          }

          async function getAndRender() {
            var call = produceCall ();
            const responseBody = (
              await fetch(call)
            ).json();
              const obj = (await responseBody);

              let lastSegment = location.href.replace(/.*\/(\w+)\/?$/, '$1');

              for (let i = 0; i < obj.length; i++) {
                // should be editable basing user permissions
                obj[i]['editable'] = drupalSettings.batCalendar[0].editable;
                // only /availability has links
                // @todo: we should do this via hook_bat_api_events_index_calendar_alter
                if (lastSegment !='availability')  {
                  obj[i]['url'] = '';
                }
              }
              let settings = [
                ['events', obj]
              ];

              build_the_calendar(settings);
          }

          function build_the_calendar(settings) {
            var dates = handyDates();
            const calendarEl = document.getElementById(drupalSettings.batCalendar[0].id)

            var calendarOptions = {
                timeZone: 'UTC',
                initialView: drupalSettings.batCalendar[0].initialView,
                headerToolbar: {
                  left: 'prev,next',
                  center: 'title',
                  right: 'dayGridYear,dayGridWeek,dayGridDay'
                },
                editable: false,
                events: settings[0][1],
                firstDay: drupalSettings.batCalendar[0].firstDay,
                locale: drupalSettings.batCalendar[0].locale,
            };

            const calendar = new FullCalendar.Calendar(calendarEl, calendarOptions);
            calendar.render();
          }
          getAndRender();
      };
    });
    }
  }
}
(Drupal, drupalSettings));
