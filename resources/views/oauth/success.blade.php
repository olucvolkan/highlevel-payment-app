<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Successful</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #27ae60;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .message {
            color: #7f8c8d;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .features {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .features h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list li:before {
            content: "✓";
            color: #27ae60;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .close-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 20px;
        }
        
        .close-button:hover {
            background: #2980b9;
        }
        
        .paytr-logo {
            margin: 20px 0;
        }
        
        .paytr-logo img {
            max-height: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Integration Successful!</h1>
        <p class="message">
            {{ $message }}
        </p>
        
        <div class="paytr-logo">
            <img src="{{ config('app.url') }}/images/paytr-logo.png" alt="PayTR" onerror="this.style.display='none'">
        </div>
        
        <div class="features">
            <h3>Your PayTR integration is now active with these features:</h3>
            <ul class="feature-list">
                <li>Accept payments from Turkish customers</li>
                <li>Support for all major Turkish banks</li>
                <li>Secure card storage for repeat customers</li>
                <li>Installment payment options</li>
                <li>Real-time payment notifications</li>
                <li>Automatic refund processing</li>
                <li>Multi-currency support (TRY, USD, EUR)</li>
            </ul>
        </div>
        
        <p class="message" style="font-size: 14px; margin-top: 30px;">
            You can now close this window and return to HighLevel to start accepting payments.
        </p>
        
        <button class="close-button" onclick="closeWindow()">Close Window</button>
    </div>

    <script>
        function closeWindow() {
            // Try to close the window
            if (window.opener) {
                window.close();
            } else if (window.parent && window.parent !== window) {
                // If in iframe, notify parent
                window.parent.postMessage({
                    type: 'oauth_success',
                    data: {
                        success: true,
                        message: 'Integration completed successfully'
                    }
                }, '*');
            } else {
                // Fallback - redirect to HighLevel
                window.location.href = 'https://app.gohighlevel.com';
            }
        }

        // Auto-close after 10 seconds if in popup
        if (window.opener || window.parent !== window) {
            setTimeout(function() {
                document.querySelector('.close-button').style.background = '#95a5a6';
                document.querySelector('.close-button').innerHTML = 'Closing in 5 seconds...';
                
                setTimeout(closeWindow, 5000);
            }, 5000);
        }
    </script>
</body>
</html>