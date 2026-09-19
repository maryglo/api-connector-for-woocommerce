=== WooCommerce Integration Connector ===
Contributors: woocommerce-api-connector
Tags: woocommerce, rest-api, integration, orders
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

# WooCommerce Integration Connector

A lightweight, configurable WooCommerce plugin for connecting WooCommerce stores to external REST APIs.

The plugin allows store owners and developers to send WooCommerce order data to third-party APIs without hard-coding a custom integration for every SaaS platform.

Configure the API connection, authentication, events, and field mappings directly from the WordPress admin.

---

## ✨ Features

### WooCommerce Integration

- Connect WooCommerce to any REST API endpoint
- Support configurable API base URLs
- Bearer token authentication
- API key authentication
- Test API connectivity from the WordPress admin

### Order Events

- Detect newly created WooCommerce orders
- Detect completed orders
- Process integration requests asynchronously
- Queue requests using Action Scheduler
- Automatic retry handling for failed requests

### Flexible Field Mapping

Map WooCommerce order data to the structure required by your external API.

Example:

```text
WooCommerce Field              API Field
────────────────────────────────────────────
Order ID                       order_id
Order Number                   order.reference
Order Status                   order.status
Currency                       order.currency
Order Total                    order.total
Billing Email                  customer.email
Billing First Name             customer.first_name
Billing Last Name              customer.last_name
```

The mapping engine supports nested destination structures such as:

```text
customer.email
customer.address.city
order.shipping.postcode
```

Line items can also be mapped into arrays:

```text
items[].sku
items[].name
items[].quantity
items[].price
```

### Integration Logging

Track integration requests and their results, including:

- Request status
- HTTP response status
- Attempt count
- Error information
- Processing timestamps

Sensitive authentication credentials are not included in integration logs.

### Testing Tools

Test the integration before sending real order data.

- Test API connection
- Generate a payload from an existing WooCommerce order
- Inspect the generated JSON
- Send test requests
- Review API responses

### WooCommerce HPOS

The plugin is designed to work with WooCommerce High-Performance Order Storage (HPOS).

Orders are accessed through WooCommerce's order APIs rather than directly querying WordPress post tables.

---

## 🏗️ Architecture

The plugin follows a simple integration pipeline:

```text
                  WooCommerce
                       │
                       ▼
                 Order Event
                       │
                       ▼
              Integration Job
                       │
                       ▼
                Mapping Engine
                       │
             ┌─────────┴─────────┐
             │                   │
       Field Resolver       Transformer
             │                   │
             └─────────┬─────────┘
                       ▼
                 JSON Payload
                       │
                       ▼
                  API Client
                       │
                       ▼
              External REST API
                       │
                       ▼
               Integration Log
```

The integration is intentionally separated into independent components so the API client, mapping engine, event handling, and logging can evolve independently.

---

## 📦 Installation

### From GitHub

Clone the repository:

```bash
git clone https://github.com/maryglo/woocommerce-integration-connector.git
```

Install PHP dependencies:

```bash
composer install
```

Copy the plugin into your WordPress installation:

```text
wp-content/plugins/woocommerce-integration-connector/
```

Then activate it from:

```text
WordPress Admin
→ Plugins
→ Installed Plugins
→ WooCommerce Integration Connector
```

### Requirements

- WordPress
- WooCommerce
- PHP 8.1+
- WooCommerce HPOS-compatible environment

The exact supported WordPress and WooCommerce versions should be verified against the release being installed.

---

## ⚙️ Configuration

After activating the plugin, open the WooCommerce integration settings.

Configure:

```text
API Connection
├── API Base URL
├── Authentication method
├── API credentials
└── Connection test

Order Events
├── Order created
└── Order completed

Field Mapping
├── Order fields
├── Customer fields
└── Line-item fields
```

Save the configuration and use the built-in connection test to verify communication with the external API.

---

## 🔐 Authentication

The connector supports configurable API authentication.

### Bearer Token

Example:

```http
Authorization: Bearer YOUR_TOKEN
```

### API Key

Example:

```http
X-API-Key: YOUR_API_KEY
```

Authentication details are stored using WordPress settings APIs and should never be committed to source control.

**Never place production API credentials in GitHub, screenshots, example configuration files, or issue reports.**

---

## 🔄 Order Processing

When a configured WooCommerce event occurs, the plugin creates an asynchronous integration job.

```text
Order Event
    │
    ▼
Create Integration Job
    │
    ▼
Action Scheduler
    │
    ▼
Load WooCommerce Order
    │
    ▼
Apply Field Mapping
    │
    ▼
Generate JSON Payload
    │
    ▼
Send REST API Request
    │
    ├── Success
    │      ↓
    │   Log success
    │
    └── Failure
           ↓
        Retry
           ↓
        Log failure
```

Using asynchronous processing prevents the external API request from unnecessarily blocking the WooCommerce order lifecycle.

---

## 🔁 Retry Handling

Temporary API failures can occur because of:

- Network problems
- API timeouts
- Temporary server errors
- Rate limiting
- External service outages

The connector can retry failed integration jobs according to its configured retry strategy.

A typical flow is:

```text
Attempt 1
   │
   └── Failed
         ↓
       Retry
         ↓
Attempt 2
   │
   └── Failed
         ↓
       Retry
         ↓
Attempt 3
   │
   ├── Success
   │
   └── Failed → Mark as failed
```

---

## 🧩 Field Mapping

