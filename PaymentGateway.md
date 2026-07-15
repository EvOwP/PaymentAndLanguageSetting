# Payment Gateway Integration Guide

*Version: 1.1 – based on the robust Stripe implementation*

This document describes the **payment-gateway architecture** used in the **PaymentAndLanguageSetting** project. It serves as a blueprint for adding new payment providers while maintaining consistency in reconciliation, logging, and security.

---

## 1. High-Level Architecture

The system uses a **Normalization & Reconciliation** pattern. Webhooks are first normalized by a gateway-specific driver and then reconciled by a central service.

```text
+-------------------+          +-------------------+          +-------------------+
| WebhookController |  <--->   |   GatewayDriver   |  <--->   |  PaymentGateway   |
| (HTTP entry point)|          | (abstract base)   |          |  (model/config)   |
+-------------------+          +-------------------+          +-------------------+
          |                              |                           |
          | 1. Receive webhook → driver  | 2. Call driver methods    |
          |    (handleWebhook)           |    (process, finalize,    |
          |                              |     checkStatus, refund)  |
          v                              v                           v
+-------------------+          +-------------------+          +-------------------+
| PaymentRecon-     |   |      |  Driver-specific  |   |      |  PaymentGateway   |
| ciliationService  |   |      |  implementation   |   |      |  (database row)   |
+-------------------+          +-------------------+          +-------------------+
          |
          | 3. Atomic Transaction (Locking, Idempotency, State Machine)
          v
+-------------------+
|  Database Tables  |
| payments, payment_logs, payment_transactions, refunds
+-------------------+
```

### Core Components
* **`WebhookController`**: The bridge between external gateways and our system. It ensures that every incoming request is logged as a "pending" event before processing begins, ensuring we never lose a webhook.
* **`GatewayDriver`**: An abstract interface that shields the system from gateway-specific API quirks.
* **`PaymentReconciliationService`**: The "Brain" of the system. It handles the database transaction, row-level locking, and state transitions.

---

## 2. The Reconciliation Flow (Stripe Pattern)

Every driver should follow the pattern established in the Stripe implementation to ensure data integrity.

### 2.1. Atomic Locking & Idempotency
The system uses `DB::transaction` with `lockForUpdate()` to prevent race conditions (e.g., two webhooks arriving simultaneously).

```php
// Found in PaymentReconciliationService
DB::transaction(function () use (...) {
    $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
    
    // Idempotency check: Skip if this event_id was already successfully processed
    if ($eventId && PaymentLog::where('event_id', $eventId)->where('processed', true)->exists()) {
        return; 
    }
    ...
});
```

### 2.2. Security & Logic Exploit Protection
Always verify the captured amount against the expected amount in our database to prevent "Price Manipulation" attacks.

| Check | Action on Failure |
|-------|-------------------|
| **Captured Amount < Expected** | Force status to `failed` and log a Security Warning in notes. |
| **Signature Invalid** | Return early and do not record a log entry. |

### 2.3. Data Enrichment
Even if a state transition is blocked (e.g., receiving a `paid` webhook for a payment already marked `paid`), we still save "Enrichment Data" like risk scores, customer emails, and exchange rates.

---

## 3. Webhook Normalization (The Contract)

All drivers **MUST** return a canonical array from `handleWebhook()`. This is the contract that allows the reconciliation service to remain gateway-agnostic.

### Canonical Array Structure
```php
[
    'local_uuid'        => '...',    // The UUID we sent to the gateway in metadata
    'external_id'       => '...',    // Gateway's Session/Intent ID
    'event_id'          => '...',    // Unique ID for this specific webhook event
    'event_type'        => '...',    // raw event type (e.g. checkout.session.completed)
    'status'            => '...',    // paid, failed, refunded, etc.
    'captured_amount'   => 0.00,     // Actual amount captured by gateway
    'fee'               => 0.00,     // Transaction fee charged by gateway
    'net_amount'        => 0.00,     // Amount we actually receive
    'original_currency' => '...',    // Customer's local currency
    'original_amount'   => 0.00,     // Amount in customer's currency
    'risk_score'        => 0-100,    // Gateway's risk assessment
    'is_fraud'          => false,    // Boolean based on gateway flags
    'is_verified'       => true,     // Result of signature verification
    'payload'           => [...],    // The full raw JSON from the gateway
]
```

---

## 4. Error Handling & Retries

The system is designed to be "Self-Healing."

1. **Initial Log**: `WebhookController` creates a log with `processed = false`.
2. **Failure**: If the reconciliation service throws an exception, the database rolls back, but the `PaymentLog` remains with `processed = false`.
3. **Retry Command**: The `payments:retry-webhooks` command finds these failed logs, increments `retry_count`, and re-attempts processing.

### Max Retries
By default, the system attempts **5 retries**. If it still fails, it stops automatically to prevent infinite loops, allowing for manual developer intervention.

---

## 5. Implementation Checklist for New Gateways

1. [ ] **Migration**: Add gateway credentials to the `payment_gateways` table.
2. [ ] **Driver**: Create `app/Services/PaymentGateway/NewGatewayDriver.php` extending `GatewayDriver`.
3. [ ] **Normalization**: Implement `handleWebhook` returning the canonical array.
4. [ ] **Factory**: Register the new driver in `GatewayFactory.php`.
5. [ ] **Webhook Route**: Ensure the gateway is pointed to `/api/webhooks/new-gateway`.
6. [ ] **Metadata**: Ensure you pass our `payment_uuid` to the gateway's metadata during `process()` so we can link the webhook back.
