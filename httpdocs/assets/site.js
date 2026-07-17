const MEMBORA_WEBHOOK_URL = window.MEMBORA_WEBHOOK_URL || 'api/lead.php';
const MEMBORA_DEMO_LOGIN_URL = '/app/index.php?route=login';
const MEMBORA_PUBLIC_PLANS_URL = window.MEMBORA_PUBLIC_PLANS_URL || 'api/plans.php';
const MEMBORA_REMOTE_PUBLIC_PLANS_URL = '/app/api/plans';
const MEMBORA_TRIAL_URL = window.MEMBORA_TRIAL_URL || 'api/trial.php';

const FALLBACK_PLANS = [
  { code: 'BASIC', name: 'Basic', monthly_price: '49.00', max_users: 3, max_members: 300, features: ['CRM de leads y socios.', 'Membresias, pagos y tareas.', 'Soporte por email.'] },
  { code: 'PRO', name: 'Pro', monthly_price: '89.00', max_users: 8, max_members: 1000, features: ['Todo lo incluido en Basic.', 'Clases, reservas y check-ins.', 'Soporte prioritario.'] },
  { code: 'BUSINESS', name: 'Business', monthly_price: '149.00', max_users: 20, max_members: 3000, features: ['Todo lo incluido en Pro.', 'Gestion de equipos y reporting avanzado.', 'Soporte preferente.'] },
  { code: 'ENTERPRISE', name: 'Enterprise', monthly_price: '299.00', max_users: null, max_members: null, features: ['Todo lo incluido en Business.', 'Capacidad para cadenas o franquicias.', 'Soporte dedicado.'] },
];

function startDemoLogin(type = 'client') {
  const form = document.createElement('form');
  form.method = 'post';
  form.action = MEMBORA_DEMO_LOGIN_URL;
  form.style.display = 'none';

  const actionInput = document.createElement('input');
  actionInput.type = 'hidden';
  actionInput.name = 'action';
  actionInput.value = 'demo_login';

  const typeInput = document.createElement('input');
  typeInput.type = 'hidden';
  typeInput.name = 'demo_type';
  typeInput.value = type;

  form.append(actionInput, typeInput);
  document.body.appendChild(form);
  form.submit();
}

document.querySelectorAll('[data-demo-login]').forEach((trigger) => {
  trigger.addEventListener('click', (event) => {
    event.preventDefault();
    startDemoLogin(trigger.dataset.demoLogin || 'client');
  });
});

const pricingGrid = document.querySelector('[data-pricing-grid]');

function formatPlanPrice(value) {
  const amount = Number.parseFloat(String(value || '0'));
  if (!Number.isFinite(amount) || amount <= 0) {
    return 'A medida';
  }

  return `${amount.toLocaleString('es-ES', {
    minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  })} EUR/mes`;
}

function renderPlanCard(plan) {
  const article = document.createElement('article');
  if (String(plan.code).toUpperCase() === 'PRO') {
    article.className = 'highlight-plan';
  }

  const title = document.createElement('h3');
  title.textContent = plan.name || 'Plan Membora';

  const description = document.createElement('p');
  const features = Array.isArray(plan.features) ? plan.features.filter(Boolean) : [];
  description.textContent = 'Plan mensual de Membora CRM.';

  const price = document.createElement('strong');
  if (plan.original_monthly_price) {
    const original = document.createElement('span');
    original.className = 'web-plan-original-price';
    original.textContent = formatPlanPrice(plan.original_monthly_price);
    price.appendChild(original);
  }
  price.append(formatPlanPrice(plan.monthly_price));

  const taxNote = document.createElement('small');
  taxNote.className = 'web-plan-tax-note';
  taxNote.textContent = 'Precios sin IVA.';

  article.append(title, description, price, taxNote);

  if (plan.discount_label) {
    const badge = document.createElement('span');
    badge.className = 'web-plan-discount';
    badge.textContent = plan.discount_label;
    article.appendChild(badge);
  }

  const list = document.createElement('ul');
  const limits = [
    plan.max_users === null ? 'Usuarios sin límite' : `Hasta ${plan.max_users} usuarios`,
    plan.max_members === null ? 'Socios sin límite' : `Hasta ${plan.max_members} socios`,
  ];
  [...limits, ...features].forEach((feature) => {
      const item = document.createElement('li');
      item.textContent = feature;
      list.appendChild(item);
  });
  article.appendChild(list);

  const action = document.createElement('a');
  action.className = 'plan-action';
  const isEnterprise = String(plan.code).toUpperCase() === 'ENTERPRISE';
  action.href = isEnterprise ? '#contacto' : '#prueba-gratis';
  action.textContent = isEnterprise ? 'Contactar' : 'Probar gratis 14 días';
  article.appendChild(action);

  return article;
}