The mapping system separates WooCommerce data from the external API's payload structure.

For example, a WooCommerce order might contain:

```text
Order ID
Billing Email
Billing First Name
Billing Last Name
Order Total
Currency
Line Items
```

The external API may expect:

```json
{
  "externalOrderId": 12345,
  "customer": {
    "email": "customer@example.com",
    "firstName": "John",
    "lastName": "Doe"
  },
  "amount": 199.99,
  "currency": "USD",
  "items": []
}
```

The mapping configuration determines how the WooCommerce data is transformed into the destination structure.

This means the plugin can support different API payload structures without requiring custom PHP code for every integration.

---

## 🧪 Testing

The project includes automated tests for core components.

Run the test suite:

```bash
composer test
```

Run PHP syntax checks:

```bash
composer lint
```

Before deploying a new version, test the plugin against a real WooCommerce installation with:

- HPOS enabled
- HPOS disabled/legacy storage where applicable
- New orders
- Completed orders
- Successful API requests
- Authentication failures
- API validation errors
- Server errors
- Request timeouts
- Retry scenarios

---

## 🛡️ Security

Security is an important part of the plugin architecture.

The project follows WordPress security practices including:

- Capability checks for administrative actions
- Nonce verification
- Input sanitization
- Output escaping
- WordPress HTTP APIs
- Secure handling of API credentials
- Avoiding direct database access for WooCommerce orders
- Avoiding credentials in logs
- Validation of API configuration

The plugin does not require access to the external API's source code or server infrastructure.

---

## 🧱 Project Structure

```text
woocommerce-integration-connector/
│
├── src/
│   ├── Admin/
│   ├── Api/
│   ├── Mapping/
│   ├── Orders/
│   ├── Jobs/
│   ├── Logging/
│   ├── Settings/
│   └── Install/
│
├── assets/
│
├── tests/
│
├── languages/
│
├── composer.json
├── composer.lock
├── readme.txt
├── uninstall.php
└── woocommerce-integration-connector.php
```

The project uses a modular architecture to keep WordPress-specific functionality separate from API communication and mapping logic.

---

## 🚧 Current Scope

The current version focuses on the core WooCommerce → REST API integration workflow.

### Currently supported

- WooCommerce order events
- Configurable API connection
- API authentication
- Order field mapping
- Customer information mapping
- Line-item mapping
- Nested JSON structures
- Asynchronous processing
- Retry handling
- Integration logging
- Test connection
- Test payload generation
- HPOS compatibility

### Planned

The following features may be introduced in future releases:

- Product synchronization
- Customer events
- Custom WooCommerce metadata mapping
- Additional authentication methods
- Advanced field transformations
- Conditional mappings
- Multiple API integrations
- Scheduled synchronization
- Bidirectional synchronization
- Response mapping
- Import/export of integration configurations
- AI-assisted field mapping

---

## 🤖 Future AI Integration

AI is intentionally not required for the core integration process.

The planned AI functionality will assist administrators during configuration rather than running inside the production order-processing pipeline.

For example:

```text
External API Schema
        │
        ▼
AI Mapping Assistant
        │
        ▼
Suggested WooCommerce Mappings
        │
        ▼
Administrator Review
        │
        ▼
Saved Mapping
        │
        ▼
Deterministic Production Integration
```

This approach keeps production integrations predictable while using AI to reduce the time required to configure complex API mappings.

---

## 🗺️ Roadmap

### V1

- [x] WooCommerce integration
- [x] REST API client
- [x] API authentication
- [x] Order events
- [x] Field mapping
- [x] Nested payloads
- [x] Line-item mapping
- [x] Action Scheduler processing
- [x] Retry handling
- [x] Integration logging
- [x] Test connection
- [x] Test payload

### V1.x

- [ ] Improved mapping UI
- [ ] Idempotency protection
- [ ] Additional API authentication methods
- [ ] Custom order metadata
- [ ] Improved integration monitoring
- [ ] Additional automated tests

### V2+

- [ ] Product synchronization
- [ ] Customer synchronization
- [ ] Multiple integrations
- [ ] Advanced transformations
- [ ] Conditional mappings
- [ ] Scheduled synchronization
- [ ] Bidirectional synchronization
- [ ] Configuration import/export
- [ ] AI-assisted mapping

---

## 🤝 Contributing

Contributions, bug reports, feature requests, and improvements are welcome.

Before submitting a pull request:

1. Follow the existing project structure.
2. Add or update tests where appropriate.
3. Run the test suite.
4. Run static analysis/linting where configured.
5. Ensure WordPress and WooCommerce compatibility is maintained.
6. Do not include API credentials or customer data.

---

## 🐛 Bug Reports

When reporting a problem, please include:

- WordPress version
- WooCommerce version
- PHP version
- Whether HPOS is enabled
- Plugin version
- Relevant error/log information
- Steps to reproduce the issue

**Never include API keys, passwords, customer information, or other sensitive data in an issue.**

---

## 📄 License

This project is licensed under the GPL-2.0-or-later license.

See [LICENSE](LICENSE) for details.

---

## ⚠️ Disclaimer

This plugin is provided as an integration framework for connecting WooCommerce stores with external REST APIs.

External API availability, authentication requirements, rate limits, payload requirements, and data processing are controlled by the destination service.

Always test an integration in a staging environment before using it with production orders.

---

## ⭐ Project

If this project is useful to you, consider giving it a star on GitHub.

Issues and feature requests are welcome.