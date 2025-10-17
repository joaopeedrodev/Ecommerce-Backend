<?php
class OrderController extends Controller
{
    private $orderModel;
    private $auth;

    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new Order();
        $this->auth = new AuthMiddleware();
    }

    public function index()
    {
        try {
            $userData = $this->auth->authenticate();
            $orders = $this->orderModel->findByUser($userData->id);

            $this->jsonResponse([
                'success' => true,
                'data' => $orders
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao buscar pedidos: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store()
    {
        try {
            $userData = $this->auth->authenticate();
            $data = $this->getRequestData();

            if (!$data) {
                $this->jsonResponse(['success' => false, 'error' => 'Dados JSON inválidos'], 400);
            }

            $error = $this->validateRequired($data, ['items']);
            if ($error) {
                $this->jsonResponse(['success' => false, 'error' => $error], 400);
            }

            if (!is_array($data['items']) || empty($data['items'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Itens do pedido são obrigatórios'], 400);
            }

            $productModel = new Product();
            $total = 0;
            $validatedItems = [];

            foreach ($data['items'] as $item) {
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    $this->jsonResponse(['success' => false, 'error' => 'Cada item deve ter product_id e quantity'], 400);
                }

                $product = $productModel->findById($item['product_id']);
                if (!$product) {
                    $this->jsonResponse(['success' => false, 'error' => "Produto {$item['product_id']} não encontrado"], 400);
                }

                if ($product['stock'] < $item['quantity']) {
                    $this->jsonResponse(['success' => false, 'error' => "Estoque insuficiente para {$product['name']}"], 400);
                }

                $itemTotal = $product['price'] * $item['quantity'];
                $total += $itemTotal;

                $validatedItems[] = [
                    'product_id' => (int)$item['product_id'],
                    'quantity' => (int)$item['quantity'],
                    'price' => (float)$product['price']
                ];
            }

            $orderId = $this->orderModel->createOrder($userData->id, $validatedItems, $total);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Pedido criado com sucesso',
                'order_id' => (int)$orderId,
                'total' => (float)$total
            ], 201);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao criar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $userData = $this->auth->authenticate();

            $order = $this->orderModel->getOrderWithItems($id);

            if (!$order) {
                $this->jsonResponse(['success' => false, 'error' => 'Pedido não encontrado'], 404);
            }

            if ($order['user_id'] != $userData->id && $userData->role !== 'admin') {
                $this->jsonResponse(['success' => false, 'error' => 'Acesso negado'], 403);
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $order
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao buscar pedido: ' . $e->getMessage()
            ], 500);
        }
    }
}
