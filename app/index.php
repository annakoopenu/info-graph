<?php

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────
require_once __DIR__ . '/src/config.php';

loadEnv(__DIR__ . '/.env');

require_once __DIR__ . '/src/errors.php';
initErrorHandling();

session_start();

require_once __DIR__ . '/src/Router.php';
require_once __DIR__ . '/src/Controllers/ItemController.php';
require_once __DIR__ . '/src/Controllers/CsvController.php';
require_once __DIR__ . '/src/Controllers/McpController.php';
require_once __DIR__ . '/src/Controllers/ApiController.php';

// ── Routes ───────────────────────────────────────────────────────
$router = new Router();

$ctrl    = new ItemController();
$csvCtrl = new CsvController();
$mcpCtrl = new McpController();
$apiCtrl = new ApiController();

$router->get('/',                fn($p) => (header('Location: ' . url('items')) && exit));
$router->get('/items',           fn($p) => $ctrl->index($p));
$router->get('/items/new',       fn($p) => $ctrl->create($p));
$router->post('/items',          fn($p) => $ctrl->store($p));
$router->get('/items/{id}',      fn($p) => $ctrl->show($p));
$router->get('/items/{id}/edit', fn($p) => $ctrl->edit($p));
$router->post('/items/{id}',     fn($p) => $ctrl->update($p));
$router->post('/items/{id}/replace-image', fn($p) => $ctrl->replaceImage($p));
$router->post('/items/{id}/delete', fn($p) => $ctrl->destroy($p));

$router->get('/export',          fn($p) => $csvCtrl->export($p));
$router->get('/export/json',     fn($p) => $csvCtrl->exportJson($p));
$router->get('/import',          fn($p) => $csvCtrl->importForm($p));
$router->post('/import',         fn($p) => $csvCtrl->importUpload($p));
$router->post('/import/confirm', fn($p) => $csvCtrl->importConfirm($p));

$router->get('/mcp/tools',       fn($p) => $mcpCtrl->listTools($p));
$router->post('/mcp/call',       fn($p) => $mcpCtrl->call($p));

$router->get('/api/items',            fn($p) => $apiCtrl->listItems($p));
$router->post('/api/items',           fn($p) => $apiCtrl->createItem($p));
$router->get('/api/items/{id}',       fn($p) => $apiCtrl->getItem($p));
$router->patch('/api/items/{id}',     fn($p) => $apiCtrl->updateItem($p));
$router->post('/api/items/{id}',      fn($p) => $apiCtrl->updateItem($p));
$router->delete('/api/items/{id}',    fn($p) => $apiCtrl->deleteItem($p));
$router->post('/api/items/{id}/delete', fn($p) => $apiCtrl->deleteItem($p));

// ── Dispatch ─────────────────────────────────────────────────────
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
