<?php
class Order extends Model
{
    public function __construct()
    {
        parent::__construct('orders');
    }

    public function createOrder($userId, $items, $total, $status = 'pending')
    {
        $this->db->beginTransaction();

        try {
            // Criar ordem
            $orderId = $this->create([
                'user_id' => $userId,
                'total' => $total,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$orderId) {
                throw new Exception('Falha ao criar pedido');
            }

            // Criar itens da ordem
            $orderItem = new OrderItem();
            foreach ($items as $item) {
                $success = $orderItem->create([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                if (!$success) {
                    throw new Exception('Falha ao criar item do pedido');
                }

                // Atualizar estoque
                $productModel = new Product();
                $product = $productModel->findById($item['product_id']);
                if ($product) {
                    $newStock = $product['stock'] - $item['quantity'];
                    $productModel->updateStock($item['product_id'], $newStock);
                }
            }

            $this->db->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findByUser($userId)
    {
        $query = "SELECT o.*, 
                 COUNT(oi.id) as items_count 
                 FROM orders o 
                 LEFT JOIN order_items oi ON o.id = oi.order_id 
                 WHERE o.user_id = :user_id 
                 GROUP BY o.id 
                 ORDER BY o.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOrderWithItems($orderId)
    {
        $order = $this->findById($orderId);
        if (!$order) return null;

        $query = "SELECT oi.*, p.name as product_name, p.image_url 
                 FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = :order_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $order;
    }

    public function updateStatus($orderId, $status)
    {
        return $this->update($orderId, ['status' => $status]);
    }
}
