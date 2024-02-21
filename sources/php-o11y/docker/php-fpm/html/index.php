<?php

use Monolog\Logger;
use OpenTelemetry\API\Globals;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LogLevel;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

require('dice.php');

$loggerProvider = Globals::loggerProvider();
$handler = new Handler(
    $loggerProvider,
    LogLevel::INFO
);
$monolog = new Logger('otel-php-monolog', [$handler]);

$app = AppFactory::create();

$dice = new Dice();

$app->get('/', function (Request $request, Response $response) {
    $data = array('response' => 'Hello world!');
    $payload = json_encode($data);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/rolldice', function (Request $request, Response $response) use ($monolog, $dice) {
    $params = $request->getQueryParams();
    if(isset($params['rolls'])) {
        $result = $dice->roll($params['rolls']);
        if (isset($params['player'])) {
          $monolog->info("A player is rolling the dice.", ['player' => $params['player'], 'result' => $result]);
        } else {
          $monolog->info("Anonymous player is rolling the dice.", ['result' => $result]);
        }
        $response->getBody()->write(json_encode($result));
    } else {
        $response->withStatus(400)->getBody()->write("Please enter a number of rolls");
    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();