/* ==========================================================================
   UniWallet — Chart.js setup for the Insights page.

   Reads its figures from the <script id="chart-data" type="application/json">
   block that PHP renders. Nothing here talks to the server.
   ========================================================================== */

(function () {
  'use strict';

  var dataNode = document.getElementById('chart-data');
  if (!dataNode || typeof Chart === 'undefined') return;

  var data;
  try {
    data = JSON.parse(dataNode.textContent);
  } catch (err) {
    return;
  }

  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }
  var dark = document.documentElement.getAttribute('data-resolved') === 'dark';
  var GRID = cssVar('--border', '#e1e7de');
  var TEXT = cssVar('--muted', '#5a6b63');
  var SURFACE = cssVar('--surface', '#ffffff');


  function money(value) {
    // Bangladeshi digit grouping (12,34,567) — the en-IN locale matches it.
    return data.currency + Number(value).toLocaleString('en-IN', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 2
    });
  }

  Chart.defaults.font.family =
    '"Plus Jakarta Sans", "Hind Siliguri", system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = TEXT;

  var moneyAxis = {
    beginAtZero: true,
    grid: { color: GRID, drawBorder: false },
    ticks: {
      callback: function (value) {
        // Keep the axis narrow: 12000 -> "৳12k"
        return value >= 1000
          ? data.currency + (value / 1000).toFixed(value % 1000 ? 1 : 0) + 'k'
          : data.currency + value;
      }
    }
  };

  // ------------------------------------------------------- category split

  var categoryCanvas = document.getElementById('categoryChart');
  if (categoryCanvas && data.catValues.length) {
    new Chart(categoryCanvas, {
      type: 'doughnut',
      data: {
        labels: data.catLabels,
        datasets: [{
          data: data.catValues,
          backgroundColor: data.catColors,
          borderColor: SURFACE,
          borderWidth: 2,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '58%',
        plugins: {
          // The HTML legend below the canvas carries the figures instead.
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var share = total ? Math.round((ctx.parsed / total) * 100) : 0;
                return ' ' + money(ctx.parsed) + ' (' + share + '%)';
              }
            }
          }
        }
      }
    });
  }

  // -------------------------------------------------------- 6-month trend

  var trendCanvas = document.getElementById('trendChart');
  if (trendCanvas) {
    new Chart(trendCanvas, {
      type: 'bar',
      data: {
        labels: data.trendLabels,
        datasets: [{
          label: 'Spent',
          data: data.trendValues,
          backgroundColor: data.accent,
          borderRadius: 6,
          maxBarThickness: 46
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) { return ' ' + money(ctx.parsed.y); }
            }
          }
        },
        scales: {
          x: { grid: { display: false } },
          y: moneyAxis
        }
      }
    });
  }

  // -------------------------------------------------------- spending pace

  var paceCanvas = document.getElementById('paceChart');
  if (paceCanvas) {
    var datasets = [{
      label: 'Running total',
      data: data.cumulative,
      borderColor: data.accent,
      backgroundColor: dark ? 'rgba(63, 199, 150, .14)' : 'rgba(14, 107, 77, .10)',
      fill: true,
      tension: .3,
      pointRadius: 0,
      pointHoverRadius: 4,
      borderWidth: 2
    }];

    // Flat reference line at the monthly cap, when one is set.
    if (data.limit !== null) {
      datasets.push({
        label: 'Budget',
        data: data.dayLabels.map(function () { return data.limit; }),
        borderColor: '#cb3a33',
        borderDash: [6, 5],
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false
      });
    }

    new Chart(paceCanvas, {
      type: 'line',
      data: { labels: data.dayLabels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: data.limit !== null, position: 'bottom' },
          tooltip: {
            callbacks: {
              title: function (items) { return 'Day ' + items[0].label; },
              label: function (ctx) {
                return ' ' + ctx.dataset.label + ': ' + money(ctx.parsed.y);
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            title: { display: true, text: 'Day of month' }
          },
          y: moneyAxis
        }
      }
    });
  }

}());
