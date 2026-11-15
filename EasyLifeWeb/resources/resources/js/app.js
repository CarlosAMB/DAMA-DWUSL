import './bootstrap';
import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
    // Menu lateral en pantallas pequenas
    document.querySelectorAll('[data-toggle-sidebar]').forEach((btn) =>
        btn.addEventListener('click', () => document.querySelector('.sidebar')?.classList.toggle('show'))
    );

    // Confirmacion antes de acciones destructivas
    document.querySelectorAll('form[data-confirm]').forEach((form) =>
        form.addEventListener('submit', (e) => {
            if (!confirm(form.dataset.confirm)) e.preventDefault();
        })
    );
});
