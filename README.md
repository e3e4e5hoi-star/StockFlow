# StockFlow

StockFlow is a practical inventory and order-management system built with a PHP-first architecture. PHP owns the HTTP API and business workflow, SQL owns transactional persistence and constraints, and a small Java worker demonstrates idempotent order-event processing.

## Architecture

| Layer | Technology | Responsibility |
|---|---|---|
| Application | PHP 8.3 | Health endpoint, product listing, validation and checkout workflow |
| Persistence | SQLite SQL | Products, orders, order items, events, foreign keys and indexes |
| Worker | Java 21 | Idempotent processing of `ORDER_CREATED` events |

The intended implementation ratio is approximately **60% PHP, 25% SQL and 15% Java** by responsibility. The system is deliberately compact so it can be run locally without Docker or a cloud account.

## Run PHP API

```bash
mkdir -p var
php -S 127.0.0.1:8080 -t public
```

Health check:

```bash
curl http://127.0.0.1:8080/health
```

List products:

```bash
curl http://127.0.0.1:8080/products
```

Create an order:

```bash
curl -X POST http://127.0.0.1:8080/orders \
  -H 'Content-Type: application/json' \
  -d '{"customer_email":"buyer@example.com","items":[{"sku":"DEMO-001","quantity":2}]}'
```

Checkout is transactional. It validates the email and items, locks the workflow inside a SQLite transaction, decrements stock with a conditional update, inserts order items and records an event. If any operation fails, the transaction rolls back.

## Run tests

```bash
php tests/test.php
javac -d /tmp/stockflow-classes java/src/main/java/com/stockflow/OrderWorker.java
java -cp /tmp/stockflow-classes com.stockflow.OrderWorker
```

Expected Java output:

```text
ORDER_ACCEPTED
DUPLICATE
```

## Security and production notes

The sample API binds to localhost and has no login system, rate limiting or TLS termination. A production deployment must add authentication, authorization, CSRF protection for browser forms, structured logging, secrets management, migrations, a production database and an authenticated message transport between PHP and Java. The implementation does not claim to be production-ready without those controls.

## License

MIT License. See [LICENSE](LICENSE).
