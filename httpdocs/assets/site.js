const MEMBORA_WEBHOOK_URL = 'https://app.crm.josehurtado.dev/webhook/lead';
const MEMBORA_DEMO_LOGIN_URL = 'https://app.crm.josehurtado.dev/index.php?route=login';
const MEMBORA_PUBLIC_PLANS_URL = window.MEMBORA_PUBLIC_PLANS_URL || 'https://app.crm.josehurtado.dev/api/plans';

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

function renderPlanCard(plan, index) {
  const article = document.createElement('article');
  if (index === 1) {
    article.className = 'highlight-plan';
  }

  const title = document.createElement('h3');
  title.textContent = plan.name || 'Plan Membora';

  const description = document.createElement('p');
  const features = Array.isArray(plan.features) ? plan.features.filter(Boolean) : [];
  description.textContent = features[0] || 'Plan comercial de Membora.';

  const price = document.createElement('strong');
  if (plan.original_monthly_price) {
    const original = document.createElement('span');
    original.className = 'web-plan-original-price';
    original.textContent = formatPlanPrice(plan.original_monthly_price);
    price.appendChild(original);
  }
  price.append(formatPlanPrice(plan.monthly_price));

  article.append(title, description, price);

  if (plan.discount_label) {
    const badge = document.createElement('span');
    badge.className = 'web-plan-discount';
    badge.textContent = plan.discount_label;
    article.appendChild(badge);
  }

  if (features.length > 1) {
    const list = document.createElement('ul');
    features.slice(1, 4).forEach((feature) => {
      const item = document.createElement('li');
      item.textContent = feature;
      list.appendChild(item);
    });
    article.appendChild(list);
  }

  return article;
}

async function loadPublicPlans() {
  if (!pricingGrid) {
    return;
  }

  try {
    const plansUrl = new URL(MEMBORA_PUBLIC_PLANS_URL, window.location.href);
    plansUrl.searchParams.set('_', String(Date.now()));
    const response = await fetch(plansUrl.toString(), {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    const result = await response.json();
    if (!response.ok || !result.success || !Array.isArray(result.plans) || result.plans.length === 0) {
      return;
    }

    pricingGrid.replaceChildren(...result.plans.map(renderPlanCard));
  } catch (error) {
    // La web mantiene los planes estaticos si el CRM no responde.
  }
}

loadPublicPlans();

const clientFeatures = {
  dashboard: {
    tag: 'Panel operativo',
    title: 'Dashboard',
    description: 'Resumen diario del gimnasio con socios activos, conversion, reservas, tareas pendientes, pagos recientes y avisos importantes.',
    items: [
      'KPIs principales del centro en una sola vista.',
      'Actividad reciente y proximas acciones del equipo.',
      'Acceso rapido a leads, socios, clases, pagos y tareas.',
    ],
  },
  leads: {
    tag: 'Formulario comercial',
    title: 'CRM de leads',
    description: 'Pantalla para registrar interesados, hacer seguimiento comercial y convertir pruebas en socios.',
    items: [
      'Alta de lead con nombre, contacto, origen, interes y notas.',
      'Estados comerciales: nuevo, contactado, prueba, propuesta, convertido o perdido.',
      'Seguimiento de llamadas, WhatsApp, proxima accion y responsable interno.',
    ],
  },
  members: {
    tag: 'Ficha de cliente',
    title: 'Gestion de socios',
    description: 'Ficha centralizada para controlar datos personales, foto, membresia activa, historial, reservas y notas internas.',
    items: [
      'Alta y edicion de datos del socio.',
      'Consulta de membresias, pagos, reservas, check-ins y tareas relacionadas.',
      'Estado del socio, vencimientos y observaciones del equipo.',
    ],
  },
  memberships: {
    tag: 'Formulario de planes',
    title: 'Membresias y cuotas',
    description: 'Gestion de planes contratados, precios, fechas de inicio y fin, renovaciones y vencimientos.',
    items: [
      'Asignacion de plan a cada socio.',
      'Control de caducidad, renovacion y cuotas pendientes.',
      'Vista de planes mensuales, trimestrales, bonos o personalizados.',
    ],
  },
  classes: {
    tag: 'Calendario',
    title: 'Clases y reservas',
    description: 'Organizacion de sesiones, horarios, aforo, entrenadores y reservas de socios.',
    items: [
      'Creacion de clases con fecha, hora, aforo y entrenador.',
      'Reserva de plaza y control de asistencia.',
      'Vista de calendario para recepcion y equipo tecnico.',
    ],
  },
  checkins: {
    tag: 'Control de acceso',
    title: 'Check-ins',
    description: 'Registro rapido de entradas al centro para consultar actividad y asistencia real de los socios.',
    items: [
      'Entrada manual o desde busqueda de socio.',
      'Historial de accesos por persona y por fecha.',
      'Deteccion de socios activos, inactivos o con cuota pendiente.',
    ],
  },
  payments: {
    tag: 'Cobros',
    title: 'Pagos y facturas',
    description: 'Registro de pagos manuales, conceptos, importes, estados y justificantes para controlar la caja del centro.',
    items: [
      'Alta de pago por socio, concepto, fecha e importe.',
      'Estados pagado, pendiente o vencido.',
      'Generacion de justificante o factura PDF cuando aplica.',
    ],
  },
  tasks: {
    tag: 'Operacion interna',
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
    description: 'Panel de avisos para detectar vencimientos, pagos pendientes, tareas atrasadas y actividad que requiere atencion.',
    items: [
      'Avisos por membresias proximas a caducar.',
      'Pagos pendientes o vencidos.',
      'Tareas atrasadas y socios que requieren seguimiento.',
    ],
  },
  users: {
    tag: 'Configuracion',
    title: 'Usuarios y roles',
    description: 'Gestion del personal interno que accede al sistema con permisos segun su funcion.',
    items: [
      'Alta de administradores, recepcion, comerciales y entrenadores.',
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
    closeClientFeatureMenu();
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
    showMessage('Solicitud enviada correctamente. Te contactaremos pronto.', 'success');
  } catch (error) {
    showMessage(error.message || 'No se pudo enviar la solicitud. Intentalo mas tarde.', 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalText;
    }
  }
});
