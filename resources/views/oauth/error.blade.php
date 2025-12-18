<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Failed</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
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
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: #e74c3c;
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
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .error-details {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #e53e3e;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
            text-align: left;
        }
        
        .troubleshooting {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .troubleshooting h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .troubleshooting ol {
            color: #495057;
            line-height: 1.6;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .retry-button {
            background: #3498db;
            color: white;
        }
        
        .retry-button:hover {
            background: #2980b9;
        }
        
        .support-button {
            background: #9b59b6;
            color: white;
        }
        
        .support-button:hover {
            background: #8e44ad;
        }
        
        .close-button {
            background: #95a5a6;
            color: white;
        }
        
        .close-button:hover {
            background: #7f8c8d;
        }
        
        .contact-info {
            margin-top: 20px;
            padding: 15px;
            background: #e8f4fd;
            border-radius: 6px;
            font-size: 14px;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">✗</div>
        <h1>Integration Failed</h1>
        <p class="message">
            We encountered an issue while setting up your PayTR payment integration.
        </p>
        
        @if($error)
        <div class="error-details">
            <strong>Error Details:</strong><br>
            {{ $error }}
        </div>
        @endif
        
        <div class="troubleshooting">
            <h3>Troubleshooting Steps:</h3>
            <ol>
                <li>Ensure you have the necessary permissions in your CRM account</li>
                <li>Check that your location is properly configured</li>
                <li>Verify your internet connection is stable</li>
                <li>Try the integration process again</li>
                <li>If the problem persists, contact our support team</li>
            </ol>
        </div>
        
        <div class="button-group">
            <button class="button retry-button" onclick="retryIntegration()">Try Again</button>
            <a href="mailto:support@example.com" class="button support-button">Contact Support</a>
            <button class="button close-button" onclick="closeWindow()">Close</button>
        </div>
        
        <div class="contact-info">
            <strong>Need Help?</strong><br>
            Email: support@example.com<br>
            Include your location ID and this error message for faster assistance.
        </div>
    </div>

    <script>
        function retryIntegration() {
            // Go back to start the OAuth flow again
            window.location.href = '/oauth/authorize';
        }

        function closeWindow() {
            // Notify parent window that integration failed
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'oauth_error',
                    data: {
                        error: '{{ $error ?: "Integration failed" }}'
                    }
                }, '*');
            }
            
            // Try to close the window
            if (window.opener) {
                window.close();
            } else {
                // Fallback - close iframe or redirect to dashboard
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'close_integration' }, '*');
                }
            }
        }
    </script>
</body>
</html>