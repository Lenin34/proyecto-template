import ApexCharts from 'apexcharts';

document.addEventListener("DOMContentLoaded", () => {
  const chartEl = document.querySelector("#chart-3");

  if (!chartEl) {
    console.warn("❌ No se encontró el elemento #chart-3, se omite renderizado de ApexChart.");
    return;
  }

  // Get education distribution data from the global variable or use default values if not available
  const educationData = window.educationDistributionData || {
    categories: ['MAESTRIA', 'POSGRADO', 'UNIVERSIDAD', 'PREPARATORIA', 'SECUNDARIA', 'PRIMARIA', 'PRESCOLAR'],
    men: [0, 0, 0, 0, 0, 0, 0],
    women: [0, 0, 0, 0, 0, 0, 0]
  };

  // Validate data integrity
  if (!Array.isArray(educationData.categories) || !Array.isArray(educationData.men) || !Array.isArray(educationData.women)) {
    console.warn('⚠️ Invalid education distribution data structure, using fallback values');
    educationData.categories = ['MAESTRIA', 'POSGRADO', 'UNIVERSIDAD', 'PREPARATORIA', 'SECUNDARIA', 'PRIMARIA', 'PRESCOLAR'];
    educationData.men = [0, 0, 0, 0, 0, 0, 0];
    educationData.women = [0, 0, 0, 0, 0, 0, 0];
  }

  // Ensure all values are numbers and arrays have same length
  const expectedLength = educationData.categories.length;
  educationData.men = educationData.men.slice(0, expectedLength).map(value => Number(value) || 0);
  educationData.women = educationData.women.slice(0, expectedLength).map(value => Number(value) || 0);

  // Pad arrays if they're too short
  while (educationData.men.length < expectedLength) educationData.men.push(0);
  while (educationData.women.length < expectedLength) educationData.women.push(0);

  // Make women's values negative for the mirror effect
  const womenNegativeValues = educationData.women.map(value => -value);

  const options = {
    series: [
      { name: 'HOMBRES', data: educationData.men },
      { name: 'MUJERES', data: womenNegativeValues }
    ],
    chart: {
      type: 'bar',
      height: 200,
      width: '100%',
      stacked: true.valueOf,
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
      categories: educationData.categories,
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
        text: 'ESCOLARIDAD',
        style: {
          color: '#FFFFFF',
        },
      },
      labels: {
        style: {
          colors: Array(7).fill('#FFFFFF'),
        }
      }
    },
    tooltip: {
      y: {
        formatter: val => Math.abs(val) + "%"
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

  const chart = new ApexCharts(document.querySelector("#chart-3"), options);
  chart.render();

  // Global function to update education chart
  window.updateEducationChart = function() {
    const newEducationData = window.educationDistributionData || {
      categories: ['MAESTRIA', 'POSGRADO', 'UNIVERSIDAD', 'PREPARATORIA', 'SECUNDARIA', 'PRIMARIA', 'PRESCOLAR'],
      men: [0, 0, 0, 0, 0, 0, 0],
      women: [0, 0, 0, 0, 0, 0, 0]
    };

    // Validate and prepare data
    if (!Array.isArray(newEducationData.categories) || !Array.isArray(newEducationData.men) || !Array.isArray(newEducationData.women)) {
      console.warn('⚠️ Invalid education distribution data structure for update');
      return;
    }

    const expectedLength = newEducationData.categories.length;
    const menData = newEducationData.men.slice(0, expectedLength).map(value => Number(value) || 0);
    const womenData = newEducationData.women.slice(0, expectedLength).map(value => -Math.abs(Number(value) || 0)); // Negative for women

    // Update chart
    chart.updateOptions({
      series: [
        { name: 'HOMBRES', data: menData },
        { name: 'MUJERES', data: womenData }
      ],
      xaxis: {
        categories: newEducationData.categories
      }
    });
  };
});
