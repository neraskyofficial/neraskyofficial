<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function paymongo_api_request(string $method, string $path, ?array $payload = null): array
{
    if (!PAYMONGO_ENABLED) {
        throw new RuntimeException('PayMongo QR Ph is not configured.');
    }
    if (!extension_loaded('curl')) {
        throw new RuntimeException('The server does not have PHP cURL enabled.');
    }

    $curl = curl_init(rtrim(PAYMONGO_API_BASE_URL, '/') . '/' . ltrim($path, '/'));
    if ($curl === false) {
        throw new RuntimeException('Could not initialize the PayMongo request.');
    }
    $headers = [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if (!is_string($body)) {
        error_log('PayMongo API request failed: ' . ($curlError !== '' ? $curlError : 'empty response'));
        throw new RuntimeException('Could not reach PayMongo. Please try again.');
    }
    try {
        $response = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('PayMongo returned an invalid JSON response (HTTP ' . $status . ').');
        throw new RuntimeException('PayMongo returned an unreadable response. Please try again.');
    }
    if ($status < 200 || $status >= 300 || !is_array($response)) {
        $apiMessage = (string) ($response['errors'][0]['detail'] ?? $response['errors'][0]['code'] ?? 'unknown API error');
        error_log('PayMongo API returned HTTP ' . $status . ': ' . mb_substr($apiMessage, 0, 250));
        throw new RuntimeException('PayMongo could not start this payment. Check QR Ph access and credentials, then try again.');
    }
    return $response;
}

function paymongo_create_checkout(int $userId, string $purpose, int $orderId = 0, float $walletAmount = 0): array
{
    if (!in_array($purpose, ['order', 'wallet', 'pro'], true)) {
        throw new InvalidArgumentException('Unknown checkout type.');
    }
    if ($purpose === 'order') {
        $order = row('SELECT id, buyer_id, price, status FROM orders WHERE id = ?', [$orderId]);
        if (!$order || (int) $order['buyer_id'] !== $userId || $order['status'] !== 'awaiting_payment') {
            throw new RuntimeException('This order cannot be paid right now.');
        }
        $amount = (float) $order['price'];
    } elseif ($purpose === 'wallet') {
        $amount = round($walletAmount, 2);
        if ($amount < 100 || $amount > 5000) {
            throw new InvalidArgumentException('Choose a wallet top-up between ₱100 and ₱5,000.');
        }
    } else {
        $amount = (float) PREMIUM_PRICE;
    }
    $centavos = (int) round($amount * 100);
    if ($centavos < 100 || $centavos > 100000000) {
        throw new InvalidArgumentException('The payment amount is outside the supported range.');
    }

    $existing = row(
        'SELECT id, payment_intent_id, qr_image FROM paymongo_checkouts
         WHERE user_id = ? AND purpose = ? AND amount_centavos = ?
           AND ((order_id IS NULL AND ? = 0) OR order_id = ?)
           AND status = "pending" AND qr_image IS NOT NULL
           AND created_at >= DATE_SUB(NOW(), INTERVAL 25 MINUTE)
         ORDER BY id DESC LIMIT 1',
        [$userId, $purpose, $centavos, $purpose === 'order' ? $orderId : 0, $purpose === 'order' ? $orderId : 0]
    );
    if ($existing) {
        return [
            'checkout_id' => (int) $existing['id'],
            'payment_intent_id' => (string) $existing['payment_intent_id'],
            'qr_image' => (string) $existing['qr_image'],
            'existing' => true,
        ];
    }

    $db = db();
    $db->beginTransaction();
    try {
        if ($purpose === 'order') {
            $locked = row('SELECT id, buyer_id, status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$locked || (int) $locked['buyer_id'] !== $userId || $locked['status'] !== 'awaiting_payment') {
                throw new RuntimeException('This order cannot be paid right now.');
            }
            $otherPayment = row(
                "SELECT id FROM payments WHERE order_id = ? AND status IN ('pending','verified') LIMIT 1",
                [$orderId]
            );
            $walletPayment = row(
                "SELECT id FROM wallet_transactions WHERE order_id = ? AND type = 'wallet_payment' AND direction = 'debit' LIMIT 1",
                [$orderId]
            );
            $incompleteCheckout = row(
                "SELECT id FROM paymongo_checkouts
                 WHERE order_id = ? AND user_id = ? AND purpose = 'order' AND status = 'pending'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 25 MINUTE)
                 LIMIT 1",
                [$orderId, $userId]
            );
            if ($otherPayment || $walletPayment || $incompleteCheckout) {
                throw new RuntimeException('Another payment is already pending or has been received for this order. Check its payment status before trying again.');
            }
        }
        $checkoutId = insert(
            'INSERT INTO paymongo_checkouts (user_id, purpose, order_id, amount_centavos, status) VALUES (?,?,?,?,?)',
            [$userId, $purpose, $purpose === 'order' ? $orderId : null, $centavos, 'pending']
        );
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $descriptions = [
        'order' => 'Nerasky order #' . $orderId,
        'wallet' => 'Nerasky wallet top-up',
        'pro' => 'Nerasky Pro membership',
    ];
    try {
        $intent = paymongo_api_request('POST', '/payment_intents', [
            'data' => [
                'attributes' => [
                    'amount' => $centavos,
                    'currency' => 'PHP',
                    'payment_method_allowed' => ['qrph'],
                    'description' => $descriptions[$purpose],
                    'metadata' => ['nerasky_checkout_id' => (string) $checkoutId],
                ],
            ],
        ]);
        $attributes = $intent['data']['attributes'] ?? [];
        $intentId = (string) ($intent['data']['id'] ?? '');
        $clientKey = (string) ($attributes['client_key'] ?? '');
        if ($intentId === '' || $clientKey === '') {
            throw new RuntimeException('PayMongo did not return a usable payment intent.');
        }
        q('UPDATE paymongo_checkouts SET payment_intent_id = ? WHERE id = ?', [$intentId, $checkoutId]);
        return [
            'checkout_id' => $checkoutId,
            'payment_intent_id' => $intentId,
            'client_key' => $clientKey,
            'public_key' => PAYMONGO_PUBLIC_KEY,
            'existing' => false,
        ];
    } catch (Throwable $e) {
        q("UPDATE paymongo_checkouts SET status = 'failed' WHERE id = ? AND status = 'pending'", [$checkoutId]);
        throw $e;
    }
}

function paymongo_fetch_intent(string $intentId): array
{
    if (preg_match('/^pi_[A-Za-z0-9_-]{8,}$/', $intentId) !== 1) {
        throw new InvalidArgumentException('Invalid payment intent.');
    }
    return paymongo_api_request('GET', '/payment_intents/' . rawurlencode($intentId));
}

function paymongo_save_qr(int $userId, int $checkoutId, string $intentId, string $imageData): void
{
    if (strlen($imageData) > 1500000
        || preg_match('~^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$~D', $imageData, $matches) !== 1) {
        throw new InvalidArgumentException('PayMongo returned an invalid QR image.');
    }
    $png = base64_decode($matches[1], true);
    if (!is_string($png) || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new InvalidArgumentException('PayMongo returned an invalid QR image.');
    }
    $updated = q(
        "UPDATE paymongo_checkouts SET qr_image = ?
         WHERE id = ? AND user_id = ? AND payment_intent_id = ? AND status = 'pending'",
        [$imageData, $checkoutId, $userId, $intentId]
    );
    if ($updated->rowCount() < 1) {
        throw new RuntimeException('This checkout is no longer active.');
    }
}

function paymongo_apply_paid_intent(string $intentId, array $intent): bool
{
    $attributes = $intent['data']['attributes'] ?? [];
    if (($attributes['status'] ?? '') !== 'succeeded') {
        return false;
    }
    $checkout = row('SELECT * FROM paymongo_checkouts WHERE payment_intent_id = ?', [$intentId]);
    if (!$checkout
        || (int) ($attributes['amount'] ?? -1) !== (int) $checkout['amount_centavos']
        || strtoupper((string) ($attributes['currency'] ?? '')) !== 'PHP') {
        throw new RuntimeException('PayMongo payment does not match a Nerasky checkout.');
    }

    $db = db();
    $startedTransaction = !$db->inTransaction();
    if ($startedTransaction) {
        $db->beginTransaction();
    }
    try {
        $locked = row('SELECT * FROM paymongo_checkouts WHERE id = ? FOR UPDATE', [(int) $checkout['id']]);
        if (!$locked) {
            throw new RuntimeException('Nerasky checkout record is missing.');
        }
        if ($locked['status'] === 'succeeded') {
            if ($startedTransaction) {
                $db->commit();
            }
            return true;
        }
        if ($locked['status'] !== 'pending') {
            if ($startedTransaction) {
                $db->commit();
            }
            return false;
        }

        $userId = (int) $locked['user_id'];
        $amount = $locked['amount_centavos'] / 100;
        if ($locked['purpose'] === 'wallet') {
            wallet_credit($userId, $amount, 'PayMongo QR Ph wallet top-up #' . (int) $locked['id'], null, 'cash_in');
            notify($userId, 'wallet', 'Your PayMongo wallet top-up of ' . money($amount) . ' was received.', 'wallet.php');
        } elseif ($locked['purpose'] === 'pro') {
            $user = row('SELECT premium_until FROM users WHERE id = ? FOR UPDATE', [$userId]);
            if (!$user) {
                throw new RuntimeException('The Nerasky account for this Pro payment no longer exists.');
            }
            $currentEnd = !empty($user['premium_until']) ? strtotime((string) $user['premium_until']) : false;
            $startsAt = $currentEnd && $currentEnd > time() ? $currentEnd : time();
            $endsAt = $startsAt + PREMIUM_DAYS * 86400;
            $startSql = date('Y-m-d H:i:s', $startsAt);
            $endSql = date('Y-m-d H:i:s', $endsAt);
            q('UPDATE users SET premium_until = ? WHERE id = ?', [$endSql, $userId]);
            insert(
                'INSERT INTO subscriptions (user_id, plan, amount, source, starts_at, ends_at, status)
                 VALUES (?,?,?,?,?,?,?)',
                [$userId, 'pro', $amount, 'paymongo', $startSql, $endSql, 'active']
            );
            notify($userId, 'premium', 'Your PayMongo payment was received. Nerasky Pro is active through ' . date('M j, Y', $endsAt) . '.', 'premium.php');
        } elseif ($locked['purpose'] === 'order') {
            $order = row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [(int) $locked['order_id']]);
            if ($order && $order['status'] === 'awaiting_payment') {
                q('UPDATE orders SET status = "in_progress" WHERE id = ? AND status = "awaiting_payment"', [(int) $order['id']]);
                insert('INSERT INTO messages (order_id, sender_id, body, is_read) VALUES (?,?,?,0)',
                    [(int) $order['id'], $userId, 'PayMongo QR Ph payment received. The seller can now begin work.']);
                notify((int) $order['seller_id'], 'order', 'PayMongo payment received for order #' . (int) $order['id'] . '. You can start working.', 'order.php?order=' . (int) $order['id']);
                notify($userId, 'payment', 'Your PayMongo payment for order #' . (int) $order['id'] . ' was received.', 'order.php?order=' . (int) $order['id']);
            } else {
                notify_admins('payment', 'PayMongo payment received for checkout #' . (int) $locked['id'] . ', but its order is no longer awaiting payment. Review for refund.', 'payments.php');
                notify($userId, 'payment', 'Your payment was received, but the order is no longer active. Contact Nerasky support before placing another order.', 'support.php');
            }
        }
        q("UPDATE paymongo_checkouts SET status = 'succeeded', paid_at = NOW() WHERE id = ?", [(int) $locked['id']]);
        if ($startedTransaction) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($startedTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function paymongo_verify_webhook_signature(string $rawBody, string $header): bool
{
    if (!PAYMONGO_ENABLED || $header === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $header) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    if (isset($parts['t'])) {
        $timestamp = filter_var($parts['t'], FILTER_VALIDATE_INT);
        if ($timestamp === false || abs(time() - $timestamp) > 300) {
            return false;
        }
        $signature = str_starts_with(PAYMONGO_SECRET_KEY, 'sk_live_')
            ? ($parts['li'] ?? '')
            : ($parts['te'] ?? '');
        return $signature !== ''
            && hash_equals(hash_hmac('sha256', $parts['t'] . '.' . $rawBody, PAYMONGO_WEBHOOK_SECRET), $signature);
    }
    return hash_equals(hash_hmac('sha256', $rawBody, PAYMONGO_WEBHOOK_SECRET), $header);
}

function paymongo_handle_webhook_event(array $event): void
{
    $eventAttributes = $event['data']['attributes'] ?? [];
    $eventId = (string) ($event['data']['id'] ?? $event['id'] ?? '');
    $eventType = (string) ($eventAttributes['type'] ?? $event['type'] ?? '');
    $resource = $eventAttributes['data'] ?? [];
    $resourceAttributes = $resource['attributes'] ?? [];
    $intentId = (string) ($resourceAttributes['payment_intent_id'] ?? $resource['id'] ?? '');
    if ($eventId === '' || $eventType === '' || $intentId === '') {
        throw new InvalidArgumentException('PayMongo webhook payload is incomplete.');
    }
    if (!in_array($eventType, ['payment.paid', 'payment.failed'], true)) {
        return;
    }
    $intent = $eventType === 'payment.paid' ? paymongo_fetch_intent($intentId) : null;

    $db = db();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO paymongo_webhook_events (event_id, event_type) VALUES (?,?)')
            ->execute([$eventId, $eventType]);
        if ($eventType === 'payment.paid') {
            if (!is_array($intent)) {
                throw new RuntimeException('PayMongo did not return the completed Payment Intent.');
            }
            if (!paymongo_apply_paid_intent($intentId, $intent)) {
                throw new RuntimeException('PayMongo reported a paid event, but the Payment Intent is not succeeded.');
            }
        } else {
            q("UPDATE paymongo_checkouts SET status = 'failed'
               WHERE payment_intent_id = ? AND status = 'pending'", [$intentId]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ((string) $e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            return;
        }
        throw $e;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
