<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Payment</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            flex-direction: column;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .payment-iframe {
            width: 100%;
            height: 100vh;
            border: none;
            display: none;
        }
        
        .error-message {
            text-align: center;
            color: #e74c3c;
            padding: 20px;
            display: none;
        }
        
        .payment-info {
            background: white;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .transaction-id {
            font-size: 14px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div id="loading" class="loading">
        <div class="payment-info">
            <div class="amount">{{ number_format($amount, 2) }} {{ $currency }}</div>
            <div class="transaction-id">Transaction: {{ $transactionId }}</div>
        </div>
        <div class="spinner"></div>
        <p>Preparing secure payment...</p>
    </div>
    
    <div id="error" class="error-message">
        <h3>Payment Error</h3>
        <p>Unable to load payment form. Please try again.</p>
    </div>
    
    <iframe id="paymentFrame" class="payment-iframe" src="{{ $iframeUrl }}"></iframe>

    <script>
        // Payment configuration
        const config = {
            iframeUrl: '{{ $iframeUrl }}',
            merchantOid: '{{ $merchantOid }}',
            transactionId: '{{ $transactionId }}',
            amount: {{ $amount }},
            currency: '{{ $currency }}'
        };

        // DOM elements
        const loading = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const paymentFrame = document.getElementById('paymentFrame');

        // Notify parent that payment iframe is ready
        function notifyParentReady() {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_provider_ready',
                    data: {
                        merchantOid: config.merchantOid,
                        transactionId: config.transactionId
                    }
                }, '*');
            }
        }

        // Notify parent of payment success
        function notifyPaymentSuccess(chargeId) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_success_response',
                    data: {
                        chargeId: chargeId,
                        transactionId: config.transactionId,
                        amount: config.amount,
                        currency: config.currency
                    }
                }, '*');
            }
        }

        // Notify parent of payment error
        function notifyPaymentError(error) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_error_response',
                    data: {
                        error: error,
                        transactionId: config.transactionId
                    }
                }, '*');
            }
        }

        // Notify parent of payment cancellation
        function notifyPaymentClosed() {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_element_close_response',
                    data: {
                        transactionId: config.transactionId
                    }
                }, '*');
            }
        }

        // Handle iframe load
        paymentFrame.onload = function() {
            loading.style.display = 'none';
            paymentFrame.style.display = 'block';
            notifyParentReady();
        };

        // Handle iframe load error
        paymentFrame.onerror = function() {
            loading.style.display = 'none';
            errorDiv.style.display = 'block';
            notifyPaymentError('Failed to load payment form');
        };

        // Listen for messages from PayTR iframe
        window.addEventListener('message', function(event) {
            // Verify origin is PayTR
            if (event.origin !== 'https://www.paytr.com') {
                return;
            }

            const data = event.data;

            if (data.type === 'payment_success') {
                // PayTR payment successful
                notifyPaymentSuccess(data.chargeId || config.merchantOid);
            } else if (data.type === 'payment_error') {
                // PayTR payment failed
                notifyPaymentError(data.error || 'Payment failed');
            } else if (data.type === 'payment_cancelled') {
                // User cancelled payment
                notifyPaymentClosed();
            }
        });

        // Poll payment status (fallback mechanism)
        let pollCount = 0;
        const maxPollCount = 60; // 5 minutes with 5-second intervals

        function pollPaymentStatus() {
            if (pollCount >= maxPollCount) {
                notifyPaymentError('Payment timeout');
                return;
            }

            fetch('/api/payments/status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    merchantOid: config.merchantOid,
                    transactionId: config.transactionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    notifyPaymentSuccess(data.chargeId || config.merchantOid);
                } else if (data.status === 'failed') {
                    notifyPaymentError(data.error || 'Payment failed');
                } else {
                    // Still pending, continue polling
                    pollCount++;
                    setTimeout(pollPaymentStatus, 5000);
                }
            })
            .catch(error => {
                console.error('Status poll error:', error);
                pollCount++;
                if (pollCount < maxPollCount) {
                    setTimeout(pollPaymentStatus, 5000);
                }
            });
        }

        // Start status polling after 10 seconds
        setTimeout(pollPaymentStatus, 10000);

        // Handle page visibility change (user switches tabs)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && paymentFrame.style.display === 'block') {
                // Page became visible again, check payment status
                pollPaymentStatus();
            }
        });

        // Set iframe source with error handling
        setTimeout(function() {
            if (config.iframeUrl) {
                paymentFrame.src = config.iframeUrl;
            } else {
                loading.style.display = 'none';
                errorDiv.style.display = 'block';
                notifyPaymentError('Invalid payment URL');
            }
        }, 1000);
    </script>
</body>
</html>