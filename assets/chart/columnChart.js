import ApexCharts from 'apexcharts';

document.addEventListener("DOMContentLoaded", () => {
  // Solo ejecutar el código si existe el elemento #chart-1
  const chartEl = document.querySelector("#chart-1");
  if (!chartEl) return;

  // Resto del código para renderizar el gráfico...
  // Get admin usage data from the global variable or use default values if not available
  const adminUsageData = window.adminWeeklyUsageData || {
    data: [0, 0, 0, 0, 0, 0, 0],
    categories: ['L', 'M', 'MI', 'J', 'V', 'S', 'D']
  };

  // Validate data integrity
  if (!Array.isArray(adminUsageData.data) || adminUsageData.data.length !== 7) {
    console.warn('⚠️ Invalid weekly usage data, using fallback values');
    adminUsageData.data = [0, 0, 0, 0, 0, 0, 0];
  }

  // Ensure all values are numbers
  adminUsageData.data = adminUsageData.data.map(value => Number(value) || 0);

  const colors = ['#FFFFFF'];

  const options = {
    series: [{
      data: adminUsageData.data
    }],
    chart: {
      height: 350,
      type: 'bar',
      className: 'weekly-usage-chart',
      toolbar: {
        show: false
      }
    },
    colors: colors,
    plotOptions: {
      bar: {
        columnWidth: '45%',
        distributed: true,
        borderRadius: 5,
      }
    },
    dataLabels: {
      enabled: false
    },
    legend: {
      show: false
    },
    xaxis: {
      categories: adminUsageData.categories,
      axisBorder: {
        show: true,
        color: '#FFFFFF',
        height: 5,
        width: '100%'
      },
      labels: {
        style: {
          colors: Array(7).fill(colors),
        },
      },
      axisTicks: {
        show: false
      },
    },
    yaxis: {
      show: false
    },
    grid: {
      yaxis: {
        lines: {
          show: false
        }
      }
      ,
      xaxis: {
        lines: {
          show: false
        },
        axisTicks: {
          show: false
        }
      }
    },
    responsive: [
      {
        breakpoint: 1400,
        options: {
          chart: {
            height: 300
          },
          plotOptions: {
            bar: {
              columnWidth: '45%'
            }
          },
          xaxis: {
            labels: {
              style: {
                fontSize: '15px'
              }
            }
          }
        },
        breakpoint: 1200,
        options: {
          chart: {
            height: 300
          },
          plotOptions: {
            bar: {
              columnWidth: '45%'
            }
          },
          xaxis: {
            labels: {
              style: {
                fontSize: '15px'
              }
            }
          }
        },
        breakpoint: 992,
        options: {
          chart: {
            height: 280
          },
          plotOptions: {
            bar: {
              columnWidth: '45%'
            }
          },
          xaxis: {
            labels: {
              style: {
                fontSize: '15px'
              }
            }
          }
        },
        breakpoint: 768,
        options: {
          chart: {
            height: 200
          },
          plotOptions: {
            bar: {
              columnWidth: '45%'
            }
          },
          xaxis: {
            labels: {
              style: {
                fontSize: '15px'
              }
            }
          }
        },
        breakpoint: 576,
        options: {
          chart: {
            height: 200
          },
          plotOptions: {
            bar: {
              columnWidth: '45%'
            }
          },
          xaxis: {
            labels: {
              style: {
                fontSize: '15px'
              }
            }
          }
        },
      }
    ]
  };

  // Resto de tu código de configuración...
  new ApexCharts(chartEl, options).render();
});