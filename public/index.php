<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Database.php'; require_once __DIR__ . '/../src/InventoryService.php';
$db = new Database(__DIR__ . '/../var/stockflow.sqlite'); $db->initialize(__DIR__ . '/../sql/schema.sql'); $service = new InventoryService($db->pdo());
header('Content-Type: application/json; charset=utf-8');
try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET' && $path === '/health') { echo json_encode(['status'=>'ok','service'=>'stockflow']); exit; }
    if ($method === 'GET' && $path === '/products') { echo json_encode(['products'=>$service->products()]); exit; }
    if ($method === 'POST' && $path === '/products') { $body = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR); http_response_code(201); echo json_encode(['product'=>$service->createProduct((string)($body['sku'] ?? ''), (string)($body['name'] ?? ''), (int)($body['stock'] ?? -1), (int)($body['price_cents'] ?? -1))]); exit; }
    if ($method === 'GET' && preg_match('#^/orders/(\\d+)$#', $path, $match)) { echo json_encode(['order'=>$service->order((int)$match[1])]); exit; }
    if ($method === 'POST' && preg_match('#^/orders/(\\d+)/cancel$#', $path, $match)) { echo json_encode(['order'=>$service->cancelOrder((int)$match[1])]); exit; }
    if ($method === 'POST' && $path === '/orders') { $body = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR); http_response_code(201); echo json_encode(['order'=>$service->createOrder((string)($body['customer_email'] ?? ''), (array)($body['items'] ?? []))]); exit; }
    http_response_code(404); echo json_encode(['error'=>'not found']);
} catch (Throwable $e) { http_response_code($e instanceof InvalidArgumentException || $e instanceof DomainException || $e instanceof JsonException ? 400 : 500); echo json_encode(['error'=>$e->getMessage()]); }
