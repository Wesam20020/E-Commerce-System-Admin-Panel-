(function () {
  'use strict';
  document.addEventListener('click', function (event) {
    const button = event.target && event.target.closest ? event.target.closest('[data-admin-print]') : null;
    if (!button) return;
    event.preventDefault();
    window.print();
  });
})();
