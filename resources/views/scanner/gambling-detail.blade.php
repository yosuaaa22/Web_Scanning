<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gambling Analysis Detail - {{ $scanResult->url }}</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <style>
        :root {
            --primary-bg: #0f172a;
            --secondary-bg: #1e293b;
            --accent-color: #38bdf8;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
        }

        body {
            background: linear-gradient(135deg, var(--primary-bg), var(--secondary-bg));
            color: var(--text-primary);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
        }

        .card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 2rem;
            border-left: 2px solid var(--accent-color);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            background: var(--accent-color);
            border-radius: 50%;
        }
    </style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header with back button -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <a href="{{ url()->previous() }}" class="inline-flex items-center text-blue-500 hover:text-blue-400">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Scan Results
                </a>
                <h1 class="text-3xl font-bold mt-2">Gambling Detection Details</h1>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-400">Scan Time</p>
                <p class="font-mono">{{ $scanResult->scan_time->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>

        <!-- Risk Score Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="card p-6">
                <h3 class="text-sm text-gray-400 mb-1">Risk Level</h3>
                <div class="text-3xl font-bold {{ 
                    $details->risk_level === 'Tinggi' ? 'text-red-500' : 
                    ($details->risk_level === 'Sedang' ? 'text-yellow-500' : 'text-green-500') 
                }}">
                    {{ $details->risk_level }}
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-sm text-gray-400 mb-1">Confidence Score</h3>
                <div class="text-3xl font-bold text-blue-400">
                    {{ number_format($details->confidence_score, 1) }}%
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-sm text-gray-400 mb-1">Keywords Detected</h3>
                <div class="text-3xl font-bold text-purple-400">
                    {{ count($details->analysis->content->gambling_keywords ?? []) }}
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-sm text-gray-400 mb-1">Pattern Matches</h3>
                <div class="text-3xl font-bold text-indigo-400">
                    {{ count($details->analysis->content->betting_patterns ?? []) }}
                </div>
            </div>
        </div>

        <!-- Analysis Charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-4">Content Analysis</h3>
                <div class="h-64">
                    <canvas id="contentAnalysisChart"></canvas>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-4">Pattern Distribution</h3>
                <div class="h-64">
                    <canvas id="patternDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detection Timeline -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold mb-6">Detection Timeline</h3>
            <div class="space-y-6">
                @if(!empty($details->analysis->content->gambling_keywords))
                <div class="timeline-item">
                    <h4 class="text-lg font-medium text-blue-400">Gambling Keywords</h4>
                    <div class="mt-4 space-y-3">
                        @foreach($details->analysis->content->gambling_keywords as $keyword => $matches)
                        <div class="bg-gray-800 rounded-lg p-4">
                            <p class="text-yellow-300 font-medium mb-2">{{ $keyword }}</p>
                            <ul class="list-disc list-inside text-sm text-gray-300">
                                @foreach($matches as $match)
                                <li>{{ $match }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(!empty($details->analysis->content->betting_patterns))
                <div class="timeline-item">
                    <h4 class="text-lg font-medium text-blue-400">Betting Patterns</h4>
                    <div class="mt-4 space-y-3">
                        @foreach($details->analysis->content->betting_patterns as $pattern => $matches)
                        <div class="bg-gray-800 rounded-lg p-4">
                            <p class="text-red-300 font-medium mb-2">{{ $pattern }}</p>
                            <ul class="list-disc list-inside text-sm text-gray-300">
                                @foreach($matches as $match)
                                <li>{{ $match }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                @if(!empty($details->analysis->technical->hidden_elements))
                <div class="timeline-item">
                    <h4 class="text-lg font-medium text-blue-400">Hidden Elements</h4>
                    <div class="mt-4 space-y-3">
                        @foreach($details->analysis->technical->hidden_elements as $element)
                        <div class="bg-gray-800 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-eye-slash text-yellow-400 mt-1 mr-2"></i>
                                <div>
                                    <p class="text-gray-200">{{ $element->description }}</p>
                                    @if(!empty($element->code))
                                    <pre class="mt-2 p-2 bg-gray-900 rounded text-xs overflow-x-auto"><code>{{ $element->code }}</code></pre>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Risk Factors -->
        <div class="card p-6">
            <h3 class="text-xl font-semibold mb-6">Risk Factors</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-lg font-medium text-blue-400 mb-4">High Impact Factors</h4>
                    <ul class="space-y-3">
                        @foreach($details->risk_factors->high_impact ?? [] as $factor)
                        <li class="flex items-start bg-red-900 bg-opacity-20 rounded-lg p-4">
                            <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                            <span>{{ $factor }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 class="text-lg font-medium text-blue-400 mb-4">Contributing Factors</h4>
                    <ul class="space-y-3">
                        @foreach($details->risk_factors->contributing ?? [] as $factor)
                        <li class="flex items-start bg-yellow-900 bg-opacity-20 rounded-lg p-4">
                            <i class="fas fa-exclamation-circle text-yellow-500 mt-1 mr-3"></i>
                            <span>{{ $factor }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Content Analysis Chart
            const contentCtx = document.getElementById('contentAnalysisChart').getContext('2d');
            new Chart(contentCtx, {
                type: 'radar',
                data: {
                    labels: ['Keywords', 'Patterns', 'Links', 'Images', 'Text Content'],
                    datasets: [{
                        label: 'Risk Score',
                        data: [
                            {{ $details->analysis->content->keyword_score ?? 0 }},
                            {{ $details->analysis->content->pattern_score ?? 0 }},
                            {{ $details->analysis->content->link_score ?? 0 }},
                            {{ $details->analysis->content->image_score ?? 0 }},
                            {{ $details->analysis->content->text_score ?? 0 }}
                        ],
                        backgroundColor: 'rgba(56, 189, 248, 0.2)',
                        borderColor: 'rgba(56, 189, 248, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(56, 189, 248, 1)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#f1f5f9'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Pattern Distribution Chart
            const patternData = @json($details->pattern_distribution ?? []);
            const patternCtx = document.getElementById('patternDistributionChart').getContext('2d');
            new Chart(patternCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(patternData),
                    datasets: [{
                        label: 'Occurrences',
                        data: Object.values(patternData),
                        backgroundColor: 'rgba(139, 92, 246, 0.8)',
                        borderColor: 'rgba(139, 92, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#f1f5f9'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>