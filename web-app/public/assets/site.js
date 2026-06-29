const MEMBORA_WEBHOOK_URL = 'https://app.crm.josehurtado.dev/webhook/lead';

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
