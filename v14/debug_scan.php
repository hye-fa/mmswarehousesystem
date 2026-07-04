<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Debug Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body { background-color: #333; color: #fff; padding: 20px; font-family: monospace; }
        .log-box { background: black; border: 1px solid #555; padding: 15px; height: 400px; overflow-y: auto; white-space: pre-wrap; color: #0f0; font-size: 14px; }
        .input-box { font-size: 20px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h3 class="text-warning">🔍 Scanner Debug Tool</h3>
    <p>Use this page to diagnose Date Format issues.</p>

    <div class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <label class="form-label text-white">1. Click here and Scan:</label>
            <input type="text" id="scan_input" class="form-control input-box" placeholder="Scan QR Code..." autocomplete="off">
        </div>
    </div>

    <div class="card bg-dark border-secondary mb-4">
        <div class="card-body">
            <label class="form-label text-white">2. Flatpickr Target (Visual Test):</label>
            <input type="text" id="test_flatpickr" class="form-control bg-secondary text-white" placeholder="Date should appear here...">
        </div>
    </div>

    <div class="mb-2">
        <label>3. Debug Log (Copy this):</label>
        <button onclick="copyLog()" class="btn btn-sm btn-primary float-end">Copy Log to Clipboard</button>
    </div>
    <div id="debug_log" class="log-box">Waiting for scan...</div>
</div>

<script>
    // Initialize Flatpickr exactly like your main page
    const fp = flatpickr("#test_flatpickr", {
        dateFormat: "d/m/Y",
        allowInput: true
    });

    const input = document.getElementById('scan_input');
    const logBox = document.getElementById('debug_log');

    // Listen for input
    input.addEventListener('input', function(e) {
        const val = e.target.value.trim();
        
        // Only trigger if looks like a full scan (length > 10)
        if (val.length > 10) {
            runDebug(val);
        }
    });

    function log(msg) {
        logBox.innerHTML += msg + "\n";
        logBox.scrollTop = logBox.scrollHeight;
    }

    function runDebug(rawString) {
        logBox.innerHTML = "--- NEW SCAN DETECTED ---\n";
        log("1. Raw Input: [" + rawString + "]");

        // A. SPLIT STRING
        const parts = rawString.split('-');
        log("2. Split Check: found " + parts.length + " parts.");
        
        if (parts.length === 3) {
            const rawDate = parts[0]; // e.g. 260831
            log("3. Date Part Extracted: [" + rawDate + "]");

            // B. PARSE DATE (YYMMDD)
            if (rawDate.length === 6) {
                const yearShort = rawDate.substring(0, 2); // 26
                const month     = rawDate.substring(2, 4); // 08
                const day       = rawDate.substring(4, 6); // 31

                const yearFull = "20" + yearShort; // 2026

                log("4. Parsing Logic:");
                log("   - Year Raw: " + yearShort + " -> Full: " + yearFull);
                log("   - Month: " + month);
                log("   - Day: " + day);

                // C. CONSTRUCT STRING
                const finalString = day + "/" + month + "/" + yearFull;
                log("5. Constructed String (DD/MM/YYYY): [" + finalString + "]");

                // D. ATTEMPT FLATPICKR SET
                try {
                    log("6. Attempting to set Flatpickr...");
                    
                    // TEST 1: String Set
                    fp.setDate(finalString, true, "d/m/Y");
                    
                    const valueInInput = document.getElementById('test_flatpickr').value;
                    log("7. Result in Input Box: [" + valueInInput + "]");

                    if (valueInInput === finalString) {
                        log("✅ SUCCESS: Date set correctly.");
                    } else {
                        log("❌ FAIL: Input box shows different value.");
                        
                        // Try Alternative: Date Object
                        log("   ...Retrying with JS Date Object...");
                        // Month is 0-indexed in JS (Jan=0, Aug=7)
                        const jsDate = new Date(parseInt(yearFull), parseInt(month)-1, parseInt(day));
                        log("   - JS Object: " + jsDate.toString());
                        fp.setDate(jsDate, true, "d/m/Y");
                        log("   - Retry Result: [" + document.getElementById('test_flatpickr').value + "]");
                    }

                } catch (err) {
                    log("❌ ERROR: " + err.message);
                }

            } else {
                log("❌ ERROR: Date part length is not 6 characters.");
            }
        } else {
            log("❌ ERROR: Did not find 3 parts separated by hyphen.");
        }
        log("-------------------------");
    }

    function copyLog() {
        const text = document.getElementById('debug_log').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert("Log copied! Paste it in the chat.");
        });
    }
</script>

</body>
</html>