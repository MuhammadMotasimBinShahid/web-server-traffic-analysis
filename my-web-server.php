<?php

if ($argc < 3) {
    echo "Usage: php my-web-server.php <host> <port>\n";
    exit(1);
}

$host = $argv[1];
$port = $argv[2];
$logFile = 'server.log';

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "Socket Creation Failed: " . socket_strerror(socket_last_error()) . "\n";
    exit(1);
}

socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

if (!socket_bind($socket, $host, $port)) {
    echo "Socket Binding Failed: " . socket_strerror(socket_last_error()) . "\n";
    socket_close($socket);
    exit(1);
}

if (!socket_listen($socket, 5)) {
    echo "Socket Listening Failed: " . socket_strerror(socket_last_error()) . "\n";
    socket_close($socket);
    exit(1);
}

echo "Server is running on $host:$port\n";

while (true) {
    $client = @socket_accept($socket);
    if ($client) {
        $request = '';
        while ($chunk = @socket_read($client, 1024)) {
            $request .= $chunk;
            if (strpos($chunk, "\r\n\r\n") !== false) {
                break;
            }
        }

        list($headers, $body) = explode("\r\n\r\n", $request, 2);
        $headers = explode("\r\n", $headers);
        $method = array_shift($headers);
        list($method, $uri, $protocol) = explode(' ', $method);

        http_response_code(200);

        $response = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Type: text/html\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= "Hello, World!\r\n";
        $response .= "You requested: " . $uri . "\r\n";

        @socket_write($client, $response);
        @socket_close($client);

        $logEntry = date('Y-m-d H:i:s') . " - " . $method . " " . $uri . " - Status: " . http_response_code() . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        echo "Request from  - " . $method . " " . $uri . "\n";

        if ($uri == '/shutdown') {
            break;
        }

    }
}

socket_close($socket);

echo "Server is shutting down\n";

?>

