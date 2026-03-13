<?php

namespace MM\Meros\Services\Integrations;

use MM\Meros\Models\Integration;
use MM\Meros\Models\IntegrationConnection;

class AuthResolver {
    public function resolve(
        Integration $integration,
        IntegrationConnection $connection
    ): array {

        switch ($integration->auth_type) {

            case 'oauth':

                $token = $connection->token;

                if (!$token) {
                    return [];
                }

                return [
                    'Authorization' => 'Bearer ' . $token?->access_token
                ];


            case 'api_key':

                $credentials = $connection->credential?->credentials ?? [];

                if (!isset($credentials['api_key'])) {
                    return [];
                }

                return [
                    'Authorization' => 'Bearer ' . $credentials['api_key']
                ];


            case 'basic':

                $credentials = $connection->credential?->credentials ?? [];

                if (!isset($credentials['username']) || !isset($credentials['password'])) {
                    return [];
                }

                return [
                    'Authorization' =>
                        'Basic ' . base64_encode(
                            $credentials['username'] . ':' . $credentials['password']
                        )
                ];
        }

        return [];
    }
}