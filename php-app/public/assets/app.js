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
