# Order and Payment Management API

A Laravel 12 REST API for managing authenticated customer orders and simulated payments. The payment layer uses a Strategy Pattern so new gateways can be added with minimal changes.

## Tech Stack

- PHP 8.2+
- Laravel 12
- MySQL or another Laravel-supported database
- JWT authentication via `tymon/jwt-auth`
- Pest for feature and unit tests
- Laravel Pint for PSR-12 formatting

## Features

- JWT registration, login, profile, and logout endpoints.
- Order creation, listing, filtering, viewing, updating, status changes, and deletion.
- Server-side order total calculation from order items.
- Payment processing for confirmed orders.
- Config-driven payment gateway strategies.
- Payment listing globally or by order.
- Business-rule responses using appropriate HTTP status codes.
- Postman collection export in `docs/postman/order-payment-management-api.postman_collection.json`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

For local test verification:

```bash
php artisan test
vendor/bin/pint --dirty
```

## Environment

Important variables:

```env
APP_URL=http://localhost
AUTH_GUARD=api
JWT_SECRET=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paymentmanagement
DB_USERNAME=root
DB_PASSWORD=

CREDIT_CARD_ENABLED=true
CREDIT_CARD_API_KEY=
CREDIT_CARD_SECRET=
PAYPAL_ENABLED=true
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
FAILING_GATEWAY_ENABLED=true
```

Run `php artisan jwt:secret` after copying `.env`; this writes a local JWT secret.

## Architecture

The API uses a light Clean Architecture style that fits Laravel without overcomplicating the project:

- **Controllers** receive HTTP requests and return resources.
- **Form Requests** own validation.
- **Actions** own business workflows.
- **Resources** own response shape.
- **Enums** define stable domain values.
- **Gateway contracts and services** isolate payment provider logic.
- **Eloquent models and migrations** own persistence.

This keeps controllers thin while avoiding unnecessary repository layers where Eloquent is already clear and expressive.

## API Authentication

All order and payment endpoints require:

```http
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

### Register

```http
POST /api/v1/auth/register
```

Request:

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

Success response `201`:

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com"
    },
    "token": {
      "access_token": "jwt-token",
      "token_type": "bearer",
      "expires_in": 3600
    }
  }
}
```

### Login

```http
POST /api/v1/auth/login
```

Request:

```json
{
  "email": "jane@example.com",
  "password": "password123"
}
```

Invalid credentials response `401`:

```json
{
  "message": "Invalid credentials."
}
```

### Current User

```http
GET /api/v1/auth/me
```

### Logout

```http
POST /api/v1/auth/logout
```

## Orders API

### Create Order

```http
POST /api/v1/orders
```

Request:

```json
{
  "customer": {
    "name": "Jane Customer",
    "email": "customer@example.com",
    "phone": "+201234567890"
  },
  "currency": "USD",
  "items": [
    {
      "product_name": "Keyboard",
      "quantity": 2,
      "price": 50.25
    },
    {
      "product_name": "Mouse",
      "quantity": 1,
      "price": 20
    }
  ]
}
```

Success response `201`:

```json
{
  "data": {
    "id": 1,
    "customer_name": "Jane Customer",
    "customer_email": "customer@example.com",
    "customer_phone": "+201234567890",
    "status": "pending",
    "total_amount": "120.50",
    "currency": "USD",
    "items": []
  }
}
```

### List Orders

```http
GET /api/v1/orders?status=pending&page=1&per_page=15
```

Supported statuses:

```text
pending
confirmed
cancelled
```

### Show Order

```http
GET /api/v1/orders/{order}
```

### Update Order

```http
PUT /api/v1/orders/{order}
```

Only pending orders can be modified. Confirmed or cancelled order update response `409`:

```json
{
  "message": "Only pending orders can be modified."
}
```

### Update Order Status

```http
PATCH /api/v1/orders/{order}/status
```

Request:

```json
{
  "status": "confirmed"
}
```

### Delete Order

```http
DELETE /api/v1/orders/{order}
```

Orders with payments cannot be deleted. Conflict response `409`:

```json
{
  "message": "Orders with associated payments cannot be deleted."
}
```

## Payments API

### Process Payment

