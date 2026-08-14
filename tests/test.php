<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Database.php'; require_once __DIR__ . '/../src/InventoryService.php';
$dir = sys_get_temp_dir() . '/stockflow-' . bin2hex(random_bytes(4)); mkdir($dir); $db = new Database($dir . '/test.sqlite'); $db->initialize(__DIR__ . '/../sql/schema.sql'); $service = new InventoryService($db->pdo());
$products = $service->products(); assert(count($products) >= 2);
$order = $service->createOrder('buyer@example.com', [['sku'=>'DEMO-001','quantity'=>2]]); assert($order['total_cents'] === 9800);
try { $service->createOrder('buyer@example.com', [['sku'=>'DEMO-001','quantity'=>9999]]); assert(false); } catch (DomainException) { }
assert((int)$db->pdo()->query("SELECT stock FROM products WHERE sku='DEMO-001'")->fetchColumn() === 23);
$newProduct = $service->createProduct('TEST-001', 'Test Item', 5, 1200); assert($newProduct['sku'] === 'TEST-001');
$order2 = $service->createOrder('buyer@example.com', [['sku'=>'TEST-001','quantity'=>2]]); assert($service->order($order2['id'])['status'] === 'PENDING');
$cancelled = $service->cancelOrder($order2['id']); assert($cancelled['status'] === 'CANCELLED'); assert((int)$db->pdo()->query("SELECT stock FROM products WHERE sku='TEST-001'")->fetchColumn() === 5);
assert($service->cancelOrder($order2['id'])['status'] === 'CANCELLED');
try { $service->createProduct('TEST-001', 'Duplicate', 1, 1); assert(false); } catch (DomainException) { }
echo "PHP StockFlow tests passed\n";
