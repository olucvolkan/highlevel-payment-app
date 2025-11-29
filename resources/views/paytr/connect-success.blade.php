<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayTR Connected Successfully - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Success Icon -->
            <div class="flex justify-center">
                <div class="rounded-full bg-green-100 p-3">
                    <svg class="h-16 w-16 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            <!-- Header -->
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    PayTR Connected Successfully!
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Your PayTR merchant credentials have been saved and verified.
                </p>
            </div>

            <!-- Success Message -->
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">
                            What happens next?
                        </h3>
                        <div class="mt-2 text-sm text-green-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Your HighLevel account is now connected to PayTR</li>
                                <li>You can start accepting payments immediately</li>
                                <li>Payment transactions will appear in your PayTR dashboard</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Information Box -->
            <div class="rounded-md bg-blue-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Pro Tip:</strong> You can manage your PayTR settings anytime from the HighLevel Settings page or by visiting the <a href="{{ route('paytr.setup') }}" class="underline hover:text-blue-900">PayTR Setup page</a>.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div>
                <a
                    href="https://app.gohighlevel.com"
                    class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Return to HighLevel
                </a>
            </div>

            <!-- Help Text -->
            <div class="text-center">
                <p class="text-xs text-gray-500">
                    Need help? Visit our <a href="{{ route('docs') }}" class="font-medium text-indigo-600 hover:text-indigo-500">documentation</a> or contact support at <a href="mailto:support@yerelodeme.com" class="font-medium text-indigo-600 hover:text-indigo-500">support@yerelodeme.com</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-close this window after 5 seconds if opened in popup
        if (window.opener) {
            setTimeout(() => {
                window.close();
            }, 5000);
        }
    </script>
</body>
</html>
