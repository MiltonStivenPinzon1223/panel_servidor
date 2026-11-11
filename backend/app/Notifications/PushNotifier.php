<?php

namespace App\Notifications;

interface PushNotifier
{
    /** @param array{title:string,body:string,severity:string,data:array<string,string>} $message */
    public function send(string $token, array $message): bool;
}