async function fetchPublicPlans(url) {
  const plansUrl = new URL(url, window.location.href);
  plansUrl.searchParams.set('_', String(Date.now()));

  const response = await fetch(plansUrl.toString(), {
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });
  const result = await response.json();
  if (!response.ok || !result.success || !Array.isArray(result.plans) || result.plans.length === 0) {
    throw new Error(result.message || 'No se pudieron cargar los planes.');
  }

  return result.plans;
}

async function loadPublicPlans() {
  if (!pricingGrid) {
    return;
  }

  try {
    let plans;
    try {
      plans = await fetchPublicPlans(MEMBORA_PUBLIC_PLANS_URL);
    } catch (error) {
      plans = await fetchPublicPlans(MEMBORA_REMOTE_PUBLIC_PLANS_URL);
    }

    pricingGrid.replaceChildren(...plans.map(renderPlanCard));
    updateStructuredPlanData(plans);
  } catch (error) {
    pricingGrid.replaceChildren(...FALLBACK_PLANS.map(renderPlanCard));
    updateStructuredPlanData(FALLBACK_PLANS);
  }
}

loadPublicPlans();

const clientFeatures = {
  dashboard: {
    tag: 'Panel operativo',
    title: 'Dashboard',
    description: 'Resumen diario del gimnasio con socios activos, conversión, reservas, tareas pendientes, pagos recientes y avisos importantes.',
    items: [
      'KPIs principales del centro en una sola vista.',
      'Actividad reciente y próximas acciones del equipo.',
      'Acceso rápido a leads, socios, clases, pagos y tareas.',
    ],
  },
  leads: {
    tag: 'Formulario comercial',
    title: 'CRM de leads',
    description: 'Pantalla para registrar interesados, hacer seguimiento comercial y convertir pruebas en socios.',
    items: [
      'Alta de lead con nombre, contacto, origen, interés y notas.',
      'Estados comerciales: nuevo, contactado, prueba, propuesta, convertido o perdido.',
      'Seguimiento de llamadas, WhatsApp, próxima acción y responsable interno.',
    ],
  },
  members: {
    tag: 'Ficha de cliente',
    title: 'Gestión de socios',
    description: 'Ficha centralizada para controlar datos personales, foto, membresía activa, historial, reservas y notas internas.',
    items: [
      'Alta y edición de datos del socio.',
      'Consulta de membresías, pagos, reservas, check-ins y tareas relacionadas.',
      'Estado del socio, vencimientos y observaciones del equipo.',
    ],
  },
  memberships: {
    tag: 'Formulario de planes',
    title: 'Membresías y cuotas',
    description: 'Gestión de planes contratados, precios, fechas de inicio y fin, renovaciones y vencimientos.',
    items: [
      'Asignación de plan a cada socio.',
      'Control de caducidad, renovación y cuotas pendientes.',
      'Vista de planes mensuales, trimestrales, bonos o personalizados.',
    ],
  },
  classes: {
    tag: 'Calendario',
    title: 'Clases y reservas',
    description: 'Organización de sesiones, horarios, aforo, entrenadores y reservas de socios.',
    items: [
      'Creación de clases con fecha, hora, aforo y entrenador.',
      'Reserva de plaza y control de asistencia.',
      'Vista de calendario para recepción y equipo técnico.',
    ],
  },
  checkins: {
    tag: 'Control de acceso',
    title: 'Check-ins',
    description: 'Registro rápido de entradas al centro para consultar actividad y asistencia real de los socios.',
    items: [
      'Entrada manual o desde búsqueda de socio.',
      'Historial de accesos por persona y por fecha.',
      'Detección de socios activos, inactivos o con cuota pendiente.',
    ],
  },
  payments: {
    tag: 'Cobros',
    title: 'Pagos y facturas',
    description: 'Registro de pagos manuales, conceptos, importes, estados y justificantes para controlar la caja del centro.',
    items: [
      'Alta de pago por socio, concepto, fecha e importe.',
      'Estados pagado, pendiente o vencido.',
      'Generación de justificante o factura PDF cuando aplica.',
    ],
  },
  tasks: {
    tag: 'Operación interna',
    title: 'Tareas internas',
    description: 'Lista de acciones para recepcion, comerciales, entrenadores y administradores.',
    items: [
      'Tareas con responsable, prioridad, fecha y estado.',
      'Seguimiento de llamadas, renovaciones, incidencias y gestiones pendientes.',
      'Relaciones con leads o socios concretos.',
    ],
  },
  alerts: {
    tag: 'Avisos',
    title: 'Alertas',
    description: 'Panel de avisos para detectar vencimientos, pagos pendientes, tareas atrasadas y actividad que requiere atención.',
    items: [
      'Avisos por membresías próximas a caducar.',
      'Pagos pendientes o vencidos.',
      'Tareas atrasadas y socios que requieren seguimiento.',
    ],
  },
  users: {
    tag: 'Configuración',
    title: 'Usuarios y roles',
    description: 'Gestión del personal interno que accede al sistema con permisos según su función.',
    items: [
      'Alta de administradores, recepción, comerciales y entrenadores.',
      'Permisos diferenciados por rol.',
      'Control de acceso al panel privado del centro.',
    ],
  },
};

