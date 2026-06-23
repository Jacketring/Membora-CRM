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

document.querySelectorAll('[data-global-search-form]').forEach((form) => {
  const input = form.querySelector('[data-global-search-input]');
  const results = form.querySelector('[data-global-search-results]');
  let searchTimer = null;
  let activeController = null;

  if (!input || !results) {
    return;
  }

  function clearResults() {
    results.hidden = true;
    results.innerHTML = '';
  }

  function resultIcon(kind) {
    const icon = document.createElement('span');
    icon.className = `result-icon result-icon--${kind}`;
    icon.textContent = kind.slice(0, 1).toUpperCase();
    return icon;
  }

  function renderResults(items, term) {
    results.innerHTML = '';

    const heading = document.createElement('div');
    heading.className = 'global-search-dropdown-heading';
    heading.textContent = `Resultados para "${term}"`;
    results.appendChild(heading);

    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'global-search-empty';
      empty.textContent = 'No hay resultados con esa busqueda.';
      results.appendChild(empty);
      results.hidden = false;
      return;
    }

    items.forEach((item) => {
      const row = document.createElement(item.href ? 'a' : 'div');
      row.className = 'global-search-result';
      if (item.href) {
        row.href = item.href;
      } else {
        row.setAttribute('aria-disabled', 'true');
      }

      const body = document.createElement('div');
      const title = document.createElement('strong');
      const detail = document.createElement('small');
      const type = document.createElement('em');

      title.textContent = item.title;
      detail.textContent = item.description || item.type;
      type.textContent = item.type;

      body.appendChild(title);
      body.appendChild(detail);
      row.appendChild(resultIcon(item.kind || 'default'));
      row.appendChild(body);
      row.appendChild(type);
      results.appendChild(row);
    });

    results.hidden = false;
  }

  input.addEventListener('input', () => {
    const term = input.value.trim();
    window.clearTimeout(searchTimer);

    if (activeController) {
      activeController.abort();
    }

    if (term.length < 2) {
      clearResults();
      return;
    }

    searchTimer = window.setTimeout(async () => {
      activeController = new AbortController();
      try {
        const response = await fetch(`index.php?route=global-search&q=${encodeURIComponent(term)}`, {
          headers: { Accept: 'application/json' },
          signal: activeController.signal,
        });
        if (!response.ok) {
          clearResults();
          return;
        }

        const payload = await response.json();
        renderResults(payload.items || [], term);
      } catch (error) {
        if (error.name !== 'AbortError') {
          clearResults();
        }
      }
    }, 180);
  });

  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      clearResults();
      input.blur();
    }
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const firstLink = results.querySelector('a.global-search-result');
    if (firstLink) {
      window.location.href = firstLink.href;
    }
  });

  document.addEventListener('click', (event) => {
    if (!form.contains(event.target)) {
      clearResults();
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
