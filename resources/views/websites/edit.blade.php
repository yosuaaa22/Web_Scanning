<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Website</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 p-6">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="globalLoading">
        <div class="text-center">
            <div class="loading-spinner inline-block w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full mb-4"></div>
            <p class="text-blue-600 font-semibold">Memproses...</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto">
        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center space-x-4">
                    <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-globe text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Edit Website</h1>
                        <p class="text-sm text-gray-500">Update your website monitoring settings</p>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form action="{{ route('websites.update', $website) }}" method="POST" class="p-6 space-y-6" id="editForm">
                @csrf
                @method('PUT')

                <!-- Website Name -->
                <div class="space-y-2">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <i class="fas fa-tag w-5 text-gray-400"></i>
                        <span>Website Name</span>
                    </label>
                    <div class="relative">
                        <input type="text" 
                               name="name" 
                               value="{{ old('name', $website->name) }}"
                               class="w-full pl-4 pr-10 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                               placeholder="Enter website name"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- URL -->
                <div class="space-y-2">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <i class="fas fa-link w-5 text-gray-400"></i>
                        <span>Website URL</span>
                    </label>
                    <div class="relative">
                        <input type="url" 
                               name="url" 
                               value="{{ old('url', $website->url) }}"
                               class="w-full pl-4 pr-10 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                               placeholder="https://example.com"
                               required>
                        @error('url')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Check Interval -->
                <div class="space-y-2">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <i class="fas fa-clock w-5 text-gray-400"></i>
                        <span>Check Interval</span>
                    </label>
                    <select name="check_interval" 
                            class="w-full pl-4 pr-10 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-white">
                        @foreach([
                            5 => '5 Minutes',
                            15 => '15 Minutes',
                            30 => '30 Minutes',
                            60 => '1 Hour'
                        ] as $value => $label)
                            <option value="{{ $value }}" 
                                    {{ $website->check_interval == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end space-x-4 pt-4 border-t border-gray-100">
                    <a href="{{ route('websites.index', $website) }}" 
                       class="px-6 py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors duration-200">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2.5 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200 flex items-center"
                            id="submitButton">
                        <i class="fas fa-save mr-2"></i>
                        <span class="button-text">Save Changes</span>
                        <i class="fas fa-spinner loading-spinner ml-2 hidden"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Last Updated Info -->
        <div class="mt-4 text-center text-sm text-gray-500">
            Last updated: {{ $website->updated_at->diffForHumans() }}
        </div>
    </div>

    <script>
        // Handle loading states
        const loadingOverlay = document.getElementById('globalLoading');
        const submitButton = document.getElementById('submitButton');
        const spinner = submitButton.querySelector('.loading-spinner');
        const buttonText = submitButton.querySelector('.button-text');

        // Form submission handler
        document.getElementById('editForm').addEventListener('submit', function(e) {
            spinner.classList.remove('hidden');
            buttonText.textContent = 'Menyimpan...';
            submitButton.disabled = true;
        });

        // Handle all link clicks
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if(this.href !== window.location.href) {
                    loadingOverlay.style.display = 'flex';
                }
            });
        });

        // Handle beforeunload
        window.addEventListener('beforeunload', function() {
            loadingOverlay.style.display = 'flex';
        });
    </script>
</body>
</html>