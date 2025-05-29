(function (Drupal, drupalSettings) {
  Drupal.behaviors.bat_fullcalendar = {
    attach: function (context, drupalSettings) {
      once('div', 'html', context).forEach(function (context) {
        window.onload = function() {
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
            var e = '/bat_api/rest/calendar-events?_format=json&unit_types=' + drupalSettings.batCalendar[0].unitType + '&unit_ids=' + drupalSettings.batCalendar[0].unitIds + '&event_types=' + drupalSettings.batCalendar[0].eventType + '&background=' + drupalSettings.batCalendar[0].background + '&start=' + dates['start'] + '&end=' + dates['end'];
            var r = '/bat_api/rest/calendar-units?_format=json&unit_type=' + drupalSettings.batCalendar[0].unitType + '&unit_ids=' + drupalSettings.batCalendar[0].unitIds ;
            call = new Map();
            call.set("events",e);
            call.set("resources",r);
            return call;
          }

          async function getAndRender() {
            var call = produceCall ();

            const responseBody1 = (
              await fetch(call.get('resources'))
            ).json();
            const resources = (await responseBody1);

            const responseBody2 = (
              await fetch(call.get('events'))
            ).json();

            const events = (await responseBody2);

            let settings = [
              ['events', events],
              ['resources', resources],
            ];

            // More control on event items
            // let lastSegment = location.href.replace(/.*\/(\w+)\/?$/, '$1');
            // for (let i = 0; i < obj.length; i++) {
            //     // should be editable basing user permissions
            //     obj[i]['editable'] = drupalSettings.batCalendar[0].editable;
            //     // only /availability has links
            //     // @todo: we should do this via hook_bat_api_events_index_calendar_alter
            //     if (lastSegment !='availability')  {
            //       obj[i]['url'] = '';
            //     }
            // }

            build_the_calendar(settings);
          }

          function build_the_calendar(settings) {
            var dates = handyDates();
            const calendarEl = document.getElementById(drupalSettings.batCalendar[0].id)
            var events = settings[0][1];
            var resources = settings[1][1];
            var calendarOptions = {
              timeZone: 'UTC',
              initialDate: dates.today,
              initialView: drupalSettings.batCalendar[0].initialView,
              aspectRatio: 1.5,
              headerToolbar: {
              left: 'prev,next',
              center: 'title',
              right: 'resourceTimelineDay,resourceTimelineWeek,resourceTimelineMonth'
              },
              editable: false,
              // initialDate: dates.today,
              // nowIndicator: true,
              resourceAreaHeaderContent: drupalSettings.batCalendar[0].type,
              resources: resources,
              events: events,
            };

            // https://fullcalendar.io/docs/timeline-standard-view-demo
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
