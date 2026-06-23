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

function closePhonePickers(exceptPicker) {
  document.querySelectorAll('[data-phone-picker]').forEach((picker) => {
    if (picker !== exceptPicker) {
      const menu = picker.querySelector('[data-phone-country-menu]');
      if (menu) menu.hidden = true;
    }
  });
}

document.querySelectorAll('[data-phone-picker]').forEach((picker) => {
  const trigger = picker.querySelector('[data-phone-country-trigger]');
  const menu = picker.querySelector('[data-phone-country-menu]');
  const hiddenInput = picker.querySelector('[data-phone-country-value]');
  const flag = picker.querySelector('[data-phone-country-flag]');
  const codeLabel = picker.querySelector('[data-phone-country-code]');
  const searchInput = picker.querySelector('[data-phone-country-search]');
  const options = picker.querySelectorAll('[data-phone-country-option]');

  if (!trigger || !menu || !hiddenInput || !flag || !codeLabel) {
    return;
  }

  trigger.addEventListener('click', () => {
    const nextHiddenState = !menu.hidden;
    closePhonePickers(picker);
    menu.hidden = nextHiddenState;
    if (!menu.hidden && searchInput) {
      searchInput.value = '';
      options.forEach((option) => { option.hidden = false; });
      searchInput.focus();
    }
  });

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const term = searchInput.value.trim().toLowerCase();
      options.forEach((option) => {
        option.hidden = term !== '' && !option.dataset.search.includes(term);
      });
    });
  }

  options.forEach((option) => {
    option.addEventListener('click', () => {
      hiddenInput.value = option.dataset.code;
      codeLabel.textContent = option.dataset.code;
      flag.src = `https://flagcdn.com/w40/${option.dataset.iso}.png`;
      menu.hidden = true;
    });
  });
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('[data-phone-picker]')) {
    closePhonePickers(null);
  }
});

document.querySelectorAll('[data-prevent-double-submit]').forEach((form) => {
  form.addEventListener('submit', () => {
    const submitter = form.querySelector('button[type="submit"], button:not([type])');
    if (submitter) {
      submitter.disabled = true;
      submitter.dataset.originalText = submitter.textContent;
      submitter.textContent = 'Guardando...';
    }
  });
});

document.querySelectorAll('[data-member-picker]').forEach((picker) => {
  const search = picker.querySelector('[data-member-search]');
  const options = picker.querySelectorAll('[data-member-option]');
  const empty = picker.querySelector('[data-member-empty]');

  if (!search) {
    return;
  }

  search.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    let visibleCount = 0;

    options.forEach((option) => {
      const matches = term === '' || option.dataset.search.includes(term);
      option.hidden = !matches;
      if (matches) visibleCount += 1;
    });

    if (empty) {
      empty.hidden = visibleCount !== 0;
    }
  });
});

const confirmDialog = document.getElementById('confirm-dialog');
let pendingConfirmForm = null;

if (confirmDialog) {
  const confirmText = confirmDialog.querySelector('[data-confirm-text]');
  const cancelButton = confirmDialog.querySelector('[data-confirm-cancel]');
  const acceptButton = confirmDialog.querySelector('[data-confirm-accept]');

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-confirm-message]');
    if (!form || form.dataset.confirmed === 'true') {
      return;
    }

    event.preventDefault();
    pendingConfirmForm = form;
    if (confirmText) {
      confirmText.textContent = form.dataset.confirmMessage || 'Esta accion no se puede deshacer.';
    }
    confirmDialog.showModal();
  });

  if (cancelButton) {
    cancelButton.addEventListener('click', () => {
      pendingConfirmForm = null;
      confirmDialog.close();
    });
  }

  if (acceptButton) {
    acceptButton.addEventListener('click', () => {
      if (!pendingConfirmForm) {
        confirmDialog.close();
        return;
      }

      pendingConfirmForm.dataset.confirmed = 'true';
      pendingConfirmForm.requestSubmit();
    });
  }

  confirmDialog.addEventListener('cancel', () => {
    pendingConfirmForm = null;
  });
}
