<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal Emulator</title>
    <link href="./css/xterm.css" rel="stylesheet">
    <script src="./js/xterm.js"></script>
    <style>
        html, body { margin: 0; }
        #terminal-container > div {
            height: 100%;
        }
    </style>
</head>
<body>
    <div id="terminal-container" style="width: 100%; height: 100vh;"></div>

    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            const terminal = new Terminal();
            terminal.open(document.getElementById('terminal-container'));

            const ws = new WebSocket('wss://172.210.73.206:8081');
            
            terminal.write('Connected to the terminal emulator\r\n');
            ws.onopen = () => {
                console.log('WebSocket connection opened');
            };

            ws.onmessage = (event) => {
                terminal.write(event.data);
            };

            terminal.onData((data) => {
                ws.send(data);
            });
        });
    </script>
</body>
</html>