```http
POST /api/v1/orders/{order}/payments
```

Request:

```json
{
  "method": "credit_card",
  "metadata": {
    "card_last_four": "4242"
  }
}
```

Success response `201`:

```json
{
  "data": {
    "id": 1,
    "payment_id": "a-public-uuid",
    "order_id": 1,
    "status": "successful",
    "method": "credit_card",
    "amount": "120.50",
    "currency": "USD",
    "gateway_reference": "cc_gateway-reference",
    "failure_reason": null,
    "metadata": {
      "card_last_four": "4242"
    },
    "processed_at": "2026-06-25T19:00:00.000000Z"
  }
}
```

Failed simulated gateway response `201`:

```json
{
  "data": {
    "status": "failed",
    "method": "failing_gateway",
    "failure_reason": "Simulated gateway failure."
  }
}
```

Pending order payment response `409`:

```json
{
  "message": "Payments can only be processed for confirmed orders."
}
```

Unsupported method validation response `422`:

```json
{
  "message": "The selected method is invalid.",
  "errors": {
    "method": [
      "The selected method is invalid."
    ]
  }
}
```

### List Payments

```http
GET /api/v1/payments?order_id=1&status=successful&method=credit_card&page=1&per_page=15
```

### List Payments For Order

```http
GET /api/v1/orders/{order}/payments
```

### Show Payment

```http
GET /api/v1/payments/{payment}
```

## Business Rules

- New orders start as `pending`.
- Order totals are always calculated by the server.
- Order items require product name, positive quantity, and positive price.
- Only pending orders can be modified.
- Payments can only be processed for confirmed orders.
- Failed payment attempts are stored for auditability.
- Orders with associated payments cannot be deleted.
- Authenticated users can only access their own orders and payments.

## Payment Gateway Extensibility

Payment processing is driven by `PaymentGatewayInterface`:

```php
use App\DTOs\Payments\PaymentRequestData;
use App\DTOs\Payments\PaymentResultData;

interface PaymentGatewayInterface
{
    public function process(PaymentRequestData $paymentRequest): PaymentResultData;
}
```

To add a new gateway:

1. Create a class under `app/Services/Payments/Gateways`.
2. Implement `PaymentGatewayInterface`.
3. Return a `PaymentResultData` with `PaymentStatus::Successful` or `PaymentStatus::Failed`.
4. Register the class in `config/payment-gateways.php`.
5. Add the required `.env.example` variables.
6. Add tests for the gateway behavior.

Example config entry:

```php
'new_gateway' => [
    'driver' => NewGateway::class,
    'enabled' => env('NEW_GATEWAY_ENABLED', true),
    'api_key' => env('NEW_GATEWAY_API_KEY'),
],
```

Tradeoff: configuration-based registration is simple, testable, and safe for secrets. A database-backed gateway registry would be more dynamic, but it would add operational and security complexity that is not needed for this scope.

## Database Overview

Core tables:

- `users`
- `orders`
- `order_items`
- `payments`

Relationships:

- User has many orders.
- Order belongs to user.
- Order has many order items.
- Order has many payments.
- Payment belongs to order.

Money fields use `decimal(12, 2)`, not floating point database columns.

## Postman Collection

Import:

```text
docs/postman/order-payment-management-api.postman_collection.json
```

Collection variables:

- `base_url`: defaults to `http://127.0.0.1:8000`
- `jwt_token`: set automatically by the Login request script when the response contains a token
- `order_id`: set manually or from create order response if desired
- `payment_id`: set manually after creating a payment

## Testing

```bash
php artisan test
```

Current coverage includes:

- Auth feature tests.
- Order feature tests.
- Payment feature tests.
- Payment gateway resolver unit tests.

## Notes and Assumptions

- Payments are simulated; no real provider SDK is called.
- Partial payments, refunds, webhooks, and admin roles are out of scope.
- Multiple failed payment attempts are allowed.
- The API is user-scoped: users cannot access other users' orders or payments.
- Gateway credentials belong in `.env`, not source control.

## Future Improvements

- Add idempotency keys for payment processing.
- Add refunds.
- Add webhook handling for real providers.
- Add admin roles and policies.
- Add OpenAPI generation if required by consumers.
