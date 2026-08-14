<?php
declare(strict_types=1);
final class InventoryService {
    public function __construct(private PDO $db) {}
    public function products(): array { return $this->db->query('SELECT * FROM products ORDER BY id')->fetchAll(); }
    public function createProduct(string $sku, string $name, int $stock, int $priceCents): array {
        if ($sku === '' || $name === '' || $stock < 0 || $priceCents < 0) throw new InvalidArgumentException('invalid product fields');
        try { $stmt = $this->db->prepare('INSERT INTO products(sku,name,stock,price_cents) VALUES(?,?,?,?)'); $stmt->execute([$sku, $name, $stock, $priceCents]); }
        catch (PDOException $e) { if ((int)$e->errorInfo[1] === 19) throw new DomainException('SKU already exists'); throw $e; }
        return ['id'=>(int)$this->db->lastInsertId(), 'sku'=>$sku, 'name'=>$name, 'stock'=>$stock, 'price_cents'=>$priceCents];
    }
    public function order(int $id): array {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE id = ?'); $stmt->execute([$id]); $order = $stmt->fetch();
        if (!$order) throw new DomainException('order not found');
        $stmt = $this->db->prepare('SELECT oi.*, p.sku, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?'); $stmt->execute([$id]);
        $order['items'] = $stmt->fetchAll(); return $order;
    }
    public function cancelOrder(int $id): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status FROM orders WHERE id = ?'); $stmt->execute([$id]); $order = $stmt->fetch();
            if (!$order) throw new DomainException('order not found');
            if ($order['status'] === 'CANCELLED') { $this->db->commit(); return $this->order($id); }
            if ($order['status'] !== 'PENDING') throw new DomainException('only pending orders can be cancelled');
            $stmt = $this->db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?'); $stmt->execute([$id]);
            foreach ($stmt->fetchAll() as $item) { $restore = $this->db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?'); $restore->execute([$item['quantity'], $item['product_id']]); }
            $stmt = $this->db->prepare("UPDATE orders SET status = 'CANCELLED' WHERE id = ? AND status = 'PENDING'"); $stmt->execute([$id]);
            $event = $this->db->prepare('INSERT INTO events(order_id,event_type,payload) VALUES(?,\'ORDER_CANCELLED\',?)'); $event->execute([$id, json_encode(['order_id'=>$id], JSON_THROW_ON_ERROR)]);
            $this->db->commit(); return $this->order($id);
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function createOrder(string $email, array $items): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$items) throw new InvalidArgumentException('valid email and items are required');
        $this->db->beginTransaction();
        try {
            $total = 0; $resolved = [];
            foreach ($items as $item) {
                if (!is_array($item)) throw new InvalidArgumentException('each item must be an object');
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
