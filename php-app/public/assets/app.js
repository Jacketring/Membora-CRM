function shouldIgnoreModalOpen(event) {
  return Boolean(event.target.closest('button, a, input, select, textarea, form, label'));
}

function openModalById(id) {
  const modal = document.getElementById(id);
  if (modal) modal.showModal();
}

document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
  trigger.addEventListener('click', (event) => {
    if (trigger.classList.contains('clickable-row') && shouldIgnoreModalOpen(event)) {
      return;
    }

    const modal = document.getElementById(trigger.dataset.openModal);
    if (modal) modal.showModal();
  });

  trigger.addEventListener('keydown', (event) => {
    if (!trigger.classList.contains('clickable-row')) {
      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      openModalById(trigger.dataset.openModal);
    }
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    const modal = button.closest('dialog');
    if (modal) modal.close();
  });
});
