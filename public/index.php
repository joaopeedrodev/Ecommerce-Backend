<?php
// ==================== CONFIGURAÇÕES ====================

// Define o caminho base absoluto CORRETO
define('BASE_DIR', dirname(__DIR__));

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=UTF-8');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==================== FUNÇÃO DE RESPOSTA ====================
function sendJsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ==================== VERIFICAR E CARREGAR ARQUIVOS ====================
function loadFile($relativePath, $fileName)
{
    $fullPath = BASE_DIR . $relativePath;

    if (!file_exists($fullPath)) {
        sendJsonResponse([
            'success' => false,
            'error' => "Arquivo não encontrado: $fileName",
            'expected_path' => $fullPath,
            'current_dir' => __DIR__
        ], 500);
    }

    require_once $fullPath;
    return true;
}

// ==================== CARREGAR CONFIGURAÇÕES ====================
try {
    loadFile('/config/config.php', 'config.php');
    loadFile('/config/database.php', 'database.php');
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Erro ao carregar configurações: ' . $e->getMessage()
    ], 500);
}

// ==================== CARREGAR DEPENDÊNCIAS ====================
try {
    // Verificar se vendor existe
    $vendorPath = BASE_DIR . '/vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        // Se não existir, usar fallback manual
        loadFile('/utils/FirebaseFallback.php', 'FirebaseFallback.php');
    } else {
        require_once $vendorPath;
    }

    // Lista de arquivos para carregar
    $filesToLoad = [
        // Core
        '/app/core/Controller.php',
        '/app/core/Model.php',

        // Utils e Middleware
        '/utils/JWTHandler.php',
        '/middleware/AuthMiddleware.php',

        // Models
        '/app/models/User.php',
        '/app/models/Product.php',
        '/app/models/Category.php',
        '/app/models/Order.php',
        '/app/models/OrderItem.php',

        // Controllers
        '/app/controllers/AuthController.php',
        '/app/controllers/ProductController.php',
        '/app/controllers/CategoryController.php',
        '/app/controllers/OrderController.php'
    ];

    // Carregar todos os arquivos
    foreach ($filesToLoad as $filePath) {
        $fileName = basename($filePath);
        loadFile($filePath, $fileName);
    }
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Erro ao carregar dependências: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

// ==================== ROTEAMENTO ====================
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Log para debug (remova em produção)
error_log("Request: $method $path");

try {
    // ==================== ROTAS PÚBLICAS ====================

    // Rota: GET / - Informações da API
    if ($method === 'GET' && $path === '/') {
        sendJsonResponse([
            'success' => true,
            'message' => '🚀 API E-commerce - Sistema Completo',
            'version' => '3.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'GET /' => 'Informações da API',
                'POST /auth/register' => 'Registrar usuário',
                'POST /auth/login' => 'Login de usuário',
                'GET /products' => 'Listar produtos',
                'GET /categories' => 'Listar categorias',
                'GET /auth/profile' => 'Perfil do usuário (auth)',
                'GET /orders' => 'Listar pedidos (auth)',
                'POST /orders' => 'Criar pedido (auth)'
            ]
        ]);
    }

    // Rota: POST /auth/register - Registrar usuário
    elseif ($method === 'POST' && $path === '/auth/register') {
        $controller = new AuthController();
        $controller->register();
    }

    // Rota: POST /auth/login - Login de usuário
    elseif ($method === 'POST' && $path === '/auth/login') {
        $controller = new AuthController();
        $controller->login();
    }

    // Rota: GET /auth/profile - Perfil do usuário (requer auth)
    elseif ($method === 'GET' && $path === '/auth/profile') {
        $controller = new AuthController();
        $controller->profile();
    }

    // ==================== ROTAS DE PRODUTOS ====================

    // Rota: GET /products - Listar produtos
    elseif ($method === 'GET' && $path === '/products') {
        $controller = new ProductController();
        $controller->index();
    }

    // Rota: POST /products - Criar produto (admin)
    elseif ($method === 'POST' && $path === '/products') {
        $controller = new ProductController();
        $controller->store();
    }

    // Rota: GET /products/{id} - Buscar produto por ID
    elseif ($method === 'GET' && preg_match('#^/products/(\d+)$#', $path, $matches)) {
        $controller = new ProductController();
        $controller->show($matches[1]);
    }

    // ==================== ROTAS DE CATEGORIAS ====================

    // Rota: GET /categories - Listar categorias
    elseif ($method === 'GET' && $path === '/categories') {
        $controller = new CategoryController();
        $controller->index();
    }

    // ==================== ROTAS DE PEDIDOS ====================

    // Rota: GET /orders - Listar pedidos do usuário (requer auth)
    elseif ($method === 'GET' && $path === '/orders') {
        $controller = new OrderController();
        $controller->index();
    }

    // Rota: POST /orders - Criar pedido (requer auth)
    elseif ($method === 'POST' && $path === '/orders') {
        $controller = new OrderController();
        $controller->store();
    }

    // Rota: GET /orders/{id} - Buscar pedido por ID (requer auth)
    elseif ($method === 'GET' && preg_match('#^/orders/(\d+)$#', $path, $matches)) {
        $controller = new OrderController();
        $controller->show($matches[1]);
    }

    // ==================== ROTA NÃO ENCONTRADA ====================
    else {
        sendJsonResponse([
            'success' => false,
            'error' => 'Rota não encontrada',
            'method' => $method,
            'path' => $path,
            'request_uri' => $requestUri,
            'available_routes' => [
                'GET /',
                'POST /auth/register',
                'POST /auth/login',
                'GET /products',
                'GET /categories',
                'GET /auth/profile',
                'GET /orders',
                'POST /orders'
            ]
        ], 404);
    }
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}
