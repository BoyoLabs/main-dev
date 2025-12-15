<?php
// Function to handle the actual ping operation
function ping_ip($ip_address) {
    // Determine the operating system to use the correct ping command
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows command: -n 1 for 1 request, -w 1000 for 1000ms timeout
        $command = "ping -n 1 -w 1000 " . escapeshellarg($ip_address);
    } else {
        // Linux/Unix command: -c 1 for 1 count, -W 1 for 1 second timeout
        $command = "ping -c 1 -W 1 " . escapeshellarg($ip_address);
    }

    // Execute the command and capture the output
    $output = shell_exec($command);

    if ($output === null) {
        return ["status" => "error", "message" => "Command failed or shell_exec is disabled."];
    }

    // Process the output to determine success and latency
    if (strpos($output, "Request timed out") !== false || strpos($output, "Destination Host Unreachable") !== false || strpos($output, "0 received") !== false) {
        return ["status" => "failure", "message" => "Host Unreachable/Timed Out", "full_output" => nl2br(htmlentities($output))];
    } else if (preg_match('/time=(\d+\.?\d*)ms/', $output, $matches)) {
        // Successful ping with latency captured
        $latency = $matches[1];
        return ["status" => "success", "message" => "Reply in " . $latency . "ms", "latency" => $latency, "full_output" => nl2br(htmlentities($output))];
    } else {
        // Other error or unexpected output format
        return ["status" => "unknown", "message" => "Ping results are inconclusive.", "full_output" => nl2br(htmlentities($output))];
    }
}

