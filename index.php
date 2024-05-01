<?php

use App\Handlers\LeadHandler;
use Symfony\Component\Dotenv\Dotenv;
use App\Factories\ApiClientFactory;
use App\Database\DatabaseConnector;

include_once __DIR__ . '/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');
$path = __DIR__ . '/.env';

$accessToken = $_ENV['AMO_ATOKEN'];
$basedomain = $_ENV['BASE_DOMAIN'];

$apiClient = ApiClientFactory::createWithToken($accessToken, $basedomain);
$dbConnector = new DatabaseConnector();

// Добавляем сделку
if (isset($_POST['leads']['add'][0])) {
    $leadHandler = new LeadHandler($apiClient, $dbConnector);
    $leadHandler->handleNewLead($_POST['leads']['add'][0]);
}

// Обновляем данные сделки
if (isset($_POST['leads']['update'][0])) {
    $leadHandler = new LeadHandler($apiClient, $dbConnector);
    $leadHandler->handleLeadUpdate($_POST['leads']['update'][0]);
}
