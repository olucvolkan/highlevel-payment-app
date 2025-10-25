<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
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
            max-width: 500px;
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
            font-size: 28px;
        }
        
        .message {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .error-details {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            color: #e53e3e;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: bold;
            color: #495057;
        }
        
        .detail-value {
            color: #6c757d;
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
        
        .close-button {
            background: #95a5a6;
            color: white;
        }
        
        .close-button:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">✗</div>
        <h1>Payment Failed</h1>
        <p class="message">
            We were unable to process your payment. Please check your payment information and try again.
        </p>
        
        @if(isset($error) && $error)
        <div class="error-details">
            <strong>Error:</strong> {{ $error }}
        </div>
        @endif
        
        @if($payment)
        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">{{ $payment->merchant_oid }}</span>
            </div>
            @if($payment->transaction_id)
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value">{{ $payment->transaction_id }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
            </div>
            @if($payment->error_message)
            <div class="detail-row">
                <span class="detail-label">Reason:</span>
                <span class="detail-value">{{ $payment->error_message }}</span>
            </div>
            @endif
        </div>
        @endif
        
        <div class="button-group">
            <button class="button retry-button" onclick="retryPayment()">Try Again</button>
            <button class="button close-button" onclick="closeWindow()">Close</button>
        </div>
    </div>

    <script>
        function retryPayment() {
            // Notify parent to retry payment
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_retry_payment',
                    data: {
                        @if($payment)
                        transactionId: '{{ $payment->transaction_id }}',
                        merchantOid: '{{ $payment->merchant_oid }}'
                        @endif
                    }
                }, '*');
            } else {
                // Reload the page to retry
                window.location.reload();
            }
        }

        function closeWindow() {
            // Notify parent window that payment failed
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_error_response',
                    data: {
                        error: '{{ $error ?: "Payment failed" }}',
                        @if($payment)
                        transactionId: '{{ $payment->transaction_id }}',
                        merchantOid: '{{ $payment->merchant_oid }}'
                        @endif
                    }
                }, '*');
            }
            
            // Try to close the window/tab
            if (window.opener) {
                window.close();
            } else {
                // If we can't close, redirect back
                window.history.back();
            }
        }
    </script>
</body>
</html>