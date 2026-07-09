<?php

$router = new Router();

// Auth Controller
$router->add('login' , AuthController::class, 'login');
$router->add('logout', AuthController::class, 'logout', ['auth']);
$router->add('session', AuthController::class, 'session');

// User Controller
$router->add('users', UserController::class, 'index', ['auth']);
$router->add('create_user', UserController::class, 'create', ['auth']);
$router->add('update_user', UserController::class, 'update', ['auth']);
$router->add('delete_user', UserController::class, 'delete', ['auth']);
$router->add('get_initial', UserController::class, 'getInitial', ['auth']);

// Store Controller
$router->add('machines', StoreController::class, 'machines', ['auth']);
$router->add('create_machine', StoreController::class, 'createMachine', ['auth']);
$router->add('store_names', StoreController::class, 'storeName', ['auth']);

// Setting Controller
$router->add('theme', SettingController::class, 'changeTheme', ['auth']);
$router->add('test', SettingController::class, 'test');

// Product Controller
$router->add('products', ProductController::class, 'index', ['auth']);
$router->add('products_by_category', ProductController::class, 'getProductByCategory', ['auth']);
$router->add('create_product', ProductController::class, 'createProduct', ['auth']);
$router->add('update_product', ProductController::class, 'updateProduct', ['auth']);
$router->add('delete_product', ProductController::class, 'deleteProduct', ['auth']);
$router->add('pagination_products', ProductController::class, 'getProductByPagination', ['auth']);
// Finishing 
$router->add('finishings', ProductController::class, 'getFinishing', ['auth']);
$router->add('finishing_by_category', ProductController::class, 'getFinishingByCategory', ['auth']);
$router->add('create_finishing', ProductController::class, 'createFinishing', ['auth']);
$router->add('update_finishing', ProductController::class, 'updateFinishing', ['auth']);
$router->add('delete_finishing', ProductController::class, 'deleteFinishing', ['auth']);
// Category
$router->add('categories', ProductController::class, 'getCategory', ['auth']);

// Order Controller
$router->add('get_orders', OrderController::class, 'index', ['auth']);
$router->add('create_order', OrderController::class, 'create', ['auth']);
$router->add('update_order', OrderController::class, 'update', ['auth']);
$router->add('delete_order', OrderController::class, 'delete', ['auth']);
// Order Item
$router->add('order_detail', OrderController::class, 'orderDetail', ['auth']);
$router->add('item_price', OrderController::class, 'fullPrice', ['auth']);
$router->add('create_order_item', OrderController::class, 'createItem', ['auth']);
$router->add('delete_item', OrderController::class, 'deleteItem', ['auth']);
$router->add('full_price_item', OrderController::class, 'fullPrice', ['auth']);
// Project
$router->add('update_project', OrderController::class, 'updateProject', ['auth']);
// Note
$router->add('update_customer_note', OrderController::class, 'createNote', ['auth']);

// Location Controller
$router->add('locations', LocationController::class, 'index', ['auth']);

// Payment Controller
$router->add('create_payment', PaymentController::class, 'create', ['auth']);
$router->add('update_payment', PaymentController::class, 'update', ['auth']);
$router->add('delete_payment', PaymentController::class, 'delete', ['auth']);

// Finance Controller
$router->add('finance', FinanceController::class, 'finance', ['auth']);
$router->add('create_income', FinanceController::class, 'createIncome', ['auth']);
$router->add('update_income', FinanceController::class, 'updateIncome', ['auth']);
$router->add('delete_income', FinanceController::class, 'deleteIncome', ['auth']);
$router->add('create_expenditure', FinanceController::class, 'createExpenditure', ['auth']);
$router->add('update_expenditure', FinanceController::class, 'updateExpenditure', ['auth']);
$router->add('delete_expenditure', FinanceController::class, 'deleteExpenditure', ['auth']);
$router->add('create_Tf', FinanceController::class, 'createTf', ['auth']);
$router->add('delete_Tf', FinanceController::class, 'deleteTf', ['auth']);
$router->add('synn_finance', FinanceController::class, 'syncFinanceInterval', ['auth']);

// Report Controller
$router->add('all_detail_order', ReportController::class, 'allDetailOrderByIntervalDate', ['auth']);
$router->add('piutang', ReportController::class, 'piutang', ['auth']);
$router->add('transactions_capture', ReportController::class, 'transactionsCapture', ['auth']);


return $router;