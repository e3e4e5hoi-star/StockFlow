<?php
declare(strict_types=1);
final class InventoryService {
    public function __construct(private PDO $db) {}
    public function products(): array { return $this->db->query('SELECT * FROM products ORDER BY id')->fetchAll(); }
    public function createOrder(string $email, array $items): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$items) throw new InvalidArgumentException('valid email and items are required');
        $this->db->beginTransaction();
        try {
            $total = 0; $resolved = [];
            foreach ($items as $item) {
                $stmt = $this->db->prepare('SELECT id, sku, name, stock, price_cents FROM products WHERE sku = ?'); $stmt->execute([$item['sku'] ?? '']); $product = $stmt->fetch();
                $qty = (int)($item['quantity'] ?? 0);
                if (!$product || $qty < 1 || (int)$product['stock'] < $qty) throw new DomainException('invalid product or insufficient stock');
                $total += (int)$product['price_cents'] * $qty; $resolved[] = [$product, $qty];
            }
            $stmt = $this->db->prepare('INSERT INTO orders(customer_email,status,total_cents) VALUES(?,\'PENDING\',?)'); $stmt->execute([$email, $total]); $orderId = (int)$this->db->lastInsertId();
            foreach ($resolved as [$product, $qty]) {
                $stmt = $this->db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'); $stmt->execute([$qty, $product['id'], $qty]);
                if ($stmt->rowCount() !== 1) throw new DomainException('stock changed during checkout');
                $stmt = $this->db->prepare('INSERT INTO order_items(order_id,product_id,quantity,unit_price_cents) VALUES(?,?,?,?)'); $stmt->execute([$orderId, $product['id'], $qty, $product['price_cents']]);
            }
            $payload = json_encode(['order_id'=>$orderId,'total_cents'=>$total], JSON_THROW_ON_ERROR); $stmt = $this->db->prepare('INSERT INTO events(order_id,event_type,payload) VALUES(?,\'ORDER_CREATED\',?)'); $stmt->execute([$orderId, $payload]);
            $this->db->commit(); return ['id'=>$orderId,'status'=>'PENDING','total_cents'=>$total];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
