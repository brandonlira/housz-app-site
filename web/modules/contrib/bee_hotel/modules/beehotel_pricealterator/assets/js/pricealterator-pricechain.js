(function (Drupal, drupalSettings) {
  Drupal.behaviors.beehotel_pricealterator = {
    attach: function (context, drupalSettings) {
      once('div', 'html', context).forEach(function (context) {

      //console.log (drupalSettings);

      window.onload = function(){
        google.charts.load("current", {packages:["corechart"]});
        google.charts.setOnLoadCallback(drawChartPie);
      };

      function drawChartPie() {
        var rows = [];
        rows.push(['Altertor', 'Price']);
        for (var k = 1; k < drupalSettings.beehotel_pricealterator.alterators.length; k++) {
          var diff = (drupalSettings.beehotel_pricealterator.alterators[k].price - drupalSettings.beehotel_pricealterator.alterators[k - 1].price);
          if (diff > 0) {
            var string = parseFloat(diff).toFixed(2).toString(0);
            var label = drupalSettings.beehotel_pricealterator.alterators[k].id.substring(0, 24) + " (" + string + ")";
            //In pies, price is the difference.
            var int = parseInt(drupalSettings.beehotel_pricealterator.alterators[k].price, 10);
            var tmp = [label, diff];
            rows.push(tmp);
          }
        }
        var data = google.visualization.arrayToDataTable(rows);
        var options = {
          title: 'Price Alteration',
          is3D: true,
        };
        var chart = new google.visualization.PieChart(document.getElementById('chain-chart'));
        chart.draw(data, options);
      }

      function drawChartComboChart() {

        // Some raw data (not necessarily accurate)
        var data = google.visualization.arrayToDataTable([
          ['Alteratore', 'Prezzo', ],
          ['Tabella',  90],
          ['Occupanti',  100],
          ['Sunday',  110],
          ['Gorni dal checkin',  125],
          ['Durata del soggiorno',  120],
          ['Global',  122],
        ]);

        var options = {
          title : 'Price alteration based on current setting',
          vAxis: {title: 'Price'},
          hAxis: {title: 'Price alterators'},
          seriesType: 'bars',
          series: {5: {type: 'line'}}
        };

        var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
        chart.draw(data, options);
      }
    });
    }
  }
}
(Drupal, drupalSettings));
