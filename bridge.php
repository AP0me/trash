<?php
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();

$localAddress = 'localhost';
$localPort = 8081;
$remoteAddress = '172.210.73.206';
$remotePort = 8080;

// Create a TCP Stream socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

// Bind the socket to an address/port
$result = socket_bind($socket, $localAddress, $localPort);
if ($result === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($socket)) . "\n";
}

// Start listening for connections
$result = socket_listen($socket, 5);
if ($result === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($socket)) . "\n";
}

function perform_handshake($client, &$headers) {
    $lines = preg_split("/\r\n/", $headers);
    $headers = [];
    foreach ($lines as $line) {
        if (strpos($line, ": ") !== false) {
            list($key, $value) = explode(": ", $line);
            $headers[$key] = $value;
        } elseif (stripos($line, "GET") === 0) {
            $headers["GET"] = $line;
        }
    }

    $secWebSocketKey = $headers['Sec-WebSocket-Key'];
    $secWebSocketAccept = base64_encode(pack('H*', sha1($secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept:$secWebSocketAccept\r\n\r\n";

    socket_write($client, $upgrade);
}

function decode_frame($data) {
    $bytes = ord($data[1]) & 127;
    if ($bytes == 126) {
        $mask = substr($data, 4, 4);
        $payload = substr($data, 8);
    } elseif ($bytes == 127) {
        $mask = substr($data, 10, 4);
        $payload = substr($data, 14);
    } else {
        $mask = substr($data, 2, 4);
        $payload = substr($data, 6);
    }
    $decoded = "";
    for ($i = 0; $i < strlen($payload); ++$i) {
        $decoded .= $payload[$i] ^ $mask[$i % 4];
    }
    return $decoded;
}

function encode_frame($data) {
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($data);

    if ($length <= 125)
        $header = pack('CC', $b1, $length);
    elseif ($length > 125 && $length < 65536)
        $header = pack('CCn', $b1, 126, $length);
    elseif ($length >= 65536)
        $header = pack('CCNN', $b1, 127, $length);
    return $header . $data;
}

while (true) {
    // Accept incoming connection
    $client = socket_accept($socket);
    if ($client === false) {
        echo "socket_accept() failed: reason: " . socket_strerror(socket_last_error($socket)) . "\n";
        break;
    }

    // Read the header from the client
    $request = socket_read($client, 5000);
    perform_handshake($client, $request);

    // Create a connection to the remote server
    $remoteSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($remoteSocket === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
    }

    $result = socket_connect($remoteSocket, $remoteAddress, $remotePort);
    if ($result === false) {
        echo "socket_connect() failed: reason: " . socket_strerror(socket_last_error($remoteSocket)) . "\n";
    }

    while (true) {
        $read = [$client, $remoteSocket];
        $write = null;
        $except = null;
        $num_changed_sockets = socket_select($read, $write, $except, 0, 10);

        if (in_array($client, $read)) {
            $data = socket_read($client, 2048);
            if ($data === false) {
                echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($client)) . "\n";
                break 2;
            }
            $message = decode_frame($data);
            socket_write($remoteSocket, $message, strlen($message));
        }

        if (in_array($remoteSocket, $read)) {
            $data = socket_read($remoteSocket, 2048);
            if ($data === false) {
                echo "socket_read() failed: reason: " . socket_strerror(socket_last_error($remoteSocket)) . "\n";
                break 2;
            }
            $encoded = encode_frame($data);
            socket_write($client, $encoded, strlen($encoded));
        }
    }

    socket_close($client);
    socket_close($remoteSocket);
}

socket_close($socket);
?>