const clientFeatureSelect = document.querySelector('[data-client-feature-select]');
const clientFeatureCard = document.querySelector('[data-client-feature-card]');

function renderClientFeature(featureKey) {
  const feature = clientFeatures[featureKey] || clientFeatures.dashboard;
  if (!clientFeatureCard) {
    return;
  }

  clientFeatureCard.querySelector('[data-client-feature-tag]').textContent = feature.tag;
  clientFeatureCard.querySelector('[data-client-feature-title]').textContent = feature.title;
  clientFeatureCard.querySelector('[data-client-feature-description]').textContent = feature.description;

  const list = clientFeatureCard.querySelector('[data-client-feature-list]');
  list.replaceChildren(...feature.items.map((text) => {
    const item = document.createElement('li');
    item.textContent = text;
    return item;
  }));
}

clientFeatureSelect?.addEventListener('change', (event) => {
  renderClientFeature(event.target.value);
});

renderClientFeature(clientFeatureSelect?.value || 'dashboard');

const clientFeatureMenu = document.querySelector('[data-client-feature-menu]');
const clientFeatureToggle = document.querySelector('[data-client-feature-toggle]');
const clientFeaturePanel = document.querySelector('[data-client-feature-panel]');

function closeClientFeatureMenu() {
  if (!clientFeaturePanel || !clientFeatureToggle) {
    return;
  }

  clientFeaturePanel.hidden = true;
  clientFeatureToggle.setAttribute('aria-expanded', 'false');
}

clientFeatureToggle?.addEventListener('click', () => {
  const isOpen = clientFeaturePanel && !clientFeaturePanel.hidden;
  if (!clientFeaturePanel) {
    return;
  }

  clientFeaturePanel.hidden = isOpen;
  clientFeatureToggle.setAttribute('aria-expanded', String(!isOpen));
});

document.addEventListener('click', (event) => {
  if (!clientFeatureMenu || clientFeatureMenu.contains(event.target)) {
    return;
  }

  closeClientFeatureMenu();
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    const wasOpen = clientFeaturePanel && !clientFeaturePanel.hidden;
    closeClientFeatureMenu();
    if (wasOpen) clientFeatureToggle?.focus();
  }
});

