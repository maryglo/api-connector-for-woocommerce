=== StoreDispatcher - Outbound Integration for WooCommerce ===
Contributors: glowkazaf
Donate link: https://paypal.me/mgdelapedra
Tags: woocommerce, rest-api, api, integration, orders
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce orders to external REST APIs with configurable authentication, field mapping, retries, and request logs.

== Description ==

A lightweight, configurable plugin for connecting WooCommerce stores to external REST APIs.

The plugin sends order data when an order is created or completed. Store owners can configure the API connection, authentication, event triggers, and order field mappings in WordPress admin.

Configure the API connection, authentication, events, and field mappings directly from the WordPress admin.

---

== Features ==

=== WooCommerce Integration ===

- Connect WooCommerce to any REST API endpoint
- Support configurable API base URLs
- Bearer token authentication
- API key authentication
- Test API connectivity from the WordPress admin

=== Order Events ===

- Detect newly created WooCommerce orders
- Detect completed orders
- Process integration requests asynchronously
- Queue requests using Action Scheduler
- Automatic retry handling for failed requests

=== Flexible Field Mapping ===

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

=== Integration Logging ===

Track integration requests and their results, including:

- Request status
- HTTP response status
- Attempt count
- Error information
- Processing timestamps

Sensitive authentication credentials are not included in integration logs.

=== Testing Tools ===

Test the integration before sending real order data.

- Test API connection
- Generate a payload from an existing WooCommerce order
- Inspect the generated JSON
- Send test requests
- Review API responses

=== High-Performance Order Storage ===

The plugin is designed to work with WooCommerce High-Performance Order Storage (HPOS).

Orders are accessed through WooCommerce's order APIs rather than directly querying WordPress post tables.

---

== Architecture ==

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

== Installation ==

=== From GitHub ===

Clone the repository:

```bash
git clone https://github.com/maryglo/api-connector-for-woocommerce.git
```

The installable ZIP includes Composer's generated runtime autoloader; Composer is not required on the WordPress server. To build the ZIP from source, use PHP 8.1 or newer, run this from the repository root in PowerShell, then upload the ZIP under **Plugins → Add Plugin → Upload Plugin**:

```powershell
.\scripts\build-plugin.ps1
```

The ZIP is written to `build/storedispatcher-outbound-integration-for-woocommerce.zip`. It includes `composer.json`, `composer.lock`, and the generated `vendor/` autoloader. The icon and screenshots remain in the source `assets/` folder and are excluded from the ZIP; the admin CSS and JavaScript are included.

After installing the ZIP, activate it from:

```text
WordPress Admin
→ Plugins
→ Installed Plugins
→ StoreDispatcher - Outbound Integration for WooCommerce
```

== Requirements ==

- WordPress 6.4 or newer
- WooCommerce 11.1.0
- PHP 8.1 or newer
- PHP Sodium extension
- HTTPS recommended for production API communication

=== Compatibility ===

This plugin is currently developed and tested with:

- **WordPress:** 7.1
- **WooCommerce:** 11.1.0
- **PHP:** 8.1+
- **WooCommerce HPOS:** Supported

Test the plugin in a staging environment before deploying it to a production store.

== Configuration ==

After activating the plugin, open:

```text
WordPress Admin
→ WooCommerce
→ API Connector
```

Configure:

```text
API Connection
├── API Name
├── API Base URL
├── Health Path
├── Authentication method
├── API credentials
└── Connection test

Order Events
├── Order created
└── Order completed

Endpoints
└── Order endpoint

Field Mapping
├── Order fields
└── Line-item fields
```

Save the configuration and use the built-in connection test to verify communication with the external API.

=== Required encryption key ===

Before saving API credentials, configure a dedicated encryption key in `wp-config.php`.

The plugin uses this key to encrypt API credentials before storing them in the WordPress database.

Add:

```php
define('WCAC_ENCRYPTION_KEY', 'YOUR_BASE64_ENCODED_32_BYTE_KEY');
```

Generate a secure 32-byte key with PHP:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Copy the generated value into `wp-config.php`:

