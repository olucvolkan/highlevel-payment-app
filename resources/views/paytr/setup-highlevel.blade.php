<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PayTR Configuration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* HighLevel-style custom colors */
        :root {
            --hl-primary: #3b82f6;
            --hl-success: #10b981;
            --hl-warning: #f59e0b;
            --hl-error: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-5xl mx-auto p-6" x-data="paytrSetup()" x-init="init()">

        <!-- Header Section (like PayFast) -->
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900">PayTR Configuration</h1>
                    <p class="text-sm text-gray-600">Please update your test and live merchant settings to use the PayTR payment gateway.</p>
                    <!-- Location ID Display -->
                    <p x-show="locationId" class="text-xs text-green-600 mt-1">
                        <span class="font-medium">Location ID:</span> <span x-text="locationId"></span>
                    </p>
                </div>
            </div>

            <a href="https://www.paytr.com/" target="_blank" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 underline">
                Find Your PayTR Merchant Settings in your PayTR Account
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button
                    @click="activeTab = 'test'"
                    :class="activeTab === 'test' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Merchant Test Settings
                </button>
                <button
                    @click="activeTab = 'live'"
                    :class="activeTab === 'live' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Merchant Live Settings
                </button>
            </nav>
        </div>

        <!-- Test Settings Tab -->
        <div x-show="activeTab === 'test'" class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Merchant Test Settings</h2>
                    <p class="text-sm text-gray-600">Enter your PayTR Test Merchant Details Here.</p>
                    <a href="https://www.paytr.com/magaza/test" target="_blank" class="inline-block mt-2 text-sm text-blue-600 hover:text-blue-800 underline">
                        Signup To PayTR Sandbox Here
                    </a>
                </div>

                <form @submit.prevent="saveTestConfig" class="space-y-6">
                    <!-- Merchant ID -->
                    <div>
                        <label for="test_merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant ID
                        </label>
                        <input
                            x-model="testConfig.merchant_id"
                            type="text"
                            id="test_merchant_id"
                            :disabled="testSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="10043780"
                        />
                    </div>

                    <!-- Merchant Key -->
                    <div>
                        <label for="test_merchant_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Key
                        </label>
                        <input
                            x-model="testConfig.merchant_key"
                            type="password"
                            id="test_merchant_key"
                            :disabled="testSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="cm0nvbpygb6l5"
                        />
                    </div>

                    <!-- Merchant Salt -->
                    <div>
                        <label for="test_merchant_salt" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Salt
                        </label>
                        <input
                            x-model="testConfig.merchant_salt"
                            type="password"
                            id="test_merchant_salt"
                            :disabled="testSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="testpaytr123"
                        />
                    </div>

                    <!-- Test Result Alert -->
                    <div x-show="testResult" x-transition
                         :class="testResult?.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                         class="p-4 rounded-md border">
                        <div class="flex items-start">
                            <svg x-show="testResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="!testResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium" x-text="testResult?.message || testResult?.error"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 pt-4">
                        <button
                            @click.prevent="testCredentials('test')"
                            type="button"
                            :disabled="testTesting || !isTestFormValid"
                            class="px-6 py-2.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <span x-show="!testTesting">Test Connection</span>
                            <span x-show="testTesting">Testing...</span>
                        </button>

                        <button
                            type="submit"
                            :disabled="testSaving || !isTestFormValid"
                            class="px-8 py-2.5 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-green-400 to-green-500 hover:from-green-500 hover:to-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm">
                            <span x-show="!testSaving">Connect</span>
                            <span x-show="testSaving">Connecting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live Settings Tab -->
        <div x-show="activeTab === 'live'" class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Merchant Live Settings</h2>
                    <p class="text-sm text-gray-600">Enter your PayTR Production Merchant Details Here.</p>
                </div>

                <form @submit.prevent="saveLiveConfig" class="space-y-6">
                    <!-- Merchant ID -->
                    <div>
                        <label for="live_merchant_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant ID
                        </label>
                        <input
                            x-model="liveConfig.merchant_id"
                            type="text"
                            id="live_merchant_id"
                            :disabled="liveSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Your live merchant ID"
                        />
                    </div>

                    <!-- Merchant Key -->
                    <div>
                        <label for="live_merchant_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Key
                        </label>
                        <input
                            x-model="liveConfig.merchant_key"
                            type="password"
                            id="live_merchant_key"
                            :disabled="liveSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Your live merchant key"
                        />
                    </div>

                    <!-- Merchant Salt -->
                    <div>
                        <label for="live_merchant_salt" class="block text-sm font-medium text-gray-700 mb-2">
                            Merchant Salt
                        </label>
                        <input
                            x-model="liveConfig.merchant_salt"
                            type="password"
                            id="live_merchant_salt"
                            :disabled="liveSaving"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono disabled:bg-gray-100 disabled:cursor-not-allowed"
                            placeholder="Your live merchant salt"
                        />
                    </div>

                    <!-- Live Result Alert -->
                    <div x-show="liveResult" x-transition
                         :class="liveResult?.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
                         class="p-4 rounded-md border">
                        <div class="flex items-start">
                            <svg x-show="liveResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="!liveResult?.success" class="w-5 h-5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium" x-text="liveResult?.message || liveResult?.error"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-3 pt-4">
                        <button
                            @click.prevent="testCredentials('live')"
                            type="button"
                            :disabled="liveTesting || !isLiveFormValid"
                            class="px-6 py-2.5 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <span x-show="!liveTesting">Test Connection</span>
                            <span x-show="liveTesting">Testing...</span>
                        </button>

                        <button
                            type="submit"
                            :disabled="liveSaving || !isLiveFormValid"
                            class="px-8 py-2.5 border border-transparent text-sm font-medium rounded-md text-white bg-gradient-to-r from-green-400 to-green-500 hover:from-green-500 hover:to-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-sm">
                            <span x-show="!liveSaving">Connect</span>
                            <span x-show="liveSaving">Connecting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
    function paytrSetup() {
        return {
            activeTab: 'test',
            locationId: @json($locationId ?? ''),

            testConfig: {
                merchant_id: '',
                merchant_key: '',
                merchant_salt: ''
            },
            liveConfig: {
                merchant_id: '',
                merchant_key: '',
                merchant_salt: ''
            },

            testSaving: false,
            liveSaving: false,
            testTesting: false,
            liveTesting: false,
            testResult: null,
            liveResult: null,
            configLoaded: false,

            get isTestFormValid() {
                return this.testConfig.merchant_id &&
                       this.testConfig.merchant_key &&
                       this.testConfig.merchant_salt;
            },

            get isLiveFormValid() {
                return this.liveConfig.merchant_id &&
                       this.liveConfig.merchant_key &&
                       this.liveConfig.merchant_salt;
            },

            init() {
                // Location ID is provided by backend via Blade template
                console.log('✅ Location ID:', this.locationId);

                // Load existing config if we have location_id
                this.loadCurrentConfig();
            },

            async loadCurrentConfig() {
                // Don't load config if we don't have location_id yet
                if (!this.locationId) {
                    console.warn('⏳ Skipping config load - waiting for location_id from HighLevel...');
                    return;
                }

                // Mark as loaded to prevent duplicate loads
                this.configLoaded = true;

                console.log('📥 Loading config for location_id:', this.locationId);

                try {
                    const response = await fetch(`/paytr/config?location_id=${this.locationId}`);
                    if (response.ok) {
                        const data = await response.json();
                        console.log('Config loaded:', data);

                        if (data.configured) {
                            // Load existing config
                            if (data.test_mode) {
                                this.testConfig.merchant_id = data.merchant_id;
                                console.log('✅ Loaded test config');
                            } else {
                                this.liveConfig.merchant_id = data.merchant_id;
                                console.log('✅ Loaded live config');
                            }
                        } else {
                            console.log('ℹ️ No existing config found');
                        }
                    }
                } catch (error) {
                    console.error('Failed to load config:', error);
                }
            },

            async testCredentials(mode) {
                const config = mode === 'test' ? this.testConfig : this.liveConfig;
                const testMode = mode === 'test';

                if (mode === 'test') {
                    this.testTesting = true;
                    this.testResult = null;
                } else {
                    this.liveTesting = true;
                    this.liveResult = null;
                }

                try {
                    const response = await fetch('/paytr/test', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            ...config,
                            test_mode: testMode
                        })
                    });

                    const data = await response.json();

                    if (mode === 'test') {
                        this.testResult = data;
                    } else {
                        this.liveResult = data;
                    }

                    setTimeout(() => {
                        if (mode === 'test') {
                            this.testResult = null;
                        } else {
                            this.liveResult = null;
                        }
                    }, 10000);
                } catch (error) {
                    const errorResult = {
                        success: false,
                        error: 'Connection failed: ' + error.message
                    };

                    if (mode === 'test') {
                        this.testResult = errorResult;
                    } else {
                        this.liveResult = errorResult;
                    }
                } finally {
                    if (mode === 'test') {
                        this.testTesting = false;
                    } else {
                        this.liveTesting = false;
                    }
                }
            },

            async saveTestConfig() {
                await this.saveConfig('test');
            },

            async saveLiveConfig() {
                await this.saveConfig('live');
            },

            async saveConfig(mode) {
                // Validate location_id before saving
                if (!this.locationId) {
                    alert('❌ Error: Location ID not found. Please refresh the page and try again.');
                    return;
                }

                const config = mode === 'test' ? this.testConfig : this.liveConfig;
                const testMode = mode === 'test';

                if (mode === 'test') {
                    this.testSaving = true;
                } else {
                    this.liveSaving = true;
                }

                try {
                    console.log('Saving config with location_id:', this.locationId);

                    const response = await fetch('/paytr/credentials', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            ...config,
                            test_mode: testMode,
                            location_id: this.locationId
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('✅ Configuration saved successfully!');

                        // Send message to parent HighLevel window
                        if (window.parent) {
                            window.parent.postMessage({
                                type: 'paytr_configured',
                                mode: mode,
                                success: true
                            }, '*');
                        }
                    } else {
                        alert('❌ Save failed: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    alert('❌ Save failed: ' + error.message);
                } finally {
                    if (mode === 'test') {
                        this.testSaving = false;
                    } else {
                        this.liveSaving = false;
                    }
                }
            }
        }
    }
    </script>
</body>
</html>
