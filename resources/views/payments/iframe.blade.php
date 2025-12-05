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
        // NOTE: locationId and publishableKey will ONLY come from HighLevel via postMessage
        // They are NOT passed from server-side
        const config = {
            locationId: null,  // Will be set by payment_initiate_props event
            publishableKey: null,  // Will be set by payment_initiate_props event
            apiUrl: '{{ $apiUrl }}',
            iframeUrl: null,
            merchantOid: null,
            transactionId: null,
            amount: null,
            currency: null
        };

        console.log('Payment iframe loaded. Waiting for payment_initiate_props from HighLevel...', {
            apiUrl: config.apiUrl,
            note: 'locationId and publishableKey will be received via postMessage'
        });

        // HighLevel allowed origins for postMessage security
        // NOTE: HighLevel supports custom domains (e.g., link.sinirsizmusteri.com)
        // So we can't rely solely on origin matching
        const HIGHLEVEL_ORIGINS = [
            'https://app.gohighlevel.com',
            'https://app.msgsndr.com',
            'https://builder.gohighlevel.com'
        ];

        // HighLevel custom domain patterns
        const HIGHLEVEL_PATTERNS = [
            'gohighlevel',
            'msgsndr',
            'leadconnector'
        ];

        let paymentInitialized = false;

        // DOM elements
        const loading = document.getElementById('loading');
        const errorDiv = document.getElementById('error');
        const paymentFrame = document.getElementById('paymentFrame');

        // Notify parent that payment iframe is ready
        function notifyParentReady() {
            if (window.parent && window.parent !== window) {
                const message = {
                    type: 'custom_provider_ready',
                    loaded: true,
                    addCardOnFileSupported: true // PayTR supports card storage
                };

                // HighLevel may expect JSON string instead of object
                window.parent.postMessage(JSON.stringify(message), '*');
                console.log('Sent custom_provider_ready to parent:', message);
            }
        }

        // Notify parent of payment success
        function notifyPaymentSuccess(chargeId) {
            if (window.parent && window.parent !== window) {
                const message = {
                    type: 'custom_element_success_response',
                    chargeId: chargeId,
                    transactionId: config.transactionId
                };

                console.log('Sending payment success to parent:', message);
                window.parent.postMessage(JSON.stringify(message), '*');
            }
        }

        // Notify parent of payment error
        function notifyPaymentError(errorMessage) {
            if (window.parent && window.parent !== window) {
                const message = {
                    type: 'custom_element_error_response',
                    error: {
                        description: errorMessage
                    }
                };

                console.log('Sending payment error to parent:', message);
                window.parent.postMessage(JSON.stringify(message), '*');
            }
        }

        // Notify parent of payment cancellation
        function notifyPaymentClosed() {
            if (window.parent && window.parent !== window) {
                const message = {
                    type: 'custom_element_close_response'
                };

                console.log('Sending payment closed to parent:', message);
                window.parent.postMessage(JSON.stringify(message), '*');
            }
        }

        // Handle payment initiation from HighLevel
        function handlePaymentInitiation(paymentData) {
            if (paymentInitialized) {
                console.warn('Payment already initialized');
                return;
            }

            console.log('✅ Received payment_initiate_props from HighLevel:', paymentData);
            paymentInitialized = true;

            // Extract all required payment data from HighLevel
            // publishableKey: This is OUR key that we sent to HighLevel in connectConfig,
            // now HighLevel is sending it BACK to us for authentication
            config.publishableKey = paymentData.publishableKey;
            config.locationId = paymentData.locationId;
            config.amount = paymentData.amount;
            // HighLevel sends currency in lowercase, normalize to uppercase
            config.currency = (paymentData.currency || 'TRY').toUpperCase();
            config.transactionId = paymentData.transactionId;

            console.log('Payment data extracted:', {
                hasPublishableKey: !!config.publishableKey,
                publishableKeyLast8: config.publishableKey ? '***' + config.publishableKey.slice(-8) : 'N/A',
                hasLocationId: !!config.locationId,
                locationId: config.locationId,
                amount: config.amount,
                currency: config.currency,
                transactionId: config.transactionId,
                orderId: paymentData.orderId,
                contactEmail: paymentData.contact?.email
            });

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
            // Validate required fields from HighLevel
            if (!config.publishableKey) {
                console.error('❌ Cannot initialize: publishableKey missing from payment_initiate_props');
                loading.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = 'Authentication error: Publishable Key not received from HighLevel';
                notifyPaymentError('Publishable Key not provided by HighLevel');
                return;
            }

            if (!config.locationId) {
                console.error('❌ Cannot initialize: locationId missing from payment_initiate_props');
                loading.style.display = 'none';
                errorDiv.style.display = 'block';
                errorDiv.querySelector('p').textContent = 'Configuration error: Location ID not received from HighLevel';
                notifyPaymentError('Location ID not provided by HighLevel');
                return;
            }

            console.log('✅ All required data present, initializing payment...');
            loading.style.display = 'flex';
            errorDiv.style.display = 'none';

            const requestData = {
                amount: paymentData.amount,
                // HighLevel sends currency in lowercase, normalize to uppercase
                currency: (paymentData.currency || 'TRY').toUpperCase(),
                email: paymentData.contact?.email || paymentData.email,
                transactionId: paymentData.transactionId,
                contactId: paymentData.contact?.id || paymentData.contactId,
                orderId: paymentData.orderId,
                subscriptionId: paymentData.subscriptionId,
                mode: paymentData.mode || 'payment',
                user_name: paymentData.contact?.name || 'Customer',
                // HighLevel uses "contact.contact" not "contact.phone"
                user_phone: paymentData.contact?.contact || paymentData.contact?.phone || '0000000000',
                user_ip: '{{ request()->ip() }}',
            };

            console.log('Calling /api/payments/initialize with data:', requestData);
            console.log('Request headers:', {
                publishableKey: config.publishableKey ? '***' + config.publishableKey.slice(-8) : 'N/A',
                locationId: config.locationId
            });

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

            // Start polling ONLY after PayTR iframe is loaded
            startPolling();
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

            // Parse message data - handle both JSON string and object
            let data;
            try {
                // If data is a string, try to parse it as JSON
                if (typeof event.data === 'string') {
                    data = JSON.parse(event.data);
                    console.log('Parsed JSON string message:', data);
                } else {
                    // Data is already an object
                    data = event.data;
                }
            } catch (e) {
                console.warn('Failed to parse postMessage data:', e, event.data);
                return;
            }

            // Check if message is from HighLevel (including custom domains)
            const isFromHighLevel = HIGHLEVEL_ORIGINS.includes(event.origin) ||
                                   HIGHLEVEL_PATTERNS.some(pattern => event.origin.includes(pattern));

            // Handle messages based on TYPE rather than just origin
            // This allows HighLevel custom domains to work
            if (data.type === 'payment_initiate_props') {
                console.log('✅ Detected payment_initiate_props event');
                console.log('Origin:', event.origin);
                console.log('Is from known HighLevel origin:', isFromHighLevel);

                // Accept payment_initiate_props from any origin that provides valid data
                // This is safe because we validate publishableKey on backend
                handlePaymentInitiation(data);
                return;
            }

            // Handle messages from PayTR iframe (strict origin check)
            if (event.origin === 'https://www.paytr.com') {
                if (data.type === 'payment_success') {
                    notifyPaymentSuccess(data.chargeId || config.merchantOid);
                } else if (data.type === 'payment_error') {
                    notifyPaymentError(data.error || 'Payment failed');
                } else if (data.type === 'payment_cancelled') {
                    notifyPaymentClosed();
                }
                return;
            }

            // Log unknown message types for debugging
            console.log('Received message with unknown type:', data.type, 'from origin:', event.origin);
        });

        // Poll payment status (fallback mechanism)
        let pollCount = 0;
        const maxPollCount = 60; // 5 minutes with 5-second intervals
        let pollingStarted = false;

        function pollPaymentStatus() {
            // Don't poll if payment hasn't been initialized yet
            if (!config.merchantOid && !config.transactionId) {
                console.log('Skipping poll - no payment initialized yet');
                return;
            }

            if (pollCount >= maxPollCount) {
                notifyPaymentError('Payment timeout');
                return;
            }

            console.log('Polling payment status:', {
                merchantOid: config.merchantOid,
                transactionId: config.transactionId,
                pollCount: pollCount
            });

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

        // Function to start polling (called after PayTR iframe loads)
        function startPolling() {
            if (pollingStarted) {
                return;
            }
            pollingStarted = true;
            console.log('Starting payment status polling...');
            setTimeout(pollPaymentStatus, 10000); // Start after 10 seconds
        }

        // Handle page visibility change (user switches tabs)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && paymentFrame.style.display === 'block' && config.merchantOid) {
                // Page became visible again, check payment status
                console.log('Page visible again, checking payment status');
                pollPaymentStatus();
            }
        });

        // Send ready notification immediately when page loads
        // DON'T wait for iframe to load - HighLevel needs to know we're ready first
        notifyParentReady();

        // Set a timeout to warn if payment_initiate_props doesn't arrive
        // This helps identify testing issues (direct browser access vs iframe embed)
        setTimeout(function() {
            if (!paymentInitialized) {
                console.warn('⚠️ payment_initiate_props not received after 30 seconds');
                console.warn('This usually means:');
                console.warn('1. You are accessing /payments/page directly (not embedded in HighLevel)');
                console.warn('2. HighLevel failed to send payment data');
                console.warn('3. postMessage communication is blocked');
                console.warn('');
                console.warn('To test properly:');
                console.warn('- Open /test-payment.html (mock parent simulator)');
                console.warn('- OR test via real HighLevel payment flow');

                // Show user-friendly message
                const amountDiv = document.querySelector('.amount');
                const transactionDiv = document.querySelector('.transaction-id');
                if (amountDiv) {
                    amountDiv.textContent = 'Waiting for payment data...';
                }
                if (transactionDiv) {
                    transactionDiv.textContent = 'This page must be opened from HighLevel';
                    transactionDiv.style.color = '#e74c3c';
                }
            }
        }, 30000);

        // The PayTR iframe will be loaded AFTER we receive payment_initiate_props from HighLevel
        // and successfully call /api/payments/initialize
    </script>
</body>
</html>