```php
define('WCAC_ENCRYPTION_KEY', 'your-generated-base64-key');
```

Place the constant before:

```php
/* That's all, stop editing! Happy publishing. */
```

=== Docker environments ===

If WordPress is running in Docker, the key can be generated inside the WordPress container:

```bash
docker compose exec wordpress php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

The key can also be provided through the container environment.

For example, in `.env`:

```env
WCAC_ENCRYPTION_KEY=your-generated-base64-key
```

Then expose it to the WordPress container and load it from `wp-config.php`:

```php
if (getenv('WCAC_ENCRYPTION_KEY')) {
	define('WCAC_ENCRYPTION_KEY', getenv('WCAC_ENCRYPTION_KEY'));
}
```

Do not commit `.env` files containing the encryption key to source control.

=== Encryption key requirements ===

- The key must be a valid Base64-encoded 32-byte value.
- Keep the encryption key secret.
- Do not commit the encryption key to Git.
- Do not place the key in plugin source code.
- Do not use the external API credential as the encryption key.
- Keep the same encryption key for the lifetime of the encrypted credentials.
- Changing the encryption key will prevent previously encrypted credentials from being decrypted.
- Back up the encryption key securely when backing up a production WordPress installation.

After configuring the encryption key, enter the API credential in the API Connector settings and save the configuration.

---

== Authentication ==

The connector supports configurable API authentication.

=== Bearer Token ===

Example:

```http
Authorization: Bearer YOUR_TOKEN
```

=== API Key ===

Example:

```http
X-API-Key: YOUR_API_KEY
```

The API credential is encrypted before being stored in the WordPress database.

Credentials are not rendered back into the settings form after being saved. The credential field remains empty and displays a saved-credential indicator when a credential is configured.

When testing a connection, an administrator can enter a new credential and test it without first saving it.

=== Server-managed API credential ===

For high-security deployments, an API credential can also be provided through the `WCAC_API_KEY` constant in `wp-config.php`.

Example:

```php
define('WCAC_API_KEY', 'YOUR_API_CREDENTIAL');
```

When configured, this server-managed credential takes precedence over the credential stored by the plugin.

Keep the value outside source control and never commit production credentials to Git.

=== Encryption key vs API credential ===

These are two different secrets:

```text
WCAC_API_KEY
    │
    └── External API credential

WCAC_ENCRYPTION_KEY
    │
    └── Local encryption key used to protect
        credentials stored by the plugin
```

The encryption key does not authenticate requests to the external API.

**Never place production API credentials or encryption keys in GitHub, screenshots, example configuration files, support requests, or issue reports.**

---

== Order Processing ==

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

== Retry Handling ==

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

== Field Mapping ==

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

== Testing ==

The project includes automated tests for core components.

Run the test suite:

```bash
composer test
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
- Encrypted credential storage and retrieval
- Connection testing with a newly entered credential
- Connection testing with a previously saved credential

---

== Security ==

Security is an important part of the plugin architecture.

The project follows WordPress security practices including:

- Capability checks for administrative actions
- Nonce verification
- Input sanitization
- Output escaping
- WordPress HTTP APIs
- Authenticated encryption for stored API credentials
- Dedicated encryption key configuration
- Secure handling of API credentials
- Avoiding direct database access for WooCommerce orders
- Avoiding credentials in logs
- Validation of API configuration
- TLS certificate verification for outbound HTTP requests

API credentials stored by the plugin are encrypted before being persisted to the WordPress options table.

The plugin uses PHP Sodium authenticated encryption for credential protection.

The plugin does not require access to the external API's source code or server infrastructure.

---

== Project Structure ==

```text
api-connector-for-woocommerce/
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
├── assets/ (admin CSS and JavaScript, icon, and directory screenshots)
│
├── tests/
│
├── languages/
│
├── composer.json
├── composer.lock
├── readme.txt
├── uninstall.php
└── storedispatcher-outbound-integration-for-woocommerce.php
```

The project uses a modular architecture to keep WordPress-specific functionality separate from API communication and mapping logic.

