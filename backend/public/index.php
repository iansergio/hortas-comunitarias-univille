<?php

// ================= CORS Headers =================
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'https://hortas-comunitarias-univille.up.railway.app'
];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
// =================================================

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Middlewares\FormatadorDeErrosMiddleware;
use App\Middlewares\ForcarJsonMiddleware;
use App\Middlewares\JwtMiddleware;
use App\Middlewares\RoutePermissionMiddleware;

// --------------- Carregando .env
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

foreach ($_SERVER as $key => $value) {
    if (getenv($key) !== false && !isset($_ENV[$key])) {
        $_ENV[$key] = $value;
    }
}

// --------------- Criando container e dependências
$containerBuilder = new ContainerBuilder();

if (false) {
    $containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

$dependencies = require __DIR__ . '/../config/dependencies.php';
$dependencies($containerBuilder);

$authDependencies = require __DIR__ . '/../config/auth.php';
$authDependencies($containerBuilder);

$container = $containerBuilder->build();
$container->get(Capsule::class);

// --------------- Criando app Slim
AppFactory::setContainer($container);
$app = AppFactory::create();

// --------------- Carregando rotas da API
$routes = require __DIR__ . '/../src/Routes/IndexRoutes.php';
$routes($app);

// --------------- Middlewares
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add(ForcarJsonMiddleware::class);
$app->add(FormatadorDeErrosMiddleware::class);

// Sistema de permissões desabilitado temporariamente
// $app->add(RoutePermissionMiddleware::class);

$app->add(JwtMiddleware::class);

// --------------- Rodando app
$app->run();