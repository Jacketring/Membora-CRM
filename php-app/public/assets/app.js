const crmSettingsKey = 'membora-crm-settings';
const tenantAccent = document.body?.dataset.tenantAccent || '#004bf2';
const defaultCrmSettings = {
  theme: 'light',
  accent: tenantAccent,
  compact: false,
};

function readCrmSettings() {
  try {
    const settings = { ...defaultCrmSettings, ...JSON.parse(localStorage.getItem(crmSettingsKey) || '{}') };
    return { ...settings, theme: settings.theme === 'dark' ? 'dark' : 'light' };
  } catch {
    return { ...defaultCrmSettings };
  }
}

function darkenHexColor(hex, amount = 34) {
  const clean = String(hex || defaultCrmSettings.accent).replace('#', '');
  if (!/^[0-9a-f]{6}$/i.test(clean)) {
    return '#003fcf';
  }

  const value = Number.parseInt(clean, 16);
  const r = Math.max(0, (value >> 16) - amount);
  const g = Math.max(0, ((value >> 8) & 255) - amount);
  const b = Math.max(0, (value & 255) - amount);
  return `#${[r, g, b].map((part) => part.toString(16).padStart(2, '0')).join('')}`;
}

function applyCrmSettings(settings = readCrmSettings()) {
  const theme = settings.theme === 'dark' ? 'dark' : 'light';

  document.body.dataset.theme = theme;
  document.body.dataset.density = settings.compact ? 'compact' : 'comfortable';
  document.documentElement.style.setProperty('--primary', settings.accent || defaultCrmSettings.accent);
  document.documentElement.style.setProperty('--primary-dark', darkenHexColor(settings.accent));
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', settings.accent || defaultCrmSettings.accent);
}

applyCrmSettings();

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

function markClassCalendarReturn(modal, dateValue) {
  if (!modal) {
    return;
  }

  const returnInput = modal.querySelector('[data-class-return-to-calendar]');
  const monthInput = modal.querySelector('[data-class-calendar-month]');
  if (returnInput) {
    returnInput.value = '1';
  }
  if (monthInput && dateValue) {
    monthInput.value = String(dateValue).slice(0, 7);
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
      if (trigger.dataset.classCalendarTrigger !== undefined) {
        markClassCalendarReturn(modal, trigger.dataset.classDate);
      }
      const currentModal = trigger.closest('dialog');
      if (currentModal && currentModal !== modal && currentModal.open) {
        currentModal.close();
      }
      closeUserMenus(null);
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
      if (targetModal && trigger.dataset.classCalendarTrigger !== undefined) {
        markClassCalendarReturn(targetModal, trigger.dataset.classDate);
      }
      if (currentModal && targetModal && currentModal !== targetModal && currentModal.open) {
        currentModal.close();
      }
      openModalById(trigger.dataset.openModal);
    }
  });
});

function closeUserMenus(exceptMenu) {
  document.querySelectorAll('[data-user-menu]').forEach((menu) => {
    if (menu === exceptMenu) {
      return;
    }

    const dropdown = menu.querySelector('[data-user-menu-dropdown]');
    const trigger = menu.querySelector('[data-user-menu-trigger]');
    if (dropdown) dropdown.hidden = true;
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  });
}

