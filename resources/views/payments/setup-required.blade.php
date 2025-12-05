<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Setup Required</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        h1 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 10px;
        }

        p {
            color: #7f8c8d;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .setup-button {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 32px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .setup-button:hover {
            background: #2980b9;
        }

        .info-box {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 14px;
            color: #34495e;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="icon">⚙️</div>
        <h1>PayTR Configuration Required</h1>
        <p>
            Before you can accept payments, you need to configure your PayTR credentials.
            This only takes a few minutes and you'll be ready to start processing payments.
        </p>

        <a href="{{ $setupUrl }}" class="setup-button" target="_parent">
            Configure PayTR Now
        </a>

        <div class="info-box">
            <strong>What you'll need:</strong><br>
            • PayTR Merchant ID<br>
            • PayTR Merchant Key<br>
            • PayTR Merchant Salt
        </div>
    </div>

    <script>
        // Notify parent that setup is required
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'custom_element_error_response',
                error: {
                    description: 'PayTR configuration required. Please configure your PayTR credentials first.'
                }
            }, '*');
        }
    </script>
</body>
</html>
