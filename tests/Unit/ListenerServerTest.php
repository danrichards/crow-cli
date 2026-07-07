<?php

namespace Tests\Unit;

use App\Support\ListenerServer;
use ReflectionClass;
use Tests\TestCase;

class ListenerServerTest extends TestCase
{
    public function test_signature_validation_accepts_valid_hmac_and_rejects_invalid_hmac(): void
    {
        $server = new ListenerServer();
        $method = (new ReflectionClass($server))->getMethod('isValidSignature');
        $method->setAccessible(true);

        $body = '{"id":"evt_123"}';
        $timestamp = '123456';
        $secret = 'secret';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->assertTrue($method->invoke($server, [
            'headers' => [
                'x-crow-timestamp' => $timestamp,
                'x-crow-signature' => $signature,
            ],
            'body' => $body,
        ], $secret));

        $this->assertFalse($method->invoke($server, [
            'headers' => [
                'x-crow-timestamp' => $timestamp,
                'x-crow-signature' => 'bad',
            ],
            'body' => $body,
        ], $secret));
    }
}
