<section class="simulated-checkout-shell">
  <a class="simulated-checkout-back" href="index.php?route=upgrade-plan">&larr; Volver a los planes</a>

  <div class="simulated-checkout-grid">
    <article class="simulated-checkout-summary">
      <span class="eyebrow">CHECKOUT DE DEMOSTRACIÓN</span>
      <h2><?= e((string) $plan['name']) ?></h2>
      <p>Suscripción <?= $renewalPeriod === 'ANNUAL' ? 'anual' : 'mensual' ?> para <?= e((string) $empresa['name']) ?>.</p>

      <div class="simulated-checkout-total">
        <span>Total de prueba</span>
        <strong><?= e(money_amount($checkoutAmount)) ?></strong>
      </div>

      <ul>
        <li>No se realiza ningún cargo bancario.</li>
        <li>No se conecta con Stripe ni con una entidad financiera.</li>
        <li>El pago y la factura aparecerán marcados como simulados en el panel administrador.</li>
      </ul>
    </article>

    <article class="simulated-checkout-card">
      <div class="simulated-checkout-badge">MODO PRUEBA</div>
      <h3>Tarjeta ficticia</h3>
      <p>Utiliza solamente los datos de demostración mostrados. No introduzcas una tarjeta real.</p>

      <form method="post" action="index.php?route=simulated-checkout" autocomplete="off">
        <input type="hidden" name="action" value="complete_tenant_simulated_checkout">
        <input type="hidden" name="plan_code" value="<?= e((string) $plan['code']) ?>">
        <input type="hidden" name="renewal_period" value="<?= e($renewalPeriod) ?>">

        <label class="field form-full">
          <span>Número de tarjeta de prueba</span>
          <input name="card_number" inputmode="numeric" value="4242 4242 4242 4242" maxlength="19" required aria-describedby="simulated-card-help">
        </label>
        <div class="simulated-checkout-fields">
          <label class="field">
            <span>Caducidad ficticia</span>
            <input name="card_expiry" inputmode="numeric" value="12/30" maxlength="5" required>
          </label>
          <label class="field">
            <span>CVC ficticio</span>
            <input name="card_cvc" inputmode="numeric" value="123" maxlength="3" required>
          </label>
        </div>

        <small id="simulated-card-help">Estos datos se validan como valores ficticios y se descartan inmediatamente.</small>
        <button class="primary-action simulated-checkout-submit" type="submit">Confirmar pago simulado de <?= e(money_amount($checkoutAmount)) ?></button>
      </form>
    </article>
  </div>
</section>