const form = document.querySelector('[data-lead-form]');
const alertBox = document.querySelector('[data-form-alert]');

function showMessage(message, type) {
  if (!alertBox) {
    return;
  }

  alertBox.textContent = message;
  alertBox.className = `form-alert ${type}`;
  alertBox.hidden = false;
}

function payloadFromForm(formElement) {
  const data = new FormData(formElement);
  return {
    nombre: String(data.get('nombre') || '').trim(),
    apellidos: String(data.get('apellidos') || '').trim(),
    empresa: String(data.get('empresa') || '').trim(),
    email: String(data.get('email') || '').trim(),
    telefono: String(data.get('telefono') || '').trim(),
    mensaje: [
      String(data.get('mensaje') || '').trim(),
      String(data.get('empresa') || '').trim() ? `Empresa/gimnasio: ${String(data.get('empresa')).trim()}` : '',
    ].filter(Boolean).join('\n'),
    origen: 'LANDING',
    acepta_rgpd: data.get('acepta_rgpd') ? '1' : '',
    website: String(data.get('website') || '').trim(),
    utm_source: 'web_publica',
    utm_medium: 'landing',
    utm_campaign: 'membora_crm',
    url_origen: window.location.href,
  };
}

form?.addEventListener('submit', async (event) => {
  event.preventDefault();

  const submitButton = form.querySelector('button[type="submit"]');
  const originalText = submitButton?.textContent || 'Enviar solicitud';
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = 'Enviando...';
  }

  try {
    const response = await fetch(MEMBORA_WEBHOOK_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payloadFromForm(form)),
    });

    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'No se pudo enviar la solicitud.');
    }

    form.reset();
    showMessage('Solicitud enviada correctamente. Te responderemos en un plazo aproximado de 24 a 48 horas.', 'success');
  } catch (error) {
    showMessage(error.message || 'No se pudo enviar la solicitud. Inténtalo más tarde.', 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
  }
});

const trialForm = document.querySelector('[data-trial-form]');
const trialAlert = document.querySelector('[data-trial-alert]');

function showTrialMessage(message, type) {
  if (!trialAlert) return;
  trialAlert.textContent = message;
  trialAlert.className = `form-alert ${type}`;
  trialAlert.hidden = false;
}

function updateStructuredPlanData(plans) {
  const script = document.querySelector('#membora-structured-data');
  if (!script) {
    return;
  }

  try {
    const data = JSON.parse(script.textContent);
    const software = data['@graph']?.find((item) => item['@id'] === 'https://membora.es/#software');
    if (!software) {
      return;
    }

    software.offers = plans.map((plan) => ({
      '@type': 'Offer',
      name: plan.name,
      price: String(plan.monthly_price),
      priceCurrency: 'EUR',
      description: 'Precio mensual sin IVA.',
    }));
    script.textContent = JSON.stringify(data);
  } catch (error) {
    // Los planes visibles siguen disponibles aunque el marcado estructurado no pueda actualizarse.
  }
}

trialForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const submitButton = trialForm.querySelector('button[type="submit"]');
  const originalText = submitButton?.textContent || 'Crear prueba gratuita';
  if (submitButton) {
    submitButton.disabled = true;
    submitButton.textContent = 'Creando solicitud...';
  }

  try {
    const data = new FormData(trialForm);
    const response = await fetch(MEMBORA_TRIAL_URL, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        nombre: String(data.get('nombre') || '').trim(),
        empresa: String(data.get('empresa') || '').trim(),
        email: String(data.get('email') || '').trim(),
        acepta_rgpd: data.get('acepta_rgpd') ? '1' : '',
        website: String(data.get('website') || '').trim(),
      }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'No se pudo crear la prueba gratuita.');
    }

    trialForm.reset();
    showTrialMessage(result.message || 'Revisa tu correo para activar la prueba y configurar tu contraseña.', 'success');
  } catch (error) {
    showTrialMessage(error.message || 'No se pudo crear la prueba. Inténtalo más tarde.', 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
  }
});
