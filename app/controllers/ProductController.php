<?php
class ProductController extends Controller
{
    private $productModel;
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
        $this->auth = new AuthMiddleware();
    }

    public function index()
    {
        try {
            $categoryId = $_GET['category_id'] ?? null;
            $search = $_GET['search'] ?? null;

            if ($categoryId) {
                $products = $this->productModel->findByCategory($categoryId);
            } elseif ($search) {
                $products = $this->productModel->search($search);
            } else {
                $products = $this->productModel->findAllWithCategory();
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao buscar produtos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = $this->productModel->findById($id);

            if ($product) {
                $this->jsonResponse([
                    'success' => true,
                    'data' => $product
                ]);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Produto não encontrado'], 404);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao buscar produto: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store()
    {
        try {
            $userData = $this->auth->authenticate();

            if ($userData->role !== 'admin') {
                $this->jsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
            }

            $data = $this->getRequestData();

            if (!$data) {
                $this->jsonResponse(['success' => false, 'error' => 'Dados JSON inválidos'], 400);
            }

            $error = $this->validateRequired($data, ['name', 'price', 'description']);
            if ($error) {
                $this->jsonResponse(['success' => false, 'error' => $error], 400);
            }

            $productData = [
                'name' => trim($data['name']),
                'description' => trim($data['description']),
                'price' => (float)$data['price'],
                'category_id' => isset($data['category_id']) ? (int)$data['category_id'] : null,
                'stock' => isset($data['stock']) ? (int)$data['stock'] : 0,
                'image_url' => $data['image_url'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $productId = $this->productModel->create($productData);

            if ($productId) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Produto criado com sucesso',
                    'product_id' => (int)$productId
                ], 201);
            } else {
                $this->jsonResponse(['success' => false, 'error' => 'Erro ao criar produto'], 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao criar produto: ' . $e->getMessage()
            ], 500);
        }
    }
}