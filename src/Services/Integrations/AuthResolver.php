<?php 

namespace MM\Meros\Services\Integrations;

class AuthResolver {
    public function resolve($integration, $connection) {
        switch ($integration->auth_type) {

            case 'oauth':

                $token = $connection->token;

                return [
                    'Authorization' => 'Bearer ' . $token->access_token
                ];

            case 'api_key':

                $key = $connection->keys()->first();

                return [
                    'Authorization' => 'Bearer ' . $key->key
                ];

            case 'basic':

                $key = $connection->keys()->first();

                return [
                    'Authorization' =>
                        'Basic ' . base64_encode(
                            $key->key . ':' . $key->secret
                        )
                ];
        }

        return [];
    }
}