import ApexCharts from 'apexcharts';

document.addEventListener("DOMContentLoaded", () => {
  const chartEl = document.querySelector("#chart-2");

  if (!chartEl) {
    console.warn("❌ No se encontró el elemento #chart-2, se omite renderizado de ApexChart.");
    return;
  }

  // Get age distribution data from the global variable or use default values if not available
  const ageData = window.ageDistributionData || {
    categories: ['78-85', '68-75', '58-68', '48-58', '38-48', '28-38', '18-28'],
    men: [0, 0, 0, 0, 0, 0, 0],
    women: [0, 0, 0, 0, 0, 0, 0]
  };

  // Validate data integrity
  if (!Array.isArray(ageData.categories) || !Array.isArray(ageData.men) || !Array.isArray(ageData.women)) {
    console.warn('⚠️ Invalid age distribution data structure, using fallback values');
    ageData.categories = ['78-85', '68-75', '58-68', '48-58', '38-48', '28-38', '18-27'];
    ageData.men = [0, 0, 0, 0, 0, 0, 0];
    ageData.women = [0, 0, 0, 0, 0, 0, 0];
  }

  // Ensure all values are numbers and arrays have same length
  const expectedLength = ageData.categories.length;
  ageData.men = ageData.men.slice(0, expectedLength).map(value => Number(value) || 0);
  ageData.women = ageData.women.slice(0, expectedLength).map(value => Number(value) || 0);

  // Pad arrays if they're too short
  while (ageData.men.length < expectedLength) ageData.men.push(0);
  while (ageData.women.length < expectedLength) ageData.women.push(0);

  // Make women's values negative for the mirror effect
  const womenNegativeValues = ageData.women.map(value => -value);

  const options = {
    series: [
      { name: 'HOMBRES', data: ageData.men },
      { name: 'MUJERES', data: womenNegativeValues }
    ],
    chart: {
      type: 'bar',
      height: 200,
      width: '100%',
      stacked: true,
      className: 'people-information',
      toolbar: {
        show: false
      },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800
      }
    },
    colors: ['#FFFFFF'],
    plotOptions: {
      bar: {
        borderRadius: 5,
        borderRadiusApplication: 'start',
        borderRadiusWhenStacked: 'all',
        horizontal: true,
        barHeight: '70%'
      }
    },
    dataLabels: { enabled: false },
    stroke: { width: 1, colors: ["#FFFFFF"] },
    xaxis: {
      categories: ageData.categories,
      labels: {
        show: false
      },
      axisTicks: {
        show: false 
      },
      axisBorder: {
        show: false 
      }
    },
    yaxis: {
      title: { 
        text: 'EDAD',
        style: {
          color: '#FFFFFF',
        },
        className: 'title-chart',
      },
      labels: {
        style: {
          colors: Array(7).fill('#FFFFFF'),
        }
      }
    },
    tooltip: {
      y: {
        formatter: val => Math.abs(val)
      }
    },
    grid: {
      yaxis: {
        lines: {
          show: false
        }
      }
    },
    legend: {
      show: true,
      position: 'bottom',
      horizontalAlign: 'center',
      labels: {
        colors: ['#FFFFFF', '#FFFFFF'],
        useSeriesColors: false
      }
    },
    breakpoint: 768,
    options: {
      chart: {
        height: 150
      },
      plotOptions: {
        bar: {
          barHeight: '30%'
        }
      },
      yaxis: {
        labels: {
          style: {
            fontSize: '10px'
          }
        }
      }
    }
  };

  const chart = new ApexCharts(document.querySelector("#chart-2"), options);
  chart.render();

  // Global function to update age chart
  window.updateAgeChart = function() {
    const newAgeData = window.ageDistributionData || {
      categories: ['78-85', '68-75', '58-68', '48-58', '38-48', '28-38', '18-27'],
      men: [0, 0, 0, 0, 0, 0, 0],
      women: [0, 0, 0, 0, 0, 0, 0]
    };

    // Validate and prepare data
    if (!Array.isArray(newAgeData.categories) || !Array.isArray(newAgeData.men) || !Array.isArray(newAgeData.women)) {
      console.warn('⚠️ Invalid age distribution data structure for update');
      return;
    }

    const expectedLength = newAgeData.categories.length;
    const menData = newAgeData.men.slice(0, expectedLength).map(value => Number(value) || 0);
    const womenData = newAgeData.women.slice(0, expectedLength).map(value => -Math.abs(Number(value) || 0)); // Negative for women

    // Update chart
    chart.updateOptions({
      series: [
        { name: 'HOMBRES', data: menData },
        { name: 'MUJERES', data: womenData }
      ],
      xaxis: {
        categories: newAgeData.categories
      }
    });
  };
});
