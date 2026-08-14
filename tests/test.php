<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Database.php'; require_once __DIR__ . '/../src/InventoryService.php';
$dir = sys_get_temp_dir() . '/stockflow-' . bin2hex(random_bytes(4)); mkdir($dir); $db = new Database($dir . '/test.sqlite'); $db->initialize(__DIR__ . '/../sql/schema.sql'); $service = new InventoryService($db->pdo());
$products = $service->products(); assert(count($products) >= 2);
$order = $service->createOrder('buyer@example.com', [['sku'=>'DEMO-001','quantity'=>2]]); assert($order['total_cents'] === 9800);
try { $service->createOrder('buyer@example.com', [['sku'=>'DEMO-001','quantity'=>9999]]); assert(false); } catch (DomainException) { }
assert((int)$db->pdo()->query("SELECT stock FROM products WHERE sku='DEMO-001'")->fetchColumn() === 23);
echo "PHP StockFlow tests passed\n";
