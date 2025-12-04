<?php
declare(strict_types=1);

namespace Tabula17\Satelles\Omnia\Roga\Database;

use Swoole\Client;
use Tabula17\Satelles\Omnia\Roga\Exception\ConnectionException;

class Bridge
{
    private Client $client;
    private string $host;
    private int $port;
    private float $timeout;

    public function __construct(string $host = '127.0.0.1', int $port = 9501, float $timeout = 60.0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->client = new Client(SWOOLE_SOCK_TCP);

        $this->configureClient();
    }

    private function configureClient(): void
    {
        $this->client->set([
            // Deshabilitar empaquetado (servidor envía JSON plano)
            'open_length_check' => false,
            'open_eof_check' => false,

            // Timeouts
            'timeout' => $this->timeout,
            'connect_timeout' => 5.0,
            'write_timeout' => 10.0,
            'read_timeout' => $this->timeout,

            // Buffers para respuestas grandes
            'socket_buffer_size' => 1024 * 1024 * 64, // 64MB
            'buffer_output_size' => 1024 * 1024 * 64,
        ]);
    }

    /**
     * @throws ConnectionException|\JsonException
     */
    public function request(array $params): array
    {
        if (!$this->client->connect($this->host, $this->port, 5.0)) {
            throw new ConnectionException(
                "Connection failed: " . swoole_strerror($this->client->errCode),
                $this->client->errCode
            );
        }

        $payload = json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if (!$this->client->send($payload)) {
            $this->client->close();
            throw new ConnectionException(
                "Send failed: " . swoole_strerror($this->client->errCode),
                $this->client->errCode
            );
        }

        $response = $this->receiveResponse();
        $this->client->close();

        return $response;
    }

    private function receiveResponse(): array
    {
        $data = '';
        $startTime = microtime(true);
        $maxTime = $this->timeout;

        while (true) {
            $chunk = $this->client->recv(65536); // 64KB chunks

            if ($chunk === false) {
                $errCode = $this->client->errCode;

                if ($errCode === SOCKET_EAGAIN || $errCode === SOCKET_ETIMEDOUT) {
                    // Timeout de socket, verificar timeout global
                    if (microtime(true) - $startTime > $maxTime) {
                        throw new ConnectionException("Receive timeout after {$maxTime} seconds");
                    }
                    usleep(50000); // 50ms
                    continue;
                }

                throw new ConnectionException(
                    "Receive error: " . swoole_strerror($errCode),
                    $errCode
                );
            }

            if ($chunk === '') {
                // Conexión cerrada por servidor
                break;
            }

            $data .= $chunk;

            // Verificar si tenemos un JSON completo
            if ($this->isCompleteJson($data)) {
                break;
            }

            // Verificar timeout global
            if (microtime(true) - $startTime > $maxTime) {
                throw new ConnectionException("Global timeout reached");
            }
        }

        if ($data === '') {
            throw new ConnectionException("Empty response from server");
        }

        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON response (first 500 chars): " . substr($data, 0, 500));
            throw new ConnectionException("Invalid JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }

    private function isCompleteJson(string $data): bool
    {
        // Intento rápido: verificar si es JSON válido
        json_decode($data);
        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        }

        // Verificar balance de brackets para JSON grande
        $openBraces = substr_count($data, '{');
        $closeBraces = substr_count($data, '}');
        $openBrackets = substr_count($data, '[');
        $closeBrackets = substr_count($data, ']');

        return ($openBraces === $closeBraces) && ($openBrackets === $closeBrackets);
    }

    public function __destruct()
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }
}
/*
// Uso:
$client = new Bridge('127.0.0.1', 9501, 120.0);
try {
    $result = $client->request('Pt/despacho/Expedicion', ['subinventarios' => 397]);

    if (isset($result['status']) && $result['status'] === 'ok') {
        echo "Success! Rows returned: " . count($result['data'] ?? []) . "\n";
        // Procesar $result['data']
    } else {
        echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
    }
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}*/