// --- AJAX Request Handler ---
if (isset($_GET['ip']) && !empty($_GET['ip'])) {
    header('Content-Type: application/json');
    $ip_to_ping = filter_var($_GET['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);

    if (!$ip_to_ping) {
        echo json_encode(["status" => "error", "message" => "Invalid or Private IP address."]);
        exit;
    }

    echo json_encode(ping_ip($ip_to_ping));
    exit;
}
// --- END AJAX Request Handler ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ping Tester</title>
    <style>
        /* --- DARK THEME STYLES --- */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #1e1e1e; /* Dark background */
            color: #d4d4d4; /* Light text */
            padding: 20px; 
        }
        .container { 
            max-width: 700px; 
            margin: 0 auto; /* Centers the container */
            background: #252526; /* Slightly lighter dark container */
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.5); 
        }
        h1, h3 { 
            text-align: center; 
            color: #ffffff; 
        }
        
        /* --- ALIGNMENT FIX START (Using margin: 0 auto;) --- */
        .input-group { 
            /* Reverted from display: flex to a centered block element */
            margin-bottom: 20px; 
            width: 90%; /* Fixed width for centering */
            margin: 0 auto 20px auto; /* Centers the block */
            display: block; /* Ensures it acts as a block element for centering */
            overflow: hidden; /* Clears floating elements inside */
        }
        .input-group input[type="text"] { 
            /* Input takes up remaining space using calc() and floats left */
            float: left;
            width: calc(100% - 100px); /* 100% minus button width (approx) */
            padding: 10px; 
            border: 1px solid #3c3c3c; 
            border-radius: 4px 0 0 4px; 
            font-size: 16px; 
            background-color: #333333; 
            color: #ffffff;
            box-sizing: border-box; /* Crucial for calc() */
        }
        .input-group button { 
            /* Button floats right */
            float: right;
            width: 100px; /* Fixed width for button */
            padding: 10px 15px; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 0 4px 4px 0; 
            cursor: pointer; 
            font-size: 16px; 
            height: 40px; /* Match the height of the input field */
            box-sizing: border-box; /* Crucial for sizing */
        }
        /* --- ALIGNMENT FIX END --- */

        .input-group button:hover { 
            background-color: #0056b3; 
        }
        #log { 
            border: 1px solid #3c3c3c; 
            height: 300px; 
            overflow-y: scroll; 
            padding: 10px; 
            background-color: #000000; 
            color: #0f0; 
            margin-top: 20px; 
            font-size: 14px; 
            line-height: 1.5; 
            white-space: pre-wrap;
            font-family: 'Courier New', monospace; 
        }
        
        /* --- STATUS COLORS --- */
        .status-success { color: #00ff00; font-weight: bold; } 
        .status-failure { color: #ff6347; font-weight: bold; } 
        .status-unknown { color: #ffd700; font-weight: bold; } 
        .status-error { color: #ff00ff; font-weight: bold; } 

        /* Time stamp and full output */
        #log > div > span:first-child { color: #888; } 
        
        .console-output { 
            font-family: 'Courier New', monospace; 
            background-color: #1a1a1a; 
            color: #0f0; 
            padding: 5px; 
            border-radius: 4px; 
            display: block; 
            margin-top: 5px; 
            font-size: 12px;
        }
        
        /* Latency Bar */
        .latency-bar { 
            height: 10px; 
            background-color: #00ff00; 
            width: 0%; 
            transition: width 0.5s; 
            margin-top: 5px; 
            border-radius: 2px; 
        }
        /* Background for the latency bar container */
        #status_display > div:last-child { 
             background-color: #333; 
             border-radius: 2px; 
             margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🌐 Ping Tester</h1>
    <p><center>By Boyo Labs</center></p>
    <div class="input-group">
        <input type="text" id="ip_address" placeholder="Enter IP Address (e.g., 8.8.8.8)" value="8.8.8.8">
        <button id="start_button">Start Ping</button>
    </div>
    <div id="status_display">
        </div>
    <h3>Ping Log:</h3>
    <div id="log">Awaiting input...</div>
</div>

<script>
// ... (JavaScript code remains the same as it handles functionality, not layout)

    let pingInterval;
    let isPinging = false;
    const logElement = document.getElementById('log');
    const ipInput = document.getElementById('ip_address');
    const startButton = document.getElementById('start_button');
    const statusDisplay = document.getElementById('status_display');
    const PING_RATE_MS = 2000; // Ping every 2 seconds

    function logMessage(message, statusClass) {
        const timestamp = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.innerHTML = `<span style="color: #888;">[${timestamp}]</span> <span class="${statusClass}">${message}</span>`;
        logElement.prepend(entry); // Add to the top
    }

    function updateStatusDisplay(result) {
        let html = `
            <div>
                <strong>Target:</strong> ${ipInput.value}
            </div>
        `;

        if (result.status === 'success') {
            const barWidth = Math.min(100, (result.latency / 200) * 100); // Scale latency for max 200ms
            html += `
                <div class="status-success">
                    Status: **${result.message}**
                </div>
                <div> 
                    <div class="latency-bar" style="width: ${barWidth}%"></div>
                </div>
            `;
        } else {
            html += `
                <div class="status-${result.status}">
                    Status: **${result.message}**
                </div>
            `;
        }
        statusDisplay.innerHTML = html;
    }

    function sendPing() {
        if (!isPinging) return;

        const ip = ipInput.value;
        const url = `?ip=${encodeURIComponent(ip)}`;

        fetch(url)
            .then(response => response.json())
            .then(result => {
                let statusClass = 'status-unknown';
                if (result.status === 'success') {
                    statusClass = 'status-success';
                } else if (result.status === 'failure') {
                    statusClass = 'status-failure';
                } else if (result.status === 'error') {
                    statusClass = 'status-error';
                }
                
                // Update log with main message
                logMessage(`Ping: ${result.message}`, statusClass);
                
                // Update status display (header)
                updateStatusDisplay(result);

                // Add full output to the log entry (prepending to the log)
                const lastEntry = logElement.firstChild;
                if (lastEntry) {
                     const fullOutputDiv = document.createElement('span');
                     fullOutputDiv.className = 'console-output';
                     fullOutputDiv.innerHTML = result.full_output || 'No full output available.';
                     lastEntry.appendChild(fullOutputDiv);
                }

            })
            .catch(error => {
                logMessage('AJAX Error: Could not connect to the server.', 'status-error');
                console.error('Fetch error:', error);
                stopPing(); // Stop on critical error
            });
    }

    function startPing() {
        const ip = ipInput.value.trim();
        if (!ip) {
            alert('Please enter a valid IP address.');
            return;
        }

        logElement.innerHTML = ''; // Clear log
        isPinging = true;
        ipInput.disabled = true;
        startButton.textContent = 'Stop Ping';
        startButton.classList.add('stop');
        startButton.onclick = stopPing;
        logMessage(`Starting ping to **${ip}** every ${PING_RATE_MS / 1000} seconds...`, 'status-unknown');
        
        // Initial ping immediately
        sendPing(); 
        
        // Set up the interval for constant pings
        pingInterval = setInterval(sendPing, PING_RATE_MS);
    }

    function stopPing() {
        isPinging = false;
        clearInterval(pingInterval);
        ipInput.disabled = false;
        startButton.textContent = 'Start Ping';
        startButton.classList.remove('stop');
        startButton.onclick = startPing;
        logMessage('Ping stopped by user.', 'status-unknown');
    }

    // Initial button setup
    startButton.onclick = startPing;

    // Allow pressing Enter in the input field
    ipInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !isPinging) {
            e.preventDefault();
            startPing();
        }
    });

</script>

</body>
</html>
