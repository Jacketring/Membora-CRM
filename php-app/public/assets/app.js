document.querySelectorAll('[data-open-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    const modal = document.getElementById(button.dataset.openModal);
    if (modal) modal.showModal();
  });
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
  button.addEventListener('click', () => {
    const modal = button.closest('dialog');
    if (modal) modal.close();
  });
});