---

== Current Scope ==

The current version focuses on the core WooCommerce → REST API integration workflow.

=== Currently supported ===

- WooCommerce order events
- Configurable API connection
- API authentication
- Encrypted API credential storage
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

=== Not implemented in this release ===

The current release sends order events only. Customer names, email addresses, and line items are available as fields within an order payload; standalone customer and product event delivery are not implemented.

Other possible future improvements include:

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

== Future AI Integration ==

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

== Roadmap ==

=== V1 ===

- [x] WooCommerce integration
- [x] REST API client
- [x] API authentication
- [x] Encrypted API credential storage
- [x] Order events
- [x] Field mapping
- [x] Nested payloads
- [x] Line-item mapping
- [x] Action Scheduler processing
- [x] Retry handling
- [x] Integration logging
- [x] Test connection
- [x] Test payload

=== V1.x ===

- [ ] Improved mapping UI
- [ ] Idempotency protection
- [ ] Additional API authentication methods
- [ ] Custom order metadata
- [ ] Improved integration monitoring
- [ ] Additional automated tests

=== V2+ ===

- [ ] Multiple integrations
- [ ] Advanced transformations
- [ ] Conditional mappings
- [ ] Scheduled synchronization
- [ ] Bidirectional synchronization
- [ ] Configuration import/export
- [ ] AI-assisted mapping

---

== Contributing ==

Contributions, bug reports, feature requests, and improvements are welcome.

Before submitting a pull request:

1. Follow the existing project structure.
2. Add or update tests where appropriate.
3. Run the test suite.
4. Run static analysis/linting where configured.
5. Ensure WordPress and WooCommerce compatibility is maintained.
6. Do not include API credentials or customer data.
7. Do not include encryption keys or other deployment secrets.

---

== Bug Reports ==

When reporting a problem, please include:

- WordPress version
- WooCommerce version
- PHP version
- Whether HPOS is enabled
- Plugin version
- Relevant error/log information
- Steps to reproduce the issue

**Never include API keys, encryption keys, passwords, customer information, or other sensitive data in an issue.**

---

== License ==

This project is licensed under the GPL-2.0-or-later license.

See `LICENSE` for details.

---

== Disclaimer ==

This plugin is provided as an integration framework for connecting WooCommerce stores with external REST APIs.

External API availability, authentication requirements, rate limits, payload requirements, and data processing are controlled by the destination service.

Always test an integration in a staging environment before using it with production orders.

---

== Project ==

If this project is useful to you, consider giving it a star on GitHub.

Issues and feature requests are welcome.

== Screenshots ==

1. Configure the API connection, authentication method, and order event settings.
2. Map order fields to nested API fields and line-item arrays.
3. Review the full order mapping table and save the field mappings.
4. Preview the JSON payload generated from the saved mapping.
5. Review integration logs, request statuses, and attempt counts.

== Frequently Asked Questions ==

= Does this send customer or product events? =

No. This release sends order-created and order-completed events. Customer details and products in an order can be included in its mapped payload, but separate customer and product synchronization are not implemented.

= Can this connect to any REST API? =

The plugin can send order data to REST APIs that accept JSON requests. Configure the API URL, authentication, order endpoint, and field mappings to match the destination API.

= Can I create nested objects and line-item arrays? =

Yes. Use dot paths such as `customer.email` for nested objects and `items[].sku` for line-item arrays.

= Does the mapping preview use unsaved edits? =

No. Preview and test send use the saved mapping. Save the mapping table before previewing if you want to test recent changes.

= How are API credentials stored? =

Credentials are encrypted in the WordPress database using PHP Sodium. Configure `WCAC_ENCRYPTION_KEY` before saving credentials, and keep a secure backup of that key.

== Changelog ==

= 0.1.1 =

* Added configurable REST API connections and authentication.
* Added order events, field mapping, asynchronous delivery, retries, and integration logs.
* Added connection and payload testing tools.

== Upgrade Notice ==

= 0.1.1 =

Configure `WCAC_ENCRYPTION_KEY` before saving API credentials.
