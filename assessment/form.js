'use strict';
const form = document.getElementById('assessment-form');
if (form) {
  const count = document.getElementById('answered-count');
  const update = () => { count.textContent = new Set([...form.querySelectorAll('input[type=radio]:checked')].map(el => el.name)).size; };
  form.addEventListener('change', update); update();
  form.addEventListener('submit', () => {
    const button = document.getElementById('assessment-button');
    button.disabled = true;
    button.textContent = 'Sending your assessment…';
    document.getElementById('sending-status').hidden = false;
  });
}
const summary = document.getElementById('error-summary');
if (summary) summary.focus();
