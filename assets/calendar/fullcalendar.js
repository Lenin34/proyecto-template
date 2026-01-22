import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import allLocales from '@fullcalendar/core/locales-all';
/*console.log('🧩 Archivo JS cargado');*/

document.addEventListener('DOMContentLoaded', function () {
  const calendarEl = document.getElementById('calendar');

  if (calendarEl) {
    const calendar = new Calendar(calendarEl, {
      plugins: [dayGridPlugin, interactionPlugin],
      initialView: 'dayGridMonth',
      locale: 'es',
      locales: allLocales,
      headerToolbar: {
        left: 'prev',
        center: 'title',
        right: 'next'
      },
      events: window.calendarEvents,
      displayEventTime: false, /**/
      eventDisplay: 'block', /**/        
      dayMaxEvents: true,
      eventLimitClick: 'custom',
      eventClick: function(info) {
        // Prevenir el comportamiento por defecto de FullCalendar
        info.jsEvent.preventDefault();
/*
        console.clear();
        console.log('✅ Se hizo click en un evento', info.event);

        console.clear(); // Para limpiar la consola al cada clic y depurar limpio
*/

        // Extraer ID
        const originalId = String(info.event.id).split('-')[0];
        let dominio = window.dominio ?? null;

/*        console.log('👁️ window.dominio:', window.dominio);
        console.log('👁️ URL actual:', window.location.href);*/

        if (!dominio) {
          const match = window.location.pathname.match(/^\/([^/]+)\//);
          if (match) {
            dominio = match[1];
            /*console.log('🔍 Dominio extraído de la URL:', dominio);*/
          } else {
            /*console.warn('⚠️ No se pudo extraer el dominio de la URL.');*/
          }
        } else {
          /*console.log('✅ Dominio tomado de window.dominio:', dominio);*/
        }

        const urlFinal = dominio ? `/${dominio}/event/${originalId}` : `/event/${originalId}`;

        /*console.log('➡️ URL final a redirigir:', urlFinal);*/

        if (dominio) {
          /*console.log('⛳ Redirigiendo ahora...');*/
          window.location.href = urlFinal;
        } else {
          alert('No se pudo determinar el tenant (dominio) para la URL del evento.');
        }
      },
      dayCellDidMount(info) {
        const dateStr = info.date.toISOString().split('T')[0];
        const todayStr = new Date().toISOString().split('T')[0];
      
        const hasEvent = Array.isArray(window.calendarEvents)
          && window.calendarEvents.some(event => event.start === dateStr);
      
        if (hasEvent) {
          const numberEl = info.el.querySelector('.fc-daygrid-day-number');
          if (numberEl) {
            numberEl.classList.add('fc-highlighted-day');
      
            if (dateStr === todayStr) {
              numberEl.classList.add('fc-today-highlight');
            }
          }
        }  
      }
    });
    /*console.log('✅ Calendar initialized');*/
    calendar.render();
  }
});
