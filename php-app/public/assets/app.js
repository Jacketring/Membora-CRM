function shouldIgnoreModalOpen(event) {
  return Boolean(event.target.closest('button, a, input, select, textarea, form, label'));
}

let lastModalTrigger = null;

function openModalById(id) {
  const modal = document.getElementById(id);
  if (!modal) {
    return;
  }

  modal.showModal();
  const focusTarget = modal.querySelector('[data-close-modal], input:not([type="hidden"]), select, textarea, button');
  if (focusTarget) {
    focusTarget.focus();
  }
}

function clearModalUrlParam(modal) {
  const params = new URLSearchParams(window.location.search);
  if (params.get('modal') !== modal.id) {
    return;
  }

  params.delete('modal');
  const nextQuery = params.toString();
  const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${window.location.hash}`;
  window.history.replaceState({}, '', nextUrl);
}

const modalToOpen = new URLSearchParams(window.location.search).get('modal');
if (modalToOpen) {
  openModalById(modalToOpen);
}

document.querySelectorAll('dialog').forEach((modal) => {
  modal.addEventListener('close', () => {
    clearModalUrlParam(modal);
    if (lastModalTrigger && document.contains(lastModalTrigger)) {
      lastModalTrigger.focus();
    }
  });
});

document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
  trigger.addEventListener('click', (event) => {
    if (trigger.classList.contains('clickable-row') && shouldIgnoreModalOpen(event)) {
      return;
    }

    const modal = document.getElementById(trigger.dataset.openModal);
    if (modal) {
      lastModalTrigger = trigger;
      const currentModal = trigger.closest('dialog');
      if (currentModal && currentModal !== modal && currentModal.open) {
        currentModal.close();
      }
      openModalById(trigger.dataset.openModal);
    }
  });

  trigger.addEventListener('keydown', (event) => {
    if (!trigger.classList.contains('clickable-row')) {
      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      lastModalTrigger = trigger;
      const currentModal = trigger.closest('dialog');
      const targetModal = document.getElementById(trigger.dataset.openModal);
      if (currentModal && targetModal && currentModal !== targetModal && currentModal.open) {
        currentModal.close();
      }
      openModalById(trigger.dataset.openModal);
    }
  });
});

document.querySelectorAll('[data-class-create-date]').forEach((trigger) => {
  trigger.addEventListener('click', (event) => {
    if (event.target.closest('[data-open-modal], .calendar-event')) {
      return;
    }

    const modal = document.getElementById('class-session-modal');
    const dateInput = modal?.querySelector('input[name="class_date"]');
    if (!modal || !dateInput) {
      return;
    }

    event.stopPropagation();
    dateInput.value = trigger.dataset.classCreateDate || dateInput.value;
    lastModalTrigger = trigger;
    const currentModal = trigger.closest('dialog');
    if (currentModal && currentModal.open) {
      currentModal.close();
    }
    openModalById('class-session-modal');
  });

  trigger.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    event.preventDefault();
    trigger.click();
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
      const trigger = picker.querySelector('[data-phone-country-trigger]');
      if (menu) menu.hidden = true;
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
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
    trigger.setAttribute('aria-expanded', String(!menu.hidden));
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
      trigger.setAttribute('aria-expanded', 'false');
    });
  });
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('[data-phone-picker]')) {
    closePhonePickers(null);
  }
});

const autoFilterTimers = new WeakMap();

function submitAutoFilterForm(form, delay = 0) {
  if (!form) {
    return;
  }

  window.clearTimeout(autoFilterTimers.get(form));
  const timer = window.setTimeout(() => {
    form.requestSubmit();
  }, delay);
  autoFilterTimers.set(form, timer);
}

function closeCustomSelects(exceptSelect) {
  document.querySelectorAll('[data-custom-select]').forEach((select) => {
    if (select !== exceptSelect) {
      const menu = select.querySelector('[data-custom-select-menu]');
      const trigger = select.querySelector('[data-custom-select-trigger]');
      if (menu) menu.hidden = true;
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    }
  });
}

document.querySelectorAll('[data-custom-select]').forEach((select) => {
  const trigger = select.querySelector('[data-custom-select-trigger]');
  const menu = select.querySelector('[data-custom-select-menu]');
  const valueInput = select.querySelector('[data-custom-select-value]');
  const label = select.querySelector('[data-custom-select-label]');
  const options = select.querySelectorAll('[data-custom-select-option]');

  if (!trigger || !menu || !valueInput || !label) {
    return;
  }

  trigger.addEventListener('click', () => {
    const nextHiddenState = !menu.hidden;
    closeCustomSelects(select);
    menu.hidden = nextHiddenState;
    trigger.setAttribute('aria-expanded', String(!menu.hidden));
  });

  trigger.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      closeCustomSelects(select);
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      const selected = select.querySelector('[data-custom-select-option].selected');
      const nextFocus = selected || options[0];
      if (nextFocus) {
        nextFocus.focus();
      }
    }
  });

  options.forEach((option) => {
    option.addEventListener('click', () => {
      valueInput.value = option.dataset.value || '';
      label.textContent = option.textContent.trim();
      options.forEach((item) => item.classList.toggle('selected', item === option));
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      valueInput.dispatchEvent(new CustomEvent('custom-select-change', {
        bubbles: true,
        detail: { option },
      }));
      trigger.focus();
      submitAutoFilterForm(select.closest('[data-auto-filter-form]'), 0);
    });

    option.addEventListener('keydown', (event) => {
      const currentIndex = Array.from(options).indexOf(option);
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        options[Math.min(currentIndex + 1, options.length - 1)]?.focus();
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        options[Math.max(currentIndex - 1, 0)]?.focus();
      }
      if (event.key === 'Escape') {
        event.preventDefault();
        menu.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
      }
    });
  });
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('[data-custom-select]')) {
    closeCustomSelects(null);
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeCustomSelects(null);
  }
});

function formatDateInputValue(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function updateMembershipEndDate(container, durationDays) {
  const startInput = container.querySelector('[data-membership-start-date]');
  const endInput = container.querySelector('[data-membership-end-date]');

  if (!startInput || !endInput || !durationDays || !startInput.value) {
    return;
  }

  const startDate = new Date(`${startInput.value}T00:00:00`);
  if (Number.isNaN(startDate.getTime())) {
    return;
  }

  startDate.setDate(startDate.getDate() + Number(durationDays));
  endInput.value = formatDateInputValue(startDate);
}

function selectedMembershipDuration(container) {
  const selectedOption = container.querySelector('input[name="membership_plan_id"]')
    ?.closest('[data-custom-select]')
    ?.querySelector('[data-custom-select-option].selected');

  return Number(selectedOption?.dataset.durationDays || 0);
}

document.querySelectorAll('input[name="membership_plan_id"]').forEach((input) => {
  const form = input.closest('form');
  if (!form) return;

  input.addEventListener('custom-select-change', (event) => {
    const durationDays = Number(event.detail?.option?.dataset.durationDays || 0);
    updateMembershipEndDate(form, durationDays);
  });

  form.querySelector('[data-membership-start-date]')?.addEventListener('change', () => {
    updateMembershipEndDate(form, selectedMembershipDuration(form));
  });
});

document.querySelectorAll('[data-auto-filter-form]').forEach((form) => {
  form.querySelectorAll('[data-auto-filter-input]').forEach((input) => {
    if (input.name === 'q') {
      return;
    }

    input.addEventListener('input', () => {
      submitAutoFilterForm(form, input.type === 'date' ? 0 : 420);
    });

    input.addEventListener('change', () => {
      submitAutoFilterForm(form, 0);
    });
  });
});

function normalizeSearchText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

document.querySelectorAll('[data-live-search-form]').forEach((form) => {
  const input = form.querySelector('input[name="q"]');
  const tableId = form.dataset.liveSearchTarget;
  const table = tableId ? document.getElementById(tableId) : null;
  const counter = document.querySelector('[data-live-search-count]');

  if (!input || !table) {
    return;
  }

  const rows = Array.from(table.querySelectorAll('[data-live-search-row]'));
  const emptyRow = table.querySelector('[data-live-search-empty]');

  function applyLiveSearch() {
    const term = normalizeSearchText(input.value.trim());
    let visibleCount = 0;

    rows.forEach((row) => {
      const matches = term === '' || normalizeSearchText(row.textContent).includes(term);
      row.hidden = !matches;
      if (matches) {
        visibleCount += 1;
      }
    });

    if (emptyRow) {
      emptyRow.hidden = visibleCount !== 0;
    }

    if (counter) {
      counter.textContent = `${visibleCount} ${visibleCount === 1 ? 'resultado' : 'resultados'}`;
    }
  }

  input.addEventListener('input', applyLiveSearch);
  applyLiveSearch();
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
