<?php

namespace App\Factories;

use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\InvalidArgumentException;

class ApiClientFactory
{
    /**
     * Создаем инстанс AmoCRMApiClient.
     *
     * @param string $accessToken
     * @param string $baseDomain
     * @return AmoCRMApiClient
     * @throws AmoCRMApiException
     */
    public static function createWithToken(string $accessToken, string $baseDomain): AmoCRMApiClient
    {
        $apiClient = new AmoCRMApiClient();

        try {
            $longLivedAccessToken = new LongLivedAccessToken($accessToken);
        } catch (InvalidArgumentException $e) {
            throw new AmoCRMApiException('Ошибка создания токена: ' . $e->getMessage());
        }

        $apiClient->setAccessToken($longLivedAccessToken)
            ->setAccountBaseDomain($baseDomain);

        return $apiClient;
    }
}
