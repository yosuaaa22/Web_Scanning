<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Analisis Backdoor - {{ $scanResult->url }}</title>
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
    </style>
</head>
<body class="p-4 md:p-8 bg-gray-900">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <a href="{{ url()->previous() }}" class="inline-flex items-center text-blue-500 hover:text-blue-400">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Hasil Pindai
                </a>
                
                <h1 class="text-3xl font-bold mt-2 text-white">Detail Deteksi Backdoor</h1>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-400">Waktu Pindai</p>
                <p class="font-mono text-white">{{ $scanResult->scan_time->format('Y-m-d H:i:s') }}</p>
            </div>
        </div>

        <!-- Metadata Scan -->
        <div class="card p-6 mb-8">
            <h3 class="text-xl font-semibold mb-4 text-blue-400">Informasi Metadata Scan</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-400">URL yang Dipindai</p>
                    <p class="font-bold text-white">{{ $scanResult->url }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Kode Respon</p>
                    <p class="font-bold text-white">{{ $details->metadata->response_code ?? 'Tidak Tersedia' }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Tipe Konten</p>
                    <p class="font-bold text-white">{{ $details->metadata->content_type ?? 'Tidak Tersedia' }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Timestamp Scan</p>
                    <p class="font-bold text-white">{{ date('Y-m-d H:i:s', $details->metadata->scan_timestamp ?? time()) }}</p>
                </div>
            </div>
        </div>

        <!-- Ringkasan Risiko -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Penilaian Risiko Keseluruhan -->
            <div class="card p-6 bg-gradient-to-br {{ 
                $overallRisk['level'] == 'Tinggi' ? 'from-red-600 to-red-900' : 
                ($overallRisk['level'] == 'Sedang' ? 'from-yellow-600 to-yellow-900' : 'from-green-600 to-green-900')
            }} text-white">
                <h3 class="text-xl font-bold mb-2">Penilaian Risiko Keseluruhan</h3>
                <div class="text-4xl font-extrabold mb-4">{{ $overallRisk['level'] }}</div>
                <p class="text-sm opacity-75">{{ $overallRisk['description'] }}</p>
                <div class="mt-4 text-lg">
                    Skor Risiko: <span class="font-bold">{{ $overallRisk['score'] }}/10</span>
                </div>
            </div>

            <!-- Distribusi Risiko -->
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-4 text-blue-400">Distribusi Risiko</h3>
                <div class="h-64">
                    <canvas id="riskDistributionChart"></canvas>
                </div>
            </div>

            <!-- Kategori Deteksi -->
            <div class="card p-6">
                <h3 class="text-xl font-semibold mb-4 text-blue-400">Kategori Deteksi</h3>
                <div class="h-64">
                    <canvas id="detectionCategoriesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Ringkasan Analisis -->
        <div class="card p-6 mb-8">
            <h3 class="text-2xl font-bold mb-6 text-blue-400">Ringkasan Analisis Keamanan</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-lg font-semibold text-blue-400 mb-3">Analisis Utama</h4>
                    <ul class="space-y-2">
                        @if(!empty($details->details->risks))
                            @foreach($details->details->risks as $category => $risks)
                                <li class="flex items-center">
                                    <i class="fas fa-shield-alt text-green-400 mr-2"></i>
                                    <span>{{ ucfirst(str_replace('_', ' ', $category)) }}: 
                                        {{ count((array)$risks) }} Temuan
                                    </span>
                                </li>
                            @endforeach
                        @else
                            <li class="text-gray-500">Tidak ada risiko signifikan terdeteksi</li>
                        @endif
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-semibold text-blue-400 mb-3">Detail Tambahan</h4>
                    <div class="bg-gray-800 p-4 rounded">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-400">Tingkat Kepercayaan</span>
                            <span class="font-bold text-white">{{ $details->confidence_level ?? 0 }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Indikator Risiko Unik</span>
                            <span class="font-bold text-white">{{ count($details->details->risks ?? []) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Temuan Keamanan Terperinci -->
        <div class="card p-6 mb-8">
            <h3 class="text-2xl font-bold mb-6 text-blue-400">Temuan Keamanan Terperinci</h3>
            @if(!empty($details->formatted_findings))
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach($details->formatted_findings as $category => $findings)
                        <div class="bg-gray-800 rounded-lg p-4">
                            <h4 class="text-xl font-semibold text-blue-400 mb-4">
                                {{ ucfirst(str_replace('_', ' ', $category)) }}
                            </h4>
                            @if(count($findings) > 0)
                                @foreach($findings as $finding)
                                    <div class="bg-gray-900 rounded-lg p-3 mb-3">
                                        <div class="flex justify-between items-start mb-2">
                                            <span class="text-sm text-gray-400">{{ $category }}</span>
                                            <span class="
                                                px-2 py-1 rounded text-xs font-bold
                                                {{ 
                                                    $finding['severity'] == 'high' ? 'bg-red-900 text-red-300' : 
                                                    ($finding['severity'] == 'medium' ? 'bg-yellow-900 text-yellow-300' : 'bg-green-900 text-green-300')
                                                }}
                                            ">
                                                {{ ucfirst($finding['severity']) }}
                                            </span>
                                        </div>
                                        <p class="text-gray-200 mb-2">
                                            {{ $finding['description'] ?? 'Tidak ada deskripsi tersedia' }}
                                        </p>
                                        @if(!empty($finding['code']))
                                            <pre class="bg-gray-700 p-2 rounded text-sm text-gray-300 overflow-x-auto max-h-40 mb-2">
                                                {{ $finding['code'] }}
                                            </pre>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <p class="text-gray-500">Tidak ada temuan spesifik dalam kategori ini.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400">Tidak ada temuan keamanan yang signifikan.</p>
            @endif
        </div>

        <!-- Rekomendasi Keamanan -->
        <div class="card p-6">
            <h3 class="text-2xl font-bold mb-6 text-blue-400">Rekomendasi Keamanan</h3>
            @if(!empty($details->formatted_recommendations))
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach($details->formatted_recommendations as $recommendation)
                        <div class="bg-blue-900 bg-opacity-20 border-l-4 border-blue-400 rounded p-4">
                            <h4 class="font-semibold text-blue-400 mb-2">
                                {{ $recommendation['title'] ?? 'Rekomendasi Umum' }}
                            </h4>
                            <div class="text-gray-300 mb-2">
                                Prioritas: 
                                <span class="
                                    px-2 py-1 rounded text-xs font-bold
                                    {{ 
                                        $recommendation['priority'] == 'high' ? 'bg-red-900 text-red-300' : 
                                        ($recommendation['priority'] == 'medium' ? 'bg-yellow-900 text-yellow-300' : 'bg-green-900 text-green-300')
                                    }}
                                ">
                                    {{ ucfirst($recommendation['priority']) }}
                                </span>
                            </div>
                            <ul class="space-y-2">
                                @foreach($recommendation['steps'] as $step)
                                    <li class="flex items-start">
                                        <i class="fas fa-check text-green-400 mt-1 mr-2"></i>
                                        <span>{{ $step }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400">Tidak ada rekomendasi keamanan tersedia.</p>
            @endif
        </div>
    </div>

    <!-- Grafik JavaScript -->
    <script>
        @if(isset($details) && $details->metrics)

        // Grafik Distribusi Risiko
        new Chart(document.getElementById('riskDistributionChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Risiko Tinggi', 'Risiko Sedang', 'Risiko Rendah'],
                datasets: [{
                    data: [
                        {{ $details->metrics->high_risk_count ?? 0}},
                        {{ $details->metrics->medium_risk_count ?? 0}},
                        {{ $details->metrics->low_risk_count ?? 0}}
                    ],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(34, 197, 94, 0.8)'
                    ],
                    borderColor: [
                        'rgba(239, 68, 68, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(34, 197, 94, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#f1f5f9',
                            padding: 20,
                            font: { size: 12 }
                        }
                    }
                }
            }
        });

        // Grafik Kategori Deteksi
        new Chart(document.getElementById('detectionCategoriesChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_keys($details->category_distribution)) !!},
                datasets: [{
                    label: 'Jumlah Deteksi',
                    data: {!! json_encode(array_values($details->category_distribution)) !!},
                    backgroundColor: 'rgba(56, 189, 248, 0.8)',
                    borderColor: 'rgba(56, 189, 248, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#f1f5f9' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#f1f5f9' }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        @endif
    </script>
</body>
</html>