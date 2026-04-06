# Payment and Language Management System

A robust Laravel-based system for managing multiple payment gateways (Stripe, PayPal, Manual) and dynamic application localization.

## 🚀 Key Features

### 💳 Payment Infrastructure
- **Multi-Gateway Support**: Integrated with Stripe, PayPal, and manual bank transfers.
- **Normalization Layer**: Uses a `GatewayDriver` abstraction to normalize webhook data from different providers into a single internal format.
- **State Machine**: Enforces strict payment status transitions (pending → paid → refunded, etc.) via a dedicated `transitionTo()` method.
- **Idempotency & Security**:
    - `lockForUpdate()` prevents race conditions during concurrent webhook deliveries.
    - Composite unique index `(event_id, payment_id)` prevents duplicate webhook processing.
    - `captured_amount` verification to detect logic exploits.
    - Idempotency keys for checkout session creation.
- **Refund System**: Supports multiple partial refunds per payment, tracked uniquely by individual gateway refund IDs.
- **Audit Logs**: Immutable `payment_logs` and `payment_status_histories` for every transaction and status change.

### 🌐 Localization & Language
- **Dynamic Languages**: Manage application languages via the Admin UI.
- **Default Language**: Toggle and set default system language.
- **Navbar Integration**: Control which languages appear in the frontend language switcher.
- **Middleware-based**: Automatic language detection and application.

### 🛠 Administrative Dashboard
- **Gateway Management**: Configure API credentials (JSON-based) and fees for each gateway.
- **Payment Tracking**: Detailed view of all payments, including fees, net amounts, risk scores, and full webhook payloads.
- **Manual Approval**: dedicated flow for approving or rejecting manual payment proofs.
- **Notes & History**: Append admin notes and view full audit trails for every payment.

---

## 📂 Project Structure

```bash
app/
├── Http/Controllers/
│   ├── WebhookController.php          # Universal webhook handler (Gateway-Agnostic)
│   ├── Admin/
│   │   └── PaymentController.php      # Admin payment & refund management
│   └── ...
├── Services/PaymentGateway/
│   ├── GatewayDriver.php              # Abstract contract for all gateways
│   ├── GatewayFactory.php             # Strategy pattern resolver
│   ├── StripeDriver.php               # Stripe implementation (Reference)
│   └── PaypalDriver.php               # PayPal implementation
├── Models/
│   ├── Payment.php                    # State machine & transition logic
│   └── ...
docs/
└── PAYMENT_INTEGRATION_STANDARDS.md   # Blueprints for adding new gateways
```

---

## 🛠 Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/EvOwP/PaymentAndLanguageSetting.git
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run Migrations & Seeders**:
   ```bash
   php artisan migrate --seed
   ```

---

## 💡 Adding a New Payment Gateway

The system is designed for extensibility. To add a new gateway (e.g., Razorpay):
1. Create a new driver in `app/Services/PaymentGateway/` extending `GatewayDriver`.
2. Implement `process()`, `handleWebhook()`, and `refund()`.
3. Register the driver in `GatewayFactory.php`.
4. Follow the specific instructions in [PAYMENT_INTEGRATION_STANDARDS.md](docs/PAYMENT_INTEGRATION_STANDARDS.md).

---

## 🛡 Security

We prioritize financial integrity:
- **Webhook Signatures**: Always verified at the driver level.
- **Amount Verification**: Controller rejects payments if the gateway-captured amount is less than the expected database amount.
- **State Machine**: Prevents illegal transitions (e.g., changing a `failed` payment to `paid`).

---

## 📝 License
This project is open-sourced software licensed under the [MIT license](LICENSE).