document.querySelectorAll('[data-user-menu]').forEach((menu) => {
  const trigger = menu.querySelector('[data-user-menu-trigger]');
  const dropdown = menu.querySelector('[data-user-menu-dropdown]');

  if (!trigger || !dropdown) {
    return;
  }

  trigger.addEventListener('click', () => {
    const nextHiddenState = !dropdown.hidden;
    closeUserMenus(menu);
    dropdown.hidden = nextHiddenState;
    trigger.setAttribute('aria-expanded', String(!dropdown.hidden));
  });

  trigger.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      closeUserMenus(menu);
      dropdown.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      dropdown.querySelector('button')?.focus();
    }
  });
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('[data-user-menu]')) {
    closeUserMenus(null);
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeUserMenus(null);
  }
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
    markClassCalendarReturn(modal, trigger.dataset.classCreateDate);
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
  const searchInput = select.querySelector('[data-custom-select-search]');
  const emptyState = select.querySelector('[data-custom-select-empty]');

  if (!trigger || !menu || !valueInput || !label) {
    return;
  }

  function applyCustomSelectSearch() {
    const term = normalizeSearchText(searchInput?.value.trim() || '');
    let visibleCount = 0;
    options.forEach((option) => {
      const searchable = option.dataset.search || option.textContent || '';
      const matchesTerm = term === '' || normalizeSearchText(searchable).includes(term);
      const allowed = option.dataset.customAllowed !== 'false';
      option.hidden = !matchesTerm || !allowed;
      if (!option.hidden) {
        visibleCount += 1;
      }
    });
    if (emptyState) {
      emptyState.hidden = visibleCount > 0;
    }
  }

  trigger.addEventListener('click', () => {
    const nextHiddenState = !menu.hidden;
    closeCustomSelects(select);
    menu.hidden = nextHiddenState;
    trigger.setAttribute('aria-expanded', String(!menu.hidden));
    if (!menu.hidden && searchInput) {
      searchInput.value = '';
      applyCustomSelectSearch();
      searchInput.focus();
    }
  });

  searchInput?.addEventListener('input', applyCustomSelectSearch);
  searchInput?.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      Array.from(options).find((option) => !option.hidden)?.focus();
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.focus();
    }
  });

  trigger.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      closeCustomSelects(select);
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      const selected = select.querySelector('[data-custom-select-option].selected');
      const nextFocus = (selected && !selected.hidden) ? selected : Array.from(options).find((option) => !option.hidden);
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
      const visibleOptions = Array.from(options).filter((item) => !item.hidden);
      const currentIndex = visibleOptions.indexOf(option);
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        visibleOptions[Math.min(currentIndex + 1, visibleOptions.length - 1)]?.focus();
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        visibleOptions[Math.max(currentIndex - 1, 0)]?.focus();
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

function setCustomSelectValue(select, value, fallbackLabel) {
  const valueInput = select?.querySelector('[data-custom-select-value]');
  const label = select?.querySelector('[data-custom-select-label]');
  const options = select?.querySelectorAll('[data-custom-select-option]');
  const option = Array.from(options || []).find((item) => (item.dataset.value || '') === value);

  if (!valueInput || !label || !options) {
    return;
  }

  valueInput.value = option?.dataset.value || '';
  label.textContent = option?.textContent.trim() || fallbackLabel || '';
  options.forEach((item) => item.classList.toggle('selected', item === option));
}

document.querySelectorAll('[data-payment-form]').forEach((form) => {
  const memberInput = form.querySelector('[name="member_id"]');
  const subscriptionSelect = form.querySelector('[data-payment-subscription-select]');
  const subscriptionInput = subscriptionSelect?.querySelector('[name="subscription_id"]');
  const subscriptionOptions = subscriptionSelect?.querySelectorAll('[data-custom-select-option]');

  if (!memberInput || !subscriptionSelect || !subscriptionInput || !subscriptionOptions) {
    return;
  }

  function syncSubscriptionOptions() {
    const memberId = memberInput.value;
    let selectedStillAllowed = subscriptionInput.value === '';

    subscriptionOptions.forEach((option) => {
      const optionMemberId = option.dataset.memberId || '';
      const allowed = optionMemberId === '' || memberId === '' || optionMemberId === memberId;
      option.dataset.customAllowed = allowed ? 'true' : 'false';
      if (allowed && option.dataset.value === subscriptionInput.value) {
        selectedStillAllowed = true;
      }
    });

    if (!selectedStillAllowed) {
      setCustomSelectValue(subscriptionSelect, '', 'Sin membresia asociada');
    }
  }

  memberInput.addEventListener('custom-select-change', syncSubscriptionOptions);
  syncSubscriptionOptions();
});

document.querySelectorAll('[data-checkin-form]').forEach((form) => {
  const memberInput = form.querySelector('[name="member_id"]');
  const reservationSelect = form.querySelector('[data-checkin-reservation-select]');
  const reservationInput = reservationSelect?.querySelector('[name="reservation_id"]');
  const reservationOptions = reservationSelect?.querySelectorAll('[data-custom-select-option]');

  if (!memberInput || !reservationSelect || !reservationInput || !reservationOptions) {
    return;
  }

  function syncReservationOptions() {
    const memberId = memberInput.value;
    let selectedStillAllowed = reservationInput.value === '';

    reservationOptions.forEach((option) => {
      const optionMemberId = option.dataset.memberId || '';
      const allowed = optionMemberId === '' || memberId === '' || optionMemberId === memberId;
      option.dataset.customAllowed = allowed ? 'true' : 'false';
      if (allowed && option.dataset.value === reservationInput.value) {
        selectedStillAllowed = true;
      }
    });

    if (!selectedStillAllowed) {
      setCustomSelectValue(reservationSelect, '', 'Entrada general sin reserva');
    }
  }

  memberInput.addEventListener('custom-select-change', syncReservationOptions);
  syncReservationOptions();
});

