<?php
class Product extends Model
{
    public function __construct()
    {
        parent::__construct('products');
    }

    public function findAllWithCategory()
    {
        $query = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCategory($categoryId)
    {
        $query = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.category_id = :category_id 
                 ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function search($term)
    {
        $query = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.name LIKE :term OR p.description LIKE :term 
                 ORDER BY p.created_at DESC";
        $stmt = $this->db->prepare($query);
        $searchTerm = "%$term%";
        $stmt->bindParam(':term', $searchTerm);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStock($productId, $newStock)
    {
        return $this->update($productId, ['stock' => $newStock]);
    }
}
