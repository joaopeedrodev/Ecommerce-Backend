<?php
class CategoryController extends Controller
{
    private $categoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->categoryModel = new Category();
    }

    public function index()
    {
        try {
            $withCount = isset($_GET['with_count']) && $_GET['with_count'] === 'true';

            if ($withCount) {
                $categories = $this->categoryModel->getWithProductCount();
            } else {
                $categories = $this->categoryModel->findAll();
            }

            $this->jsonResponse([
                'success' => true,
                'data' => $categories
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Erro ao buscar categorias: ' . $e->getMessage()
            ], 500);
        }
    }
}
