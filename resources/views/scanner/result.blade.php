<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cosmic Security Scan Results</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent-color: #38bdf8;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --danger: #ef4444;
            --warning: #f59e0b;
            --success: #22c55e;
        }

        body {
            background: linear-gradient(135deg, var(--primary-bg), var(--secondary-bg));
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .risk-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        /* Animations */
        @keyframes cardEntrance {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .animate-card {
            animation: cardEntrance 0.6s ease-out forwards;
            opacity: 0;
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        .animate-delay-100 { animation-delay: 100ms; }
        .animate-delay-200 { animation-delay: 200ms; }

        .card:hover {
            transform: translateY(-2px);
        }

        .risk-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        .risk-high {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .risk-medium {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
            border: 1px solid var(--warning);
        }

        .risk-low {
            background: rgba(34, 197, 94, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .recommendation-card {
            border-left: 4px solid var(--accent-color);
            background: rgba(56, 189, 248, 0.1);
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0.5rem;
        }

        .details-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .details-section.active {
            max-height: 1000px;
        }

        .toggle-button {
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

      
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body class="p-4 md:p-8 bg-gradient-to-br from-gray-900 to-gray-800 text-white">
    <div class="max-w-7xl mx-auto animate-fade-in">
        <!-- Header Section -->
        <header class="text-center mb-8">
            <div class="inline-block bg-blue-600 text-white px-4 py-2 rounded-full mb-4 animate-float">
                <i class="fas fa-shield-alt mr-2 "></i>Scan Complete
            </div>
            <h1 class="text-4xl font-bold mb-2">Security Scan Results</h1>
            <p class="text-gray-400">Comprehensive analysis report for your URL</p>
        </header>
        
        

        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-1 gap-5 animate-card">
            <!-- Scan Overview Card -->
            <div class="card p-6 hover:scale-110 transition-all">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-info-circle mr-2 text-blue-400"></i>Scan Overview
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="stat-card">
                        <p class="text-sm text-gray-400">URL Scanned</p>
                        <p class="font-mono text-sm truncate">{{ $scanResult->url }}</p>
                    </div>
                    <div class="stat-card">
                        <p class="text-sm text-gray-400">Scan Time</p>
                        <p class="font-mono text-sm">{{ $scanResult->scan_time->format('Y-m-d H:i:s') }}</p>
                    </div>
                </div>
            </div>

            <!-- Risk Summary Card -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-chart-pie mr-2 text-purple-400"></i>Risk Summary
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="stat-card">
                        <p class="text-sm text-gray-400">Overall Risk Level</p>
                        <div
                            class="risk-indicator {{ $backdoorResult['risk_level'] == 'Tinggi' || $gamblingResult['risk_level'] == 'Tinggi'
                                ? 'risk-high'
                                : ($backdoorResult['risk_level'] == 'Sedang' || $gamblingResult['risk_level'] == 'Sedang'
                                    ? 'risk-medium'
                                    : 'risk-low') }}">
                            {{ max($backdoorResult['risk_level'], $gamblingResult['risk_level']) }}
                        </div>
                    </div>
                    <div class="stat-card">
                        <p class="text-sm text-gray-400">Confidence Score</p>
                        <div class="text-xl font-bold">
                            {{ number_format((($backdoorResult['confidence_level'] ?? ($backdoorResult['confidence_score'] ?? 0)) + ($gamblingResult['confidence_score'] ?? ($gamblingResult['confidence_level'] ?? 0))) / 2, 1) }}%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <!-- Network and Technical Analysis -->
        <div class="grid grid-cols-1 md:grid-cols-1 gap-5 animate-card">
            <div class="card p-6 mt-6 hover:scale-150 transition-all">
                <h2 class="text-xl font-semibold mb-4 flex items-center">
                    <i class="fas fa-network-wired mr-2 text-green-400"></i>
                    Network & Technical Analysis
                    <span class="ml-2 group relative">
                        <i class="fas fa-info-circle text-gray-500 cursor-pointer"></i>
                        <span class="tooltip w-64 left-full ml-2">
                            Detailed analysis of network indicators, hidden elements, and technical risks
                        </span>
                    </span>
                </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Network Indicators -->
                <div class="bg-gray-800 p-4 rounded-lg stat-card">
                    <h3 class="text-lg font-semibold mb-3 text-blue-400">
                        <i class="fas fa-globe-americas mr-2"></i>Network Indicators
                    </h3>
                    @if (!empty($networkAnalysis['suspicious_urls']))
                        <ul class="list-disc list-inside text-sm space-y-2">
                            @foreach ($networkAnalysis['suspicious_urls'] as $url)
                                <li class="text-yellow-300">{{ $url['full_url'] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-green-400">No suspicious network indicators found</p>
                    @endif
                </div>

                <!-- Hidden Elements -->
                <div class="bg-gray-800 p-4 rounded-lg stat-card">
                    <h3 class="text-lg font-semibold mb-3 text-red-400">
                        <i class="fas fa-eye-slash mr-2"></i>Hidden Elements
                    </h3>
                    @if (!empty($hiddenElements['css_hidden']))
                        <ul class="list-disc list-inside text-sm space-y-2">
                            @foreach ($hiddenElements['css_hidden'] as $element)
                                <li class="text-red-300">
                                    {{ $element['element'] }}
                                    <span class="text-xs text-gray-400">({{ $element['pattern_matched'] }})</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-green-400">No hidden elements detected</p>
                    @endif
                </div>
            </div>

            <!-- JavaScript & Redirect Analysis -->
            <div class="mt-4 bg-gray-800 p-4 rounded-lg stat-card">
                <h3 class="text-lg font-semibold mb-3 text-purple-400">
                    <i class="fas fa-code mr-2"></i>JavaScript & Redirect Analysis
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <h4 class="font-medium text-blue-300 mb-2">Suspicious JS Patterns</h4>
                        @if (!empty($jsAnalysis['suspicious_patterns']))
                            <ul class="list-disc list-inside text-sm space-y-1">
                                @foreach ($jsAnalysis['suspicious_patterns'] as $pattern)
                                    <li class="text-yellow-300">{{ $pattern['type'] }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-green-400">No suspicious JS patterns</p>
                        @endif
                    </div>

                    <div>
                        <h4 class="font-medium text-blue-300 mb-2">Suspicious Redirects</h4>
                        @if (!empty($redirectAnalysis['suspicious_redirects']))
                            <ul class="list-disc list-inside text-sm space-y-1">
                                @foreach ($redirectAnalysis['suspicious_redirects'] as $redirect)
                                    <li class="text-red-300">{{ $redirect['url'] }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-green-400">No suspicious redirects</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Flow Analysis -->
        <div class="card p-6 mt-6 hover:scale-120 transition-all">            
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-user-plus mr-2 text-blue-400"></i>
                Registration Flow Analysis
                <span class="ml-2 group relative">
                    <i class="fas fa-info-circle text-gray-500 cursor-pointer"></i>
                    <span class="tooltip w-64 left-full ml-2">
                        Detailed analysis of registration process and potential risks
                    </span>
                </span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 ">
                <div class="bg-gray-800 p-4 rounded-lg stat-card">
                    <h3 class="text-lg font-semibold mb-3 text-blue-400">
                        <i class="fas fa-file-alt mr-2"></i>Required Fields
                    </h3>
                    @if (!empty($registrationAnalysis['required_fields']))
                        <ul class="list-disc list-inside text-sm space-y-2">
                            @foreach ($registrationAnalysis['required_fields'] as $field)
                                <li>
                                    Type: <span class="text-blue-300">{{ $field['type'] }}</span>
                                    Name: <span class="text-green-300">{{ $field['name'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-green-400">No specific registration fields detected</p>
                    @endif
                </div>

                <div class="bg-gray-800 p-4 rounded-lg stat-card">
                    <h3 class="text-lg font-semibold mb-3 text-red-400">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Suspicious Elements
                    </h3>
                    @if (!empty($registrationAnalysis['suspicious_elements']))
                        <ul class="list-disc list-inside text-sm space-y-2">
                            @foreach ($registrationAnalysis['suspicious_elements'] as $element)
                                <li class="text-red-300">{{ $element['field'] }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-green-400">No suspicious registration elements found</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Detailed Analysis Section -->
        <!-- resources/views/scanner/result.blade.php -->

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Backdoor Analysis -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-bug mr-2 text-red-400"></i>Backdoor Analysis
                </h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Risk Level</span>
                        <div
                            class="risk-indicator {{ $backdoorResult['risk_level'] == 'Tinggi'
                                ? 'risk-high'
                                : ($backdoorResult['risk_level'] == 'Sedang'
                                    ? 'risk-medium'
                                    : 'risk-low') }}">
                            {{ $backdoorResult['risk_level'] }}
                        </div>
                    </div>
                    @if (!empty($backdoorResult['details']))
                        <div class="mt-4">
                            <a href="{{ route('backdoor.details') }}"
                                class="toggle-button flex justify-between items-center bg-blue-500 text-white px-4 py-2 rounded-lg">
                                <span>View Details</span>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Gambling Analysis -->
            <div class="card p-6">
                <h2 class="text-xl font-semibold mb-4">
                    <i class="fas fa-dice mr-2 text-yellow-400"></i>Gambling Analysis
                </h2>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Risk Level</span>
                        <div
                            class="risk-indicator {{ $gamblingResult['risk_level'] == 'Tinggi'
                                ? 'risk-high'
                                : ($gamblingResult['risk_level'] == 'Sedang'
                                    ? 'risk-medium'
                                    : 'risk-low') }}">
                            {{ $gamblingResult['risk_level'] }}
                        </div>
                    </div>
                    @if (!empty($gamblingResult['analysis']))
                        <div class="mt-4">
                            <a href="{{ route('gambling.details') }}"
                                class="toggle-button flex justify-between items-center bg-blue-500 text-white px-4 py-2 rounded-lg">
                                <span>View Details</span>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>


        <!-- AI Recommendations Section -->
        <div class="card p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">
                <i class="fas fa-robot mr-2 text-green-400"></i>AI Recommendations
            </h2>
            @if (is_array($aiRecommendation['recommendations']))
                @foreach ($aiRecommendation['recommendations'] as $recommendation)
                    <div class="recommendation-card">
                        <h3 class="font-semibold mb-2">
                            {{ is_array($recommendation) ? $recommendation['title'] : $recommendation }}</h3>
                        @if (is_array($recommendation) && !empty($recommendation['actions']))
                            <ul class="list-disc list-inside text-sm text-gray-300">
                                @foreach ($recommendation['actions'] as $action)
                                    <li>{{ $action }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="recommendation-card">
                    {{ $aiRecommendation }}
                </div>
            @endif
        </div>
        <!-- Action Buttons -->
        <div class="flex justify-center gap-4 mt-8">
            <a href="{{ route('scanner.index') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 animate-pulse-slow">
                <i class="fas fa-search mr-2"></i>Scan Another URL
            </a>
            <a href="{{ route('scanner.history') }}"
                class="bg-gray-700 hover:bg-gray-800 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                <i class="fas fa-history mr-2"></i>View Scan History
            </a>
        </div>
        <script>
            function toggleDetails(elementId) {
                const element = document.getElementById(elementId);
                element.classList.toggle('active');
                const button = element.previousElementSibling;
                const icon = button.querySelector('i');
                icon.classList.toggle('fa-chevron-up');
                icon.classList.toggle('fa-chevron-down');
            }
        </script>
</body>
