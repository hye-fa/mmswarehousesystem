<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Format Debugger</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body { background-color: #121212; color: #e0e0e0; font-family: monospace; padding: 20px; }
        .log-container { 
            background: #000; 
            border: 1px solid #333; 
            color: #0f0; 
            padding: 15px; 
            height: 400px; 
            overflow-y: auto; 
            white-space: pre-wrap; 
            font-size: 12px; 
            margin-top: 15px;
        }
        .test-box {
            background: #2c2c2c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        input { font-family: monospace; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h3 class="text-warning text-center mb-4">🕵️ Expiry Date Forensic Tool</h3>

    <div class="test-box border-primary border">
        <label class="form-label text-white">1. Scan QR Here:</label>
        <input type="text" id="scan_input" class="form-control form-control-lg bg-dark text-white border-primary" placeholder="Scan..." autocomplete="off">
    </div>

    <div class="test-box border-success border">
        <label class="form-label text-white">2. Target Calendar (d/m/Y):</label>
        <input type="text" id="target_date" class="form-control bg-dark text-white border-success" placeholder="Result should appear here...">
        <small class="text-muted">Config: dateFormat: "d/m/Y"</small>
    </div>

    <button onclick="copyLog()" class="btn btn-primary w-100 py-3 fw-bold">📋 COPY LOG REPORT</button>

    <div id="debug_log" class="log-container">Waiting for scan...</div>
</div>

<script>
    // 1. Initialize Flatpickr EXACTLY as you do in your main app
    const fp = flatpickr("#target_date", {
        dateFormat: "d/m/Y",
        allowInput: true
    });

    const input = document.getElementById('scan_input');
    const logBox = document.getElementById('debug_log');

    function log(msg) {
        const time = new Date().toISOString().split('T')[1].slice(0,8);
        logBox.innerHTML += `[${time}] ${msg}\n`;
        logBox.scrollTop = logBox.scrollHeight;
    }

    function header(title) {
        logBox.innerHTML += `\n=== ${title} ===\n`;
    }

    // LISTEN FOR SCAN
    input.addEventListener('input', function(e) {
        const val = e.target.value;
        // Wait for full scan (approx length > 10)
        if (val.length > 10) {
            runDiagnostics(val);
        }
    });

    function runDiagnostics(rawString) {
        logBox.innerHTML = ""; // Clear previous
        header("NEW SCAN RECEIVED");
        log(`Raw String: "${rawString}"`);
        log(`Length: ${rawString.length}`);

        // STEP 1: HIDDEN CHARACTER CHECK
        header("STEP 1: HIDDEN CHAR CHECK");
        let cleanString = "";
        for (let i = 0; i < rawString.length; i++) {
            const code = rawString.charCodeAt(i);
            const char = rawString.charAt(i);
            if (code < 32 || code > 126) {
                log(`⚠️ FOUND HIDDEN CHAR at index ${i}: Code ${code}`);
            } else {
                cleanString += char;
            }
        }
        log(`Cleaned String: "${cleanString}"`);

        // STEP 2: PARSING ATTEMPT
        header("STEP 2: PARSING LOGIC");
        const parts = cleanString.trim().split('-');
        log(`Split Parts found: ${parts.length}`);
        
        if (parts.length === 3) {
            const rawDate = parts[0];
            log(`Date Part: "${rawDate}"`);

            const yearShort = rawDate.substring(0, 2);
            const month     = rawDate.substring(2, 4);
            const day       = rawDate.substring(4, 6);
            const yearFull  = "20" + yearShort;

            log(`Parsed: Day=${day}, Month=${month}, Year=${yearFull}`);

            // TEST 1: SETTING "DD/MM/YYYY" STRING
            header("TEST 1: DIRECT STRING (d/m/Y)");
            const strDMY = `${day}/${month}/${yearFull}`;
            log(`Attempting to set: "${strDMY}"`);
            
            try {
                fp.clear(); // Reset
                fp.setDate(strDMY, true, "d/m/Y");
                
                const val1 = document.getElementById('target_date').value;
                log(`RESULT: Input value is now: "${val1}"`);
                
                if (val1 === strDMY) log("✅ SUCCESS");
                else log("❌ FAILED (Mismatch)");
            } catch (err) {
                log(`❌ ERROR: ${err.message}`);
            }

            // TEST 2: SETTING "YYYY-MM-DD" STRING (ISO)
            header("TEST 2: ISO STRING (Y-m-d)");
            const strISO = `${yearFull}-${month}-${day}`;
            log(`Attempting to set: "${strISO}"`);
            
            try {
                fp.clear();
                fp.setDate(strISO, true); // No format arg, expects ISO
                
                const val2 = document.getElementById('target_date').value;
                log(`RESULT: Input value is now: "${val2}"`);
                
                // We expect it to auto-convert to d/m/Y for display
                if (val2 === strDMY) log("✅ SUCCESS (Auto-converted)");
                else log("❌ FAILED");
            } catch (err) {
                log(`❌ ERROR: ${err.message}`);
            }

            // TEST 3: JAVASCRIPT DATE OBJECT
            header("TEST 3: JS DATE OBJECT");
            try {
                fp.clear();
                // Note: Month is 0-indexed in JS (Jan=0)
                const jsDate = new Date(parseInt(yearFull), parseInt(month)-1, parseInt(day));
                log(`JS Object: ${jsDate.toString()}`);
                
                fp.setDate(jsDate, true, "d/m/Y");
                
                const val3 = document.getElementById('target_date').value;
                log(`RESULT: Input value is now: "${val3}"`);
                
                if (val3 === strDMY) log("✅ SUCCESS");
                else log("❌ FAILED");
            } catch (err) {
                log(`❌ ERROR: ${err.message}`);
            }

        } else {
            log("❌ ERROR: Format Invalid (Not 3 parts)");
        }
    }

    function copyLog() {
        const text = document.getElementById('debug_log').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert("Log copied to clipboard!");
        }).catch(err => {
            alert("Failed to copy. Please select text manually.");
        });
    }
</script>

</body>
</html>