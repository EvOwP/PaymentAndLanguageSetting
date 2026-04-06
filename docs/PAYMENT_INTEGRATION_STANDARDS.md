# Payment Integration Standards (AI Blueprint)

> **MANDATORY STANDARD**: All future payment gateway integrations (PayPal, Razorpay, etc.) MUST follow this architecture to ensure compatibility with the existing `WebhookController`, `Refund` system, and `Payment` state machine.

---

## 1. Core Architecture Pattern

### Driver Strategy Pattern
- **Base Class**: `app/Services/PaymentGateway/GatewayDriver.php` (All drivers MUST extend this).
- **Factory**: `app/Services/PaymentGateway/GatewayFactory.php` (Register new drivers here).
- **Model**: `App\Models\Payment` (Handles the State Machine).

---

## 2. The Driver Contract (Interface)

Every new driver MUST implement:

| Method | Role | Return Requirement |
|---|---|---|
| `process()` | Initiate payment | `['type' => 'redirect', 'url' => '...']` |
| `handleWebhook()` | Parse & Normalize payload | **MUST** return the [Normalized Array](#3-normalized-webhook-return-contract) |
| `refund()` | Backend API refund | `['status' => '...', 'external_refund_id' => 're_xxx', 'amount' => ...]` |
| `finalize()` | Post-redirect sync | `['status' => 'paid']` or similar |

---

## 3. Normalized Webhook Return Contract

The `WebhookController` is gateway-agnostic. It ONLY works if `handleWebhook()` returns this exact structure:

```php
return [
    // --- LINKING (CRITICAL) ---
    'local_uuid'       => '...',   // Internal Payment UUID (Resolved from metadata)
    'external_id'      => '...',   // Gateway's Transaction ID (ch_xxx, pi_xxx)
    'event_id'         => '...',   // Gateway's Unique Event ID (For Idempotency)
    'status'           => '...',   // Normalized: paid | failed | refunded | partially_refunded
    
    // --- FINANCIALS ---
    'captured_amount'  => 0.00,    // REAL amount captured (Security check vs DB)
    'fee'              => 0.00,    // ACTUAL processing fee from gateway API/payload
    'net_amount'       => 0.00,    // amount - fee
    'customer_email'   => '...',   // Extracted for audit/enrichment
    
    // --- REFUNDING (Multi-Refund Support) ---
    'refund_id'        => '...',   // INDIVIDUAL Refund ID (re_xxx). Key for Refund record.
    'last_refund_amount'=> 0.00,   // Amount of THIS individual refund only.
    
    // --- AUDIT ---
    'event_type'       => '...',   // Raw gateway event name
    'payload'          => [...],   // Full raw body array
    'is_verified'      => true,    // MUST verify signature in driver
    'signature'        => '...',   // Raw signature header
];
```

---

## 4. Refund Logic (The "Triple Fallback" Rule)

To support multiple partial refunds, never deduplicate by Charge ID. Always use the individual Refund ID.
1. **Primary**: Extract from webhook `refunds` data.
2. **Secondary**: Fallback to Gateway API call to get the latest refund object for that charge.
3. **Tertiary**: Fallback to `evt_` + `event_id` if no individual ID is available.

---

## 5. Security & Integrity Rules

1. **State Machine ONLY**: NEVER use `$payment->update(['status' => ...])`. ALWAYS use `$payment->transitionTo('status')`.
2. **Metadata Persistence**: The `WebhookController` handles "Enrichment Bypass". It will save `fee`, `email`, and `risk_score` even if the status transition is blocked (e.g. duplicate `paid` notifications).
3. **Amount Check**: The controller automatically fails a payment if `captured_amount < payment->amount`.
4. **Idempotency**: Webhook events are deduplicated via the `payment_logs` table using `(event_id, payment_id)` composite unique index.

---

## 6. Development Checklist for New AI Integrations

- [ ] Extend `GatewayDriver`.
- [ ] Implement `process()`: Ensure `payment_uuid` is passed to Gateway Metadata.
- [ ] Implement `handleWebhook()`:
    - [ ] Perform Signature Verification.
    - [ ] Resolve `local_uuid` from metadata (or API fallback).
    - [ ] Extract real processing fees.
    - [ ] Extract individual refund IDs (`re_xxx` style).
- [ ] Implement `refund()`: Use the gateway API.
- [ ] Register driver in `GatewayFactory::match`.
- [ ] Expose Webhook route: `/api/webhooks/{gateway_name}` (Matches `PaymentGateway.name`).

---

## 7. Knowledge Items (AI Context)

- **Source of Truth**: Webhooks.
- **Deduplication**: Handled at the `WebhookController` level via `event_id`.
- **Database Row Lock**: `WebhookController` uses `lockForUpdate()` on the `Payment` record during processing to prevent race conditions.
