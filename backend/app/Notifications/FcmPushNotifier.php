<?php

namespace App\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmPushNotifier implements PushNotifier
{
    public function send(string $token, array $message): bool
    {
        $credentialsPath = (string) config('services.fcm.credentials_path');
        if ($credentialsPath === '' || ! is_readable($credentialsPath)) {
            throw new RuntimeException('No se puede leer el archivo de credenciales FCM configurado.');
        }
        $credentials = json_decode((string) file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
        $accessToken = Cache::remember('panel:fcm:access-token', 3300, fn () => $this->accessToken($credentials));
        $response = Http::withToken($accessToken)->timeout(2)->post(
            'https://fcm.googleapis.com/v1/projects/'.rawurlencode($credentials['project_id']).'/messages:send',
            [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $message['title'], 'body' => $message['body']],
                    'data' => [
                        ...$message['data'],
                        'severity' => $message['severity'],
                    ],
                    'android' => ['notification' => ['channel_id' => 'alerts', 'priority' => 'HIGH']],
                ],
            ]
        );

        if ($response->successful()) {
            return true;
        }
        if (str_contains($response->body(), 'UNREGISTERED') || str_contains($response->body(), 'registration-token-not-registered')) {
            DB::table('devices')->where('fcm_token', $token)->delete();

            return false;
        }

        throw new RuntimeException('FCM rechazó la notificación (HTTP '.$response->status().').');
    }

    private function accessToken(array $credentials): string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        $key = openssl_pkey_get_private($credentials['private_key']);
        if (! $key || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar la solicitud OAuth de FCM.');
        }
        $assertion = $unsigned.'.'.$this->base64Url($signature);
        $response = Http::asForm()->timeout(2)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('No se pudo obtener el token OAuth para FCM.');
        }

        return (string) $response->json('access_token');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
