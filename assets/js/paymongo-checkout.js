(() => {
  const api = 'https://api.paymongo.com/v1';

  const requestApp = async (form, payload) => {
    const response = await fetch(form.action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...payload, csrf: form.elements.csrf.value }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Could not complete the payment request.');
    return result;
  };

  const requestPayMongo = async (path, publicKey, body) => {
    const response = await fetch(`${api}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Basic ${btoa(`${publicKey}:`)}`,
      },
      body: JSON.stringify(body),
    });
    const result = await response.json();
    if (!response.ok) {
      throw new Error(result.errors?.[0]?.detail || 'PayMongo could not create the QR payment.');
    }
    return result;
  };

  const startStatusPolling = (form, checkoutId) => {
    const message = form.querySelector('[data-paymongo-message]');
    const poll = async () => {
      try {
        const result = await requestApp(form, { action: 'status', checkout_id: checkoutId });
        if (result.status === 'succeeded') {
          message.textContent = 'Payment received! Updating your Nerasky account…';
          window.setTimeout(() => window.location.reload(), 1500);
          return;
        }
        if (result.status !== 'pending') {
          message.textContent = 'This QR payment has expired or failed. Start a new checkout to try again.';
          form.querySelector('[data-paymongo-submit]').disabled = false;
          return;
        }
      } catch (error) {
        message.textContent = error.message;
      }
      window.setTimeout(poll, 5000);
    };
    window.setTimeout(poll, 5000);
  };

  document.querySelectorAll('[data-paymongo-checkout]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('[data-paymongo-submit]');
      const resultBox = form.querySelector('[data-paymongo-result]');
      const message = form.querySelector('[data-paymongo-message]');
      const image = form.querySelector('[data-paymongo-qr]');
      button.disabled = true;
      resultBox.hidden = false;
      image.hidden = true;
      message.textContent = 'Creating your secure PayMongo QR…';

      try {
        const created = await requestApp(form, {
          action: 'create',
          purpose: form.dataset.purpose,
          order_id: form.dataset.orderId,
          amount: form.elements.amount?.value || '',
        });
        const checkout = created.checkout;
        if (checkout.existing && checkout.qr_image) {
          image.src = checkout.qr_image;
          image.hidden = false;
          message.textContent = 'Continue scanning your existing single-use QR Ph code. Keep this page open while PayMongo confirms the payment.';
          startStatusPolling(form, checkout.checkout_id);
          return;
        }
        const paymentMethod = await requestPayMongo('/payment_methods', checkout.public_key, {
          data: { attributes: { type: 'qrph', expiry_seconds: 1800 } },
        });
        const paymentMethodId = paymentMethod.data?.id;
        if (!paymentMethodId) throw new Error('PayMongo did not return a QR payment method.');

        const attached = await requestPayMongo(
          `/payment_intents/${encodeURIComponent(checkout.payment_intent_id)}/attach`,
          checkout.public_key,
          { data: { attributes: { payment_method: paymentMethodId, client_key: checkout.client_key } } },
        );
        const imageUrl = attached.data?.attributes?.next_action?.code?.image_url || '';
        const dataUrl = imageUrl.startsWith('data:image/png;base64,')
          ? imageUrl
          : `data:image/png;base64,${imageUrl}`;
        if (!/^[A-Za-z0-9+/]+={0,2}$/.test(dataUrl.slice('data:image/png;base64,'.length))) {
          throw new Error('PayMongo did not return a valid QR image.');
        }

        await requestApp(form, {
          action: 'attach',
          checkout_id: checkout.checkout_id,
          payment_intent_id: checkout.payment_intent_id,
          qr_image: dataUrl,
        });
        image.src = dataUrl;
        image.hidden = false;
        message.textContent = 'Scan this single-use QR Ph code and pay the exact amount shown. Keep this page open while PayMongo confirms it.';
        startStatusPolling(form, checkout.checkout_id);
      } catch (error) {
        message.textContent = error.message;
        button.disabled = false;
      }
    });
  });
})();
