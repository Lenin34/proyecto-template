import $ from 'jquery';
import 'select2';

export function initSelect2Multiple(selector = '.select2-multiple') {
  $(document).ready(function () {
    $(selector).select2({
      placeholder: 'Selecciona una o más empresas',
      width: '100%',
      allowClear: true
    });
  });
}
