<?php

namespace Crow\Listen;

use RuntimeException;

class ListenerServer
{
    /**
     * @param  callable(array<string, mixed>): void  $onEvent
     */
    public function listen(string $host, int $port, string $secret, callable $onEvent): void
    {
        $socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (! $socket) {
            throw new RuntimeException("Unable to start listener on {$host}:{$port}: {$errstr} ({$errno})");
        }

        while ($client = @stream_socket_accept($socket, -1)) {
            $request = $this->readRequest($client);
            [$status, $body] = $this->handleRequest($request, $secret, $onEvent);
            $this->writeResponse($client, $status, $body);
            fclose($client);
        }

        fclose($socket);
    }

    /**
     * @return array{method: string, path: string, headers: array<string, string>, body: string}
     */
    private function readRequest($client): array
    {
        $requestLine = trim((string) fgets($client));
        [$method, $path] = array_pad(explode(' ', $requestLine, 3), 2, '');
        $headers = [];

        while (($line = fgets($client)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }

            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        $length = (int) ($headers['content-length'] ?? 0);
        $body = '';
        while (strlen($body) < $length && ! feof($client)) {
            $body .= (string) fread($client, $length - strlen($body));
        }

        return compact('method', 'path', 'headers', 'body');
    }

    /**
     * @param  array{method: string, path: string, headers: array<string, string>, body: string}  $request
     * @param  callable(array<string, mixed>): void  $onEvent
     * @return array{0: int, 1: string}
     */
    private function handleRequest(array $request, string $secret, callable $onEvent): array
    {
        if ($request['method'] === 'GET' && $request['path'] === '/health') {
            return [200, json_encode(['ok' => true]) ?: '{"ok":true}'];
        }

        if ($request['method'] !== 'POST' || $request['path'] !== '/crow/events') {
            return [404, json_encode(['error' => 'Not found']) ?: '{"error":"Not found"}'];
        }

        if (! $this->isValidSignature($request, $secret)) {
            return [401, json_encode(['error' => 'Invalid signature']) ?: '{"error":"Invalid signature"}'];
        }

        $event = json_decode($request['body'], true);
        if (! is_array($event)) {
            return [422, json_encode(['error' => 'Invalid JSON']) ?: '{"error":"Invalid JSON"}'];
        }

        $onEvent($event);

        return [202, json_encode(['ok' => true]) ?: '{"ok":true}'];
    }

    /**
     * @param  array{headers: array<string, string>, body: string}  $request
     */
    private function isValidSignature(array $request, string $secret): bool
    {
        $timestamp = $request['headers']['x-crow-timestamp'] ?? '';
        $signature = $request['headers']['x-crow-signature'] ?? '';

        if ($timestamp === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request['body'], $secret);

        return hash_equals($expected, $signature);
    }

    private function writeResponse($client, int $status, string $body): void
    {
        $messages = [
            200 => 'OK',
            202 => 'Accepted',
            401 => 'Unauthorized',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
        ];

        fwrite($client, "HTTP/1.1 {$status} ".($messages[$status] ?? 'Error')."\r\n");
        fwrite($client, "Content-Type: application/json\r\n");
        fwrite($client, 'Content-Length: '.strlen($body)."\r\n");
        fwrite($client, "Connection: close\r\n\r\n");
        fwrite($client, $body);
    }
}
