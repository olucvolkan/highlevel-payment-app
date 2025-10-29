@extends('layouts.landing')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">PayTR Configuration</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        <span class="font-medium">Location ID:</span>
                        <span class="font-mono bg-gray-100 px-2 py-1 rounded">{{ $locationId }}</span>
                    </p>
                </div>
                <div>
                    @if($isConfigured)
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Configured
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Not Configured
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Alpine.js Component -->
        <div x-data="paytrSetup()" x-init="loadCurrentConfig()">
            <!-- Configuration Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">PayTR Credentials</h2>
                <p class="text-sm text-gray-600 mb-6">
                    Enter your PayTR merchant credentials. You can find these in your PayTR Merchant Panel.
                </p>

                <form @submit.prevent="saveCredentials" class="space-y-6">
                    <!-- Merchant ID -->
                    <div>
                        <label for="merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant ID <span class="text-red-500">*</span>
                        </label>
                        <input
                            x-model="credentials.merchant_id"
                            type="text"
                            id="merchant_id"
                            :disabled="testing || saving"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Enter your PayTR Merchant ID"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Your unique merchant identifier from PayTR
                        </p>
                    </div>

                    <!-- Merchant Key -->
                    <div>
                        <label for="merchant_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Key <span class="text-red-500">*</span>
                        </label>
                        <input
                            x-model="credentials.merchant_key"
                            type="password"
                            id="merchant_key"
                            :disabled="testing || saving"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed font-mono"
                            placeholder="Enter your PayTR Merchant Key"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Your secret merchant key (kept encrypted)
                        </p>
                    </div>

                    <!-- Merchant Salt -->
                    <div>
                        <label for="merchant_salt" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Salt <span class="text-red-500">*</span>
                        </label>
                        <input
                            x-model="credentials.merchant_salt"
                            type="password"
                            id="merchant_salt"
                            :disabled="testing || saving"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent disabled:bg-gray-100 disabled:cursor-not-allowed font-mono"
                            placeholder="Enter your PayTR Merchant Salt"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            Your secret salt value for hash generation
                        </p>
                    </div>

                    <!-- Test Mode Toggle -->
                    <div>
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input
                                x-model="credentials.test_mode"
                                type="checkbox"
                                class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                            />
                            <span class="text-sm font-medium text-gray-700">Enable Test Mode</span>
                        </label>
                        <p class="mt-1 ml-7 text-xs text-gray-500">
                            Use PayTR sandbox for testing (recommended for development)
                        </p>
                    </div>

                    <!-- Test Result Alert -->
                    <div x-show="testResult"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 transform scale-95"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         :class="testResult?.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                         class="p-4 rounded-lg border">
                        <div class="flex items-start">
                            <svg x-show="testResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="!testResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium" x-text="testResult?.message"></p>
                                <p x-show="testResult?.details" class="text-xs mt-1" x-text="testResult?.details"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center space-x-4 pt-4 border-t border-gray-200">
                        <button
                            @click.prevent="testCredentials"
                            type="button"
                            :disabled="testing || !isFormValid"
                            class="inline-flex items-center px-6 py-3 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg x-show="!testing" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <svg x-show="testing" class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!testing">Test Credentials</span>
                            <span x-show="testing">Testing...</span>
                        </button>

                        <button
                            type="submit"
                            :disabled="saving || !isFormValid"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <svg x-show="!saving" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            <svg x-show="saving" class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-show="!saving">Save Configuration</span>
                            <span x-show="saving">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Current Configuration (if exists) -->
            <div x-show="isConfigured && currentConfig"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform translate-y-4"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Configuration</h2>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Merchant ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono" x-text="currentConfig?.merchant_id"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Test Mode</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span x-show="currentConfig?.test_mode" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                Enabled
                            </span>
                            <span x-show="!currentConfig?.test_mode" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Production
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Configured At</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="formatDate(currentConfig?.configured_at)"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Active
                            </span>
                        </dd>
                    </div>
                </dl>

                <button
                    @click="removeConfiguration"
                    class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-lg text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Remove Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function paytrSetup() {
    return {
        credentials: {
            merchant_id: '',
            merchant_key: '',
            merchant_salt: '',
            test_mode: true
        },
        testing: false,
        saving: false,
        testResult: null,
        currentConfig: null,
        isConfigured: {{ $isConfigured ? 'true' : 'false' }},

        get isFormValid() {
            return this.credentials.merchant_id &&
                   this.credentials.merchant_key &&
                   this.credentials.merchant_salt;
        },

        async loadCurrentConfig() {
            if (!this.isConfigured) return;

            try {
                const response = await fetch('/paytr/config?location_id={{ $locationId }}', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.currentConfig = data.config;
                }
            } catch (error) {
                console.error('Failed to load config:', error);
            }
        },

        async testCredentials() {
            this.testing = true;
            this.testResult = null;

            try {
                const response = await fetch('/paytr/test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        ...this.credentials,
                        location_id: '{{ $locationId }}'
                    })
                });

                const data = await response.json();
                this.testResult = data;

                // Clear test result after 10 seconds
                setTimeout(() => {
                    this.testResult = null;
                }, 10000);
            } catch (error) {
                this.testResult = {
                    success: false,
                    message: 'Test failed',
                    details: error.message
                };
            } finally {
                this.testing = false;
            }
        },

        async saveCredentials() {
            this.saving = true;

            try {
                const response = await fetch('/paytr/credentials', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        ...this.credentials,
                        location_id: '{{ $locationId }}'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Configuration saved successfully!');
                    window.location.reload();
                } else {
                    alert('❌ Save failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('❌ Save failed: ' + error.message);
            } finally {
                this.saving = false;
            }
        },

        async removeConfiguration() {
            if (!confirm('Are you sure you want to remove PayTR configuration? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('/paytr/config?location_id={{ $locationId }}', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Configuration removed successfully!');
                    window.location.reload();
                } else {
                    alert('❌ Remove failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('❌ Remove failed: ' + error.message);
            }
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('tr-TR', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
    }
}
</script>
@endsection
