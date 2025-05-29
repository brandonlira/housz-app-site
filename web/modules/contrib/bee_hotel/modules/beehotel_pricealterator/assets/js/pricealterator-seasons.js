(function (Drupal, drupalSettings) {
  Drupal.behaviors.beehotel_pricealterator = {
    attach: function (context, drupalSettings) {
    once('beehotel_pricealterator', 'html', context).forEach(function (context) {
      window.onload = function(){
        var container = document.getElementById('getseason-chart');
        var seasons = [];
        var l = drupalSettings.beehotel_pricealterator.seasons;
        for (let i = 0; i < l.length; i++) {
          seasons.push({
            c: [
              {v: l[i][0]},
              {v: l[i][0]},
              {v: 'Date(' + l[i]['from']['Y'] + ', ' + l[i]['from']['m'] + ', ' + l[i]['from']['d'] + ')'},
              {v: 'Date(' + l[i]['to']['Y'] + ', ' + l[i]['to']['m'] + ', ' + l[i]['to']['d'] + ')'},
            ]
            });
        }
        google.charts.load('current', {'packages':['timeline']});
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
          var data = new google.visualization.DataTable({
            cols: [
              // for grouping
              {id: 'season', label: 'Season', type: 'string'},
              // label on color
              {id: 'season', label: 'Season', type: 'string'},
              {id: 'start', label: 'Start', type: 'date'},
              {id: 'end', label: 'End', type: 'date'},
            ],
            rows: seasons,
          });
          var options = {
            legend: 'none',
            timeline: {
              groupByRowLabel: true,
              showRowLabels: false
            },
            tooltip: {isHtml: true},
          };
          var chart = new google.visualization.Timeline(document.getElementById('getseason-chart'));
          chart.draw(data, options);
        }

      };

      });
    }
  }
} (Drupal, drupalSettings));
