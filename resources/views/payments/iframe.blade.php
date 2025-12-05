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
            <div class="amount">Preparing payment...</div>
            <div class="transaction-id">Please wait</div>
        </div>
        <div class="spinner"></div>
        <p>Loading secure payment form...</p>
    </div>
    
    <div id="error" class="error-message">
        <h3>Payment Error</h3>
        <p>Unable to load payment form. Please try again.</p>
    </div>
    
    <iframe id="paymentFrame" class="payment-iframe"></iframe>

    <script>
        // Payment configuration
        const config = {
            locationId: '{{ $locationId ?? "" }}',  // Can be empty, will come via postMessage
            publishableKey: '{{ $publishableKey ?? "" }}',  // Publishable key for authentication
            apiUrl: '{{ $apiUrl }}',
            iframeUrl: null,
            merchantOid: null,
            transactionId: null,
            amount: null,
            currency: null
        };

        console.log('Payment iframe loaded with config:', {
            locationId: config.locationId || 'Will receive via postMessage',
            publishableKey: config.publishableKey ? '***' + config.publishableKey.slice(-8) : 'Will receive via postMessage',
            apiUrl: config.apiUrl
        });

        // HighLevel allowed origins for postMessage security
        const HIGHLEVEL_ORIGINS = [
            'https://app.gohighlevel.com',
            'https://app.msgsndr.com',
            'https://builder.gohighlevel.com'
        ];

        let paymentInitialized = false;

        // DOM elements
        const loading = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const paymentFrame = document.getElementById('paymentFrame');

        // Notify parent that payment iframe is ready
        function notifyParentReady() {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'custom_provider_ready',
                    loaded: true,
                    addCardOnFileSupported: true // PayTR supports card storage
                }, '*'); // HighLevel expects wildcard origin for initial ready message
                console.log('Sent custom_provider_ready to parent');
            }
        }

        // Notify parent of payment success
        function notifyPaymentSuccess(chargeId) {
            if (window.parent && window.parent !== window) {
                console.log('Sending payment success to parent:', chargeId);
                window.parent.postMessage({
                    type: 'custom_element_success_response',
                    chargeId: chargeId,
                    transactionId: config.transactionId
                }, '*');
            }
        }

        // Notify parent of payment error
        function notifyPaymentError(errorMessage) {
            if (window.parent && window.parent !== window) {
                console.log('Sending payment error to parent:', errorMessage);
                window.parent.postMessage({
                    type: 'custom_element_error_response',
                    error: {
                        description: errorMessage
                    }
                }, '*');
            }
        }

        // Notify parent of payment cancellation
        function notifyPaymentClosed() {
            if (window.parent && window.parent !== window) {
                console.log('Sending payment closed to parent');
                window.parent.postMessage({
                    type: 'custom_element_close_response'
                }, '*');
            }
        }

        // Handle payment initiation from HighLevel
        function handlePaymentInitiation(paymentData) {
            if (paymentInitialized) {
                console.warn('Payment already initialized');
                return;
            }

            console.log('Received payment_initiate_props from HighLevel:', paymentData);
            paymentInitialized = true;

            // Extract locationId if present (HighLevel may send it here)
            if (paymentData.locationId && !config.locationId) {
                console.log('LocationId received via postMessage:', paymentData.locationId);
                config.locationId = paymentData.locationId;
            }

            // Extract publishableKey if present (HighLevel sends this for authentication)
            if (paymentData.publishableKey && !config.publishableKey) {
                console.log('PublishableKey received via postMessage');
                config.publishableKey = paymentData.publishableKey;
            }

            // Update config with received data
            config.amount = paymentData.amount;
            config.currency = paymentData.currency || 'TRY';
            config.transactionId = paymentData.transactionId;

            // Update UI to show payment info
            if (paymentData.amount && document.querySelector('.amount')) {
                document.querySelector('.amount').textContent =
                    `${paymentData.amount} ${paymentData.currency || 'TRY'}`;
            }
            if (paymentData.transactionId && document.querySelector('.transaction-id')) {
                document.querySelector('.transaction-id').textContent =
                    `Transaction: ${paymentData.transactionId}`;
            }

            // Call backend to initialize PayTR payment
            initializePayTRPayment(paymentData);
        }

        // Initialize PayTR payment via backend
        function initializePayTRPayment(paymentData) {
            // Check if we have publishableKey (primary authentication)
            if (!config.publishableKey) {
                console.error('Cannot initialize payment: publishableKey is missing');
                loading.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = 'Configuration error: Publishable Key missing';
                notifyPaymentError('Publishable Key not provided');
                return;
            }

            // Check if we have locationId (fallback, may not be needed with publishable key)
            if (!config.locationId) {
                console.warn('LocationId is missing, will authenticate using publishable key only');
            }

            loading.style.display = 'flex';
            errorDiv.style.display = 'none';

            const requestData = {
                amount: paymentData.amount,
                currency: paymentData.currency || 'TRY',
                email: paymentData.contact?.email || paymentData.email,
                transactionId: paymentData.transactionId,
                contactId: paymentData.contact?.id || paymentData.contactId,
                orderId: paymentData.orderId,
                subscriptionId: paymentData.subscriptionId,
                mode: paymentData.mode || 'payment',
                user_name: paymentData.contact?.name || 'Customer',
                user_phone: paymentData.contact?.phone || '0000000000',
                user_ip: '{{ request()->ip() }}',
            };

            console.log('Calling /api/payments/initialize with data:', requestData);

            fetch(config.apiUrl + '/api/payments/initialize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Publishable-Key': config.publishableKey,  // Primary authentication
                    'X-Location-Id': config.locationId || '',    // Optional fallback
                    'Accept': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.error || 'Payment initialization failed');
                    });
                }
                return response.json();
            })
            .then(result => {
                console.log('Payment initialization response:', result);

                if (result.success) {
                    // Store payment details
                    config.merchantOid = result.merchant_oid;
                    config.iframeUrl = result.iframe_url;

                    // Load PayTR iframe
                    paymentFrame.src = result.iframe_url;
                } else {
                    loading.style.display = 'none';
                    errorDiv.style.display = 'block';
                    notifyPaymentError(result.error || 'Payment initialization failed');
                }
            })
            .catch(error => {
                console.error('Payment initialization error:', error);
                loading.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = error.message;
                notifyPaymentError(error.message || 'Failed to initialize payment');
            });
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

        // Listen for messages from both HighLevel and PayTR
        window.addEventListener('message', function(event) {
            console.log('Received postMessage:', event.origin, event.data);

            // Handle messages from HighLevel (payment initiation)
            if (HIGHLEVEL_ORIGINS.includes(event.origin) || event.origin.includes('gohighlevel') || event.origin.includes('msgsndr')) {
                const data = event.data;

                if (data.type === 'payment_initiate_props') {
                    handlePaymentInitiation(data);
                }
                return;
            }

            // Handle messages from PayTR iframe
            if (event.origin === 'https://www.paytr.com') {
                const data = event.data;

                if (data.type === 'payment_success') {
                    notifyPaymentSuccess(data.chargeId || config.merchantOid);
                } else if (data.type === 'payment_error') {
                    notifyPaymentError(data.error || 'Payment failed');
                } else if (data.type === 'payment_cancelled') {
                    notifyPaymentClosed();
                }
                return;
            }

            // Log unknown origins for debugging
            console.warn('Received message from unknown origin:', event.origin);
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

        // Send ready notification immediately when page loads
        // DON'T wait for iframe to load - HighLevel needs to know we're ready first
        notifyParentReady();

        // The PayTR iframe will be loaded AFTER we receive payment_initiate_props from HighLevel
        // and successfully call /api/payments/initialize
    </script>
</body>
</html>