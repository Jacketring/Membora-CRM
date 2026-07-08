const MEMBORA_WEBHOOK_URL = 'https://app.crm.josehurtado.dev/webhook/lead';
const MEMBORA_DEMO_LOGIN_URL = 'https://app.crm.josehurtado.dev/index.php?route=login';
const MEMBORA_PUBLIC_PLANS_URL = 'https://app.crm.josehurtado.dev/api/plans';

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
  title.textContent = plan.name || 'Plan CRM';

  const description = document.createElement('p');
  const features = Array.isArray(plan.features) ? plan.features.filter(Boolean) : [];
  description.textContent = features[0] || 'Plan comercial de Membora CRM.';

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
    const response = await fetch(MEMBORA_PUBLIC_PLANS_URL, { headers: { Accept: 'application/json' } });
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
