<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
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
            max-width: 500px;
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
            font-size: 28px;
        }
        
        .message {
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.5;
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
        
        .close-button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .close-button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✓</div>
        <h1>Payment Successful!</h1>
        <p class="message">
            Your payment has been processed successfully. You will receive a confirmation email shortly.
        </p>
        
        @if($payment)
        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value">{{ $payment->transaction_id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">{{ $payment->merchant_oid }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value">{{ $payment->paid_at ? $payment->paid_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s') }}</span>
            </div>
        </div>
        @endif
        
        <button class="close-button" onclick="closeWindow()">Close</button>
    </div>

    <script>
        function closeWindow() {
            // Notify parent window that payment is complete
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_success_response',
                    data: {
                        @if($payment)
                        chargeId: '{{ $payment->charge_id ?: $payment->merchant_oid }}',
                        transactionId: '{{ $payment->transaction_id }}',
                        amount: {{ $payment->amount }},
                        currency: '{{ $payment->currency }}'
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

        // Auto-close after 5 seconds if in popup
        if (window.opener || window.parent !== window) {
            setTimeout(closeWindow, 5000);
        }
    </script>
</body>
</html>