<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $website->name }} - Monitoring</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #4f46e5;
            --success-color: #10b981;
            --danger-color: #ef4444;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .security-card {
            @apply bg-white rounded-xl p-6 border border-gray-200 transition-all duration-200;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .security-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .status-badge {
            @apply px-3 py-1 rounded-full text-sm font-medium;
        }
        [x-cloak] { display: none !important; }
        .dropdown-enter-active, .dropdown-leave-active {
            transition: all 0.3s ease;
        }
        .dropdown-enter-from, .dropdown-leave-to {
            opacity: 0;
            transform: translateY(-10px);
        }
    </style>
</head>
<body class="antialiased" x-data="{ 
    openDetails: true, 
    openHeaders: true,
    openRecommendations: true,
    openVulnerabilities: true 
}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <header class="mb-8">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <a href="{{ route('websites.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                {{ $website->name }}
                                <span class="text-indigo-600 text-lg">//{{ parse_url($website->url, PHP_URL_HOST) }}</span>
                            </h1>
                            <a href="{{ $website->url }}" target="_blank" class="text-gray-500 hover:text-indigo-600 inline-flex items-center mt-1">
                                {{ Str::limit($website->url, 40) }}
                                <i class="fas fa-external-link-alt ml-2 text-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form action="{{ route('websites.scan', $website->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Scan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Security Overview Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Security Score Card -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-shield-alt text-indigo-500 mr-2"></i>
                        Skor Keamanan
                    </h3>
                    <div class="radial-progress text-indigo-600" 
                         style="--value:{{ ($securityAnalysis['overall_score']/200)*100 }};">
                        {{ round(($securityAnalysis['overall_score']/200)*100) }}%
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold">{{ $securityAnalysis['overall_score'] }}/200</p>
                    <p class="text-sm text-gray-500 mt-1">Total Skor Keamanan</p>
                </div>
            </div>

            <!-- SSL Status Card -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-lock text-emerald-500 mr-2"></i>
                        Status SSL
                    </h3>
                    @php
                        $sslValid = $website->sslDetails->is_valid ?? false;
                        $sslStatus = $sslValid ? 'Valid' : 'Invalid';
                        $statusColor = $sslValid ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800';
                    @endphp
                    <span class="status-badge {{ $statusColor }}">{{ $sslStatus }}</span>
                </div>
                <div class="space-y-2">
                    <p class="text-sm">
                        <span class="font-medium">Issuer:</span> 
                        {{ $website->sslDetails->issuer ?? 'Unknown' }}
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">Kadaluarsa:</span> 
                        {{ $website->sslDetails->valid_to?->format('d M Y') ?? 'N/A' }}
                    </p>
                </div>
            </div>

            <!-- Uptime Status Card -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                        Status Uptime
                    </h3>
                    @php
                        $statusColor = $website->status === 'up' 
                            ? 'bg-emerald-100 text-emerald-800' 
                            : 'bg-rose-100 text-rose-800';
                    @endphp
                    <span class="status-badge {{ $statusColor }}">
                        {{ strtoupper($website->status) }}
                    </span>
                </div>
                <div class="space-y-2">
                    <p class="text-sm">
                        <span class="font-medium">Uptime 24h:</span> 
                        {{ $uptimeStats['uptime_24h'] }}%
                    </p>
                    <p class="text-sm">
                        <span class="font-medium">Terakhir Check:</span> 
                        {{ $website->last_checked?->diffForHumans() ?? 'Never' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Security Details Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Security Breakdown -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-clipboard-list text-purple-500 mr-2"></i>
                        Detail Keamanan
                    </h3>
                    <button @click="openDetails = !openDetails" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-chevron-down" x-show="!openDetails"></i>
                        <i class="fas fa-chevron-up" x-show="openDetails"></i>
                    </button>
                </div>
                <div x-cloak x-show="openDetails" class="space-y-3">
                    @foreach($securityAnalysis['score_breakdown'] as $category => $score)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            @if($score > 0)
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            @else
                                <i class="fas fa-times-circle text-rose-500"></i>
                            @endif
                            <span class="capitalize">{{ str_replace('_', ' ', $category) }}</span>
                        </div>
                        <span class="font-medium">{{ $score }} pts</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Security Headers -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-heading text-amber-500 mr-2"></i>
                        Header Keamanan
                    </h3>
                    <button @click="openHeaders = !openHeaders" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-chevron-down" x-show="!openHeaders"></i>
                        <i class="fas fa-chevron-up" x-show="openHeaders"></i>
                    </button>
                </div>
                <div x-cloak x-show="openHeaders" class="grid grid-cols-1 gap-2">
                    @foreach($securityAnalysis['headers'] as $header => $data)
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-2">
                            @if($data['exists'])
                                <i class="fas fa-check-circle text-emerald-500"></i>
                            @else
                                <i class="fas fa-times-circle text-rose-500"></i>
                            @endif
                            <code class="text-sm">{{ $header }}</code>
                        </div>
                        @if(!$data['exists'])
                            <span class="text-xs text-rose-600">Missing</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recommendations & Vulnerabilities Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Recommendations Dropdown -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                        Rekomendasi Keamanan
                    </h3>
                    <button @click="openRecommendations = !openRecommendations" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-chevron-down" x-show="!openRecommendations"></i>
                        <i class="fas fa-chevron-up" x-show="openRecommendations"></i>
                    </button>
                </div>
                <div x-cloak x-show="openRecommendations" 
                     x-transition:enter="dropdown-enter"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     class="space-y-3">
                    @forelse($recommendations as $recommendation)
                    <div class="flex items-start p-3 bg-gray-50 rounded-lg transition-all hover:bg-gray-100">
                        <div class="flex-shrink-0 mt-1 mr-3">
                            {!! match(true) {
                                str_contains($recommendation, '🚨') => '<i class="fas fa-exclamation-circle text-red-500"></i>',
                                str_contains($recommendation, '⏳') => '<i class="fas fa-clock text-orange-500"></i>',
                                default => '<i class="fas fa-info-circle text-blue-500"></i>'
                            } !!}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-800">{!! $recommendation !!}</p>
                        </div>
                    </div>
                    @empty
                    <div class="bg-green-50 p-4 rounded-lg text-green-800">
                        <i class="fas fa-check-circle mr-2"></i>
                        Website memenuhi semua standar keamanan dasar
                    </div>
                    @endforelse
                </div>
            </div>

            <!-- Vulnerabilities Dropdown -->
            <div class="security-card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="fas fa-bug text-rose-500 mr-2"></i>
                        Kerentanan Terdeteksi
                    </h3>
                    <button @click="openVulnerabilities = !openVulnerabilities" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-chevron-down" x-show="!openVulnerabilities"></i>
                        <i class="fas fa-chevron-up" x-show="openVulnerabilities"></i>
                    </button>
                </div>
                <div x-cloak x-show="openVulnerabilities" 
                     x-transition:enter="dropdown-enter"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     class="space-y-2">
                    @foreach($securityAnalysis['vulnerabilities'] as $vuln => $status)
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg hover:bg-gray-100">
                        <span class="text-sm">{{ $vuln }}</span>
                        <span class="status-badge {{ $status ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800' }}">
                            {{ $status ? 'Rentan' : 'Aman' }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Performance Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Response Time Chart -->
            <div class="security-card">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-stopwatch text-blue-500 mr-2"></i>
                    Riwayat Response Time
                </h3>
                <div class="w-full h-64">
                    <canvas id="responseTimeChart"></canvas>
                </div>
            </div>

            <!-- Uptime Distribution -->
            <div class="security-card">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <i class="fas fa-chart-pie text-purple-500 mr-2"></i>
                    Distribusi Uptime
                </h3>
                <div class="w-full h-64">
                    <canvas id="uptimeChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Response Time Chart
        const responseCtx = document.getElementById('responseTimeChart');
        new Chart(responseCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($responseTimes->keys()) !!},
                datasets: [{
                    label: 'Response Time (ms)',
                    data: {!! json_encode($responseTimes->values()) !!},
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.05)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: false,
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkip: true }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f3f4f6' },
                        title: { text: 'Milliseconds' }
                    }
                }
            }
        });

        // Uptime Distribution Chart
        const uptimeCtx = document.getElementById('uptimeChart');
        new Chart(uptimeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Up', 'Down', 'Unknown'],
                datasets: [{
                    data: [
                        {{ $stats['up'] }},
                        {{ $stats['down'] }},
                        {{ $stats['total'] - ($stats['up'] + $stats['down']) }}
                    ],
                    backgroundColor: ['#10b981', '#ef4444', '#e5e7eb'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const total = context.dataset.data.reduce((a,b) => a+b, 0);
                                const percent = ((context.raw/total)*100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>

    
</body>
</html>