document.querySelectorAll('[data-empresa-form]').forEach((form) => {
  const planSelect = form.querySelector('[data-plan-price-select]');
  const priceInput = form.querySelector('[data-plan-price-input]');
  const nextPaymentInput = form.querySelector('[data-next-payment-input]');
  const nextPaymentField = form.querySelector('[data-next-payment-field]');
  const accessUntilInput = form.querySelector('[data-access-until-input]');
  const trialPlanField = form.querySelector('[data-trial-plan-field]');
  const crmStatusSelect = form.querySelector('select[name="status"]');
  const paymentStatusSelect = form.querySelector('select[name="payment_status"]');
  const renewalPeriodSelect = form.querySelector('select[name="renewal_period"]');

  if (!planSelect || !priceInput) {
    return;
  }

  let prices = {};
  try {
    prices = JSON.parse(form.dataset.planPrices || '{}');
  } catch {
    prices = {};
  }

  const priceForSelectedPlan = () => {
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    return selectedOption?.dataset.monthlyPrice || prices[planSelect.value] || '';
  };

  const applyPlanPrice = () => {
    const planPrice = priceForSelectedPlan();
    if (planPrice !== '') {
      priceInput.value = Number.parseFloat(planPrice).toFixed(2);
    }
  };

  const syncTrialPlanFields = () => {
    const isTrialPlan = planSelect.value === 'TRIAL';
    if (nextPaymentField) {
      nextPaymentField.hidden = isTrialPlan;
    }
    if (trialPlanField) {
      trialPlanField.hidden = !isTrialPlan;
    }
    if (isTrialPlan && nextPaymentInput) {
      nextPaymentInput.value = '';
      nextPaymentInput.dataset.autoNextPayment = 'false';
    }
    if (isTrialPlan && accessUntilInput) {
      accessUntilInput.value = '';
    }
    if (isTrialPlan) {
      if (crmStatusSelect) crmStatusSelect.value = 'TRIAL';
      if (paymentStatusSelect) paymentStatusSelect.value = 'TRIAL';
    }
  };

  const nextMonthPaymentDate = () => {
    const now = new Date();
    if (renewalPeriodSelect?.value === 'ANNUAL') {
      return formatDateInputValue(new Date(now.getFullYear() + 1, now.getMonth(), now.getDate()));
    }

    const lastDayOfNextMonth = new Date(now.getFullYear(), now.getMonth() + 2, 0).getDate();
    const nextPayment = new Date(
      now.getFullYear(),
      now.getMonth() + 1,
      Math.min(now.getDate(), lastDayOfNextMonth)
    );

    return formatDateInputValue(nextPayment);
  };

  const shouldAutoFillNextPayment = () => nextPaymentInput
    && planSelect.value
    && planSelect.value !== 'TRIAL'
    && (nextPaymentInput.value.trim() === '' || nextPaymentInput.dataset.autoNextPayment === 'true');

  const applyNextPaymentDate = () => {
    if (!shouldAutoFillNextPayment()) {
      if (accessUntilInput && nextPaymentInput) {
        accessUntilInput.value = nextPaymentInput.value;
      }
      return;
    }

    nextPaymentInput.value = nextMonthPaymentDate();
    nextPaymentInput.dataset.autoNextPayment = 'true';
    if (accessUntilInput) {
      accessUntilInput.value = nextPaymentInput.value;
    }
  };

  if (priceInput.value.trim() === '' || Number.parseFloat(priceInput.value.replace(',', '.')) === 0) {
    applyPlanPrice();
  }

  syncTrialPlanFields();

  if (planSelect.value !== 'TRIAL' && nextPaymentInput && nextPaymentInput.value.trim() === '') {
    applyNextPaymentDate();
  } else if (accessUntilInput && nextPaymentInput) {
    accessUntilInput.value = nextPaymentInput.value;
  }

  nextPaymentInput?.addEventListener('input', () => {
    nextPaymentInput.dataset.autoNextPayment = 'false';
    if (accessUntilInput) {
      accessUntilInput.value = nextPaymentInput.value;
    }
  });

  planSelect.addEventListener('change', () => {
    applyPlanPrice();
    syncTrialPlanFields();
    applyNextPaymentDate();
  });

  renewalPeriodSelect?.addEventListener('change', () => {
    if (nextPaymentInput) {
      nextPaymentInput.dataset.autoNextPayment = 'true';
    }
    applyNextPaymentDate();
  });

  crmStatusSelect?.addEventListener('change', () => {
    syncTrialPlanFields();
    applyNextPaymentDate();
  });
});

