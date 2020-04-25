<?php

declare(strict_types=1);

namespace App;

class Server {
    private string $host;
    private int $port;
    private array $clients = [];
    private $socket;

    public function __construct(string $host, int $port) {
        $this->host = $host;
        $this->port = $port;
    }

    private function sendMessage(string $msg): bool {
        foreach ($this->clients as $changed_socket) {
            @socket_write($changed_socket, $msg, strlen($msg));
        }
        return true;
    }

    private function unmask(string $text): string {
        $length = ord($text[1]) & 127;
        if ($length === 126) {
            $masks = substr($text, 4, 4);
            $data = substr($text, 8);
        } elseif ($length === 127) {
            $masks = substr($text, 10, 4);
            $data = substr($text, 14);
        } else {
            $masks = substr($text, 2, 4);
            $data = substr($text, 6);
        }
        $text = '';
        for ($i = 0, $iMax = strlen($data); $i < $iMax; ++$i) {
            $text .= $data[$i] ^ $masks[$i % 4];
        }
        return $text;
    }

    private function mask(string $text): string {
        $b1 = 0x80 | (0x1 & 0x0f);
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCNN', $b1, 127, $length);
        }
        return $header . $text;
    }

    private function parseHeaders(string $recevedHeader): array {
        $headers = [];
        $lines = explode("\n", $recevedHeader);
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }
        return $headers;
    }

    private function calculateSecAccept(string $secKey): string {
        return base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    }

    private function performHandshaking(string $receivedHeader, $clientConn): void {
        $headers = $this->parseHeaders($receivedHeader);
        $secAccept = $this->calculateSecAccept($headers['Sec-WebSocket-Key']);
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake
Upgrade: websocket
Connection: Upgrade
WebSocket-Origin: $this->host
WebSocket-Location: php-ws://$this->host:$this->port/php-php-ws/chat-daemon.php
Sec-WebSocket-Accept:$secAccept

";
        socket_write($clientConn, $upgrade, strlen($upgrade));
    }

    public function run():void {
        $this->initConnection();
        while (true) {
            $changed = $this->clients;
            $null = null;
            socket_select($changed, $null, $null, 0, 10);

            if (in_array($this->socket, $changed, true)) {
                $newConnection = socket_accept($this->socket);
                $this->clients[] = $newConnection;

                $header = socket_read($newConnection, 1024);
                $this->performHandshaking($header, $newConnection, $this->host, $this->port);

                socket_getpeername($newConnection, $ip);
                $response = $this->mask(json_encode([
                    'type' => 'system',
                    'message' => $ip . ' connected'
                ], JSON_THROW_ON_ERROR));
                $this->sendMessage($response);

                $found_socket = array_search($this->socket, $changed);
                unset($changed[$found_socket]);
            }

            foreach ($changed as $changedSocket) {
                $byteSocket = @socket_recv($changedSocket, $buf, 1024, 0);
                while ($byteSocket >= 1) {
                    $received_text = $this->unmask($buf);
                    $message = json_decode($received_text, true, null, JSON_THROW_ON_ERROR);
                    $userName = $message['name']; //sender name
                    $userMessage = $message['message']; //message text
                    $userColor = $message['color']; //color

                    $response_text = $this->mask(json_encode([
                        'type' => 'usermsg',
                        'name' => $userName,
                        'message' => $userMessage,
                        'color' => $userColor
                    ], JSON_THROW_ON_ERROR));
                    $this->sendMessage($response_text);
                    break 2;
                }

                $buf = @socket_read($changedSocket, 1024, PHP_NORMAL_READ);
                if ($buf === false) {
                    $found_socket = array_search($changedSocket, $this->clients, true);
                    socket_getpeername($changedSocket, $ip);
                    unset($this->clients[$found_socket]);

                    $response = $this->mask(json_encode([
                        'type' => 'system',
                        'message' => $ip . ' disconnected'
                    ], JSON_THROW_ON_ERROR));
                    $this->sendMessage($response);
                }
            }
        }
        socket_close($this->socket);
    }

    private function initConnection(): void {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, '0', $this->port);
        socket_listen($this->socket);
        $this->clients = [$this->socket];
    }
}
