Integrating Iyzico and PayTR in a GoHighLevel Marketplace App

Iyzico and PayTR are popular Turkish online payment gateways that provide hosted payment forms which can be embedded in merchant sites. To use them in a GoHighLevel app, agency owners first obtain merchant accounts with Iyzico and PayTR and enter their credentials into the app’s settings. The app then uses these credentials to call the gateway APIs and display the payment forms via secure iFrames. This lets customers pay without leaving the site, while keeping credit card data off your servers. Below are the key steps and details for each integration, with excerpts from the official documentation.

1. Obtaining Merchant Credentials

Before integrating, the user (GoHighLevel agency) must sign up for each gateway and get API keys:
	•	Iyzico: Upon registration, Iyzico provides a merchant ID, API key, and secret key. These are used to authenticate API calls (e.g. CF‑Initialize).
	•	PayTR: On PayTR’s Merchant Panel, the main user/technical user can view the Merchant ID, Merchant Key, and Merchant Salt (secret key) needed for API calls ￼.

These credentials should be saved securely in the app’s configuration. The integration must perform the initial API calls on a secure server side (not in client JavaScript) to avoid exposing secrets ￼ ￼.

2. Iyzico CheckoutForm Integration via iFrame

Iyzico’s Checkout Form (CF) integration is a two-step API flow. The app first sends a CF‑Initialize request (POST) with the order details, price, callback URL, etc. Iyzico responds with a JSON containing a token and a paymentPageUrl ￼. This paymentPageUrl is the hosted payment page. To display it in an iFrame, append &iframe=true to that URL ￼ ￼. For example, if Iyzico returns:

"paymentPageUrl": "https://sandbox-cpp.iyzipay.com/?token=ABC123DEF"

you would open:

https://sandbox-cpp.iyzipay.com/?token=ABC123DEF&iframe=true

in an HTML <iframe> on your page. The documentation explicitly states that merchants wanting to display the Iyzico payment page as an iframe “should add &iframe=true at the end of URL which is sent within paymentPageUrl” ￼. This embeds the secure checkout form directly into your site.

After embedding, Iyzico shows the payment form (credit card input, etc) inside the iFrame. Once the customer completes payment, Iyzico will redirect the iFrame to the callback URL you provided and also send an IPN (Instant Payment Notification) to that URL. In other words, Iyzico “triggers [an] IPN (Instant Payment Notification) to [the] given callbackUrl” after payment ￼. The app must then verify the payment status (via Iyzico’s CF‑Retrieve API or via the IPN) to finalize the transaction.

3. PayTR iFrame API Integration

PayTR uses a similar two-step flow for its iFrame API. First, the app must call PayTR’s get-token endpoint on the server side. This is done by making a POST request with fields like merchant_id, merchant_oid (order ID), payment_amount, user_ip, etc. PayTR’s docs show a table of required fields (merchant credentials, order details, basket, etc.) that you must send to https://www.paytr.com/odeme/api/get-token ￼. On success, PayTR returns JSON with a token (often called iframe_token):

{"status":"success","token":"abcdef12345..."}

This token is then used to embed the payment form. PayTR’s documentation instructs: “The iframe_token received in the successful response … is used in the src attribute of iFrame.” ￼. In practice, you include an <iframe> in your page with src="https://www.paytr.com/odeme/guvenli/{token}", where {token} is the value from PayTR’s response. For example (from PayTR’s sample code):

<iframe src="https://www.paytr.com/odeme/guvenli/<?= $token ?>" id="paytriframe" 
        frameborder="0" scrolling="no" style="width:100%;"></iframe>

In the above, <?= $token ?> is the PHP variable holding the PayTR token. PayTR even provides a JavaScript snippet (iframeResizer.min.js) to auto-resize the frame. The docs explicitly show this example code block with the iFrame, confirming the token is inserted into the URL ￼. Once this iFrame is on the page, the user sees PayTR’s payment form inside it.

After the payment is completed (or fails), PayTR will send a server-to-server callback (to your merchant callback URL) with the result. The PayTR docs emphasize that “STEP 2 must be completed in order to receive the payment result (success/failed) and to confirm/cancel the order.” ￼. In practice, you set up an endpoint in your app that PayTR can POST to with the transaction outcome, then verify and finalize the order there.

4. Embedding Forms in the GoHighLevel App

In a GoHighLevel marketplace app, you would use a Custom Page or Web Widget to host the payment form. For example, your app can create a Custom Page (as shown in GoHighLevel docs) whose content is an HTML snippet containing the <iframe> tag. When the agency/admin user opens the app in GHL, that page loads and shows the iFrame. The app would typically collect order details (amount, description) via a form or parameters, then perform the gateway calls in the background and set the iframe src as described above.

Because both Iyzico and PayTR handle PCI compliance by hosting the form, your app never handles raw card data. You still need to secure your API calls. PayTR’s docs explicitly note that the get-token request is a server-side call (they say “This request occurs in the background (server-side) using the POST method” ￼). Similarly, Iyzico’s CF-Initialize should be done with your secret keys on a secure backend. In a serverless GoHighLevel app, you might use GHL’s built-in server-side function or an external API endpoint to make these calls safely.

5. Summary of Key Steps
	•	Account Setup: The agency enters their Iyzico and PayTR merchant credentials into the app’s settings.
	•	Iyzico Payment Flow: App sends CF‑Initialize to Iyzico → receives paymentPageUrl and token → app opens <iframe src="…&iframe=true"> with that URL ￼ ￼ → user pays inside the iFrame → Iyzico posts payment result to callback.
	•	PayTR Payment Flow: App sends required data to PayTR’s /get-token → receives token → app inserts token into the PayTR iframe URL (https://www.paytr.com/odeme/guvenli/{token}) ￼ → user pays in the iFrame → PayTR calls the callback URL with the result.
	•	Verification: In both cases, the app must handle the callback (or API check) to confirm payment success before granting the service or recording the sale ￼ ￼.

By following these documented processes, your GoHighLevel app can seamlessly embed Iyzico’s and PayTR’s secure payment forms via iFrames, allowing customers to pay without leaving your platform. Both gateways ensure that “payment security is ensured” and “customers are protected from fraud” (PayTR’s marketing page ￼), and they explicitly support embedded checkout forms for a smooth user experience. Properly implemented, this gives agency owners the ability to charge clients using Turkish payment methods directly through your app, with the payments handled by Iyzico and PayTR behind the scenes.

Sources: Official Iyzico and PayTR developer documentation and integration guides ￼ ￼ ￼ ￼ ￼ ￼.