import './styles/app.css';
import './styles/navbar.css';
import './styles/tables.css';
import './styles/modals.css';
import './styles/datatables-custom.css';
import './styles/modern-tables.css';
import './styles/button-system.css';
import './styles/card-system.css';

import './chart/columnChart.js';
import './chart/barChartAge.js';
import './chart/barChartSchooling.js';

import './calendar/fullcalendar.js';

import $ from 'jquery';
window.$ = $;
window.jQuery = $;

import 'select2/dist/css/select2.min.css';

import { initSelect2Multiple } from './select/initSelect2.js';

import 'bootstrap';

import 'bootstrap/dist/css/bootstrap.min.css';

import 'bootstrap/dist/js/bootstrap.bundle.min.js';


document.addEventListener('DOMContentLoaded', () => {
    initSelect2Multiple();
});