const demoCountdown = document.querySelector('[data-demo-countdown]');
const demoExpiresIn = Number.parseInt(document.body.dataset.demoExpiresIn || '0', 10);
if (demoCountdown && demoExpiresIn > 0) {
  let remainingSeconds = demoExpiresIn;
  const renderDemoCountdown = () => {
    const minutes = String(Math.floor(remainingSeconds / 60)).padStart(2, '0');
    const seconds = String(remainingSeconds % 60).padStart(2, '0');
    demoCountdown.textContent = `${minutes}:${seconds}`;
  };

  renderDemoCountdown();
  const demoTimer = window.setInterval(() => {
    remainingSeconds -= 1;
    if (remainingSeconds <= 0) {
      window.clearInterval(demoTimer);
      window.location.href = 'index.php?route=demo-expired';
      return;
    }

    renderDemoCountdown();
  }, 1000);
}

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
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      applyLiveSearch();
    }
  });
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

document.querySelectorAll('[data-crm-settings-form]').forEach((form) => {
  const themeInputs = form.querySelectorAll('[data-setting-theme]');
  const accentInput = form.querySelector('[data-setting-accent]');
  const compactInput = form.querySelector('[data-setting-compact]');
  const resetButton = form.querySelector('[data-settings-reset]');

  function syncSettingsForm(settings = readCrmSettings()) {
    themeInputs.forEach((input) => {
      input.checked = input.value === settings.theme;
    });
    if (accentInput) {
      accentInput.value = settings.accent || defaultCrmSettings.accent;
    }
    if (compactInput) {
      compactInput.checked = Boolean(settings.compact);
    }
  }

  function settingsFromForm() {
    const selectedTheme = Array.from(themeInputs).find((input) => input.checked)?.value || defaultCrmSettings.theme;
    return {
      theme: selectedTheme,
      accent: accentInput?.value || defaultCrmSettings.accent,
      compact: Boolean(compactInput?.checked),
    };
  }

  syncSettingsForm();

  [...themeInputs, accentInput, compactInput].filter(Boolean).forEach((input) => {
    input.addEventListener('input', () => {
      applyCrmSettings(settingsFromForm());
    });
    input.addEventListener('change', () => {
      applyCrmSettings(settingsFromForm());
    });
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const nextSettings = settingsFromForm();
    localStorage.setItem(crmSettingsKey, JSON.stringify(nextSettings));
    applyCrmSettings(nextSettings);
    form.closest('dialog')?.close();
  });

  resetButton?.addEventListener('click', () => {
    localStorage.removeItem(crmSettingsKey);
    syncSettingsForm(defaultCrmSettings);
    applyCrmSettings(defaultCrmSettings);
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

document.querySelectorAll('[data-copy-target]').forEach((button) => {
  button.addEventListener('click', async () => {
    const source = document.querySelector(`[data-copy-source="${button.dataset.copyTarget}"]`);
    const text = source?.value ?? source?.textContent ?? '';
    if (!text.trim()) {
      return;
    }

    try {
      await navigator.clipboard.writeText(text);
      const originalText = button.textContent;
      button.textContent = 'Copiado';
      window.setTimeout(() => {
        button.textContent = originalText;
      }, 1400);
    } catch {
      if (source instanceof HTMLInputElement || source instanceof HTMLTextAreaElement) {
        source.select();
        document.execCommand('copy');
      } else {
        const fallback = document.createElement('textarea');
        fallback.value = text;
        fallback.setAttribute('readonly', '');
        fallback.style.position = 'fixed';
        fallback.style.opacity = '0';
        document.body.appendChild(fallback);
        fallback.select();
        document.execCommand('copy');
        fallback.remove();
      }
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
    if (acceptButton) {
      acceptButton.textContent = form.dataset.confirmActionLabel || 'Eliminar';
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
