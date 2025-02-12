<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Monitoring Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .glass-nav {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(241, 245, 249, 0.8);
        }
        .status-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
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
<body class="antialiased">
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="globalLoading">
        <div class="text-center">
            <div class="loading-spinner inline-block w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full mb-4"></div>
            <p class="text-indigo-600 font-semibold">Memproses...</p>
        </div>
    </div>

    <div class="min-h-screen">
        <!-- Enhanced Navbar -->
        <nav class="glass-nav fixed w-full z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center space-x-3">
                        <div class="flex items-center space-x-3">
                            <a href="{{ route('scanner.result') }}" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <span class="text-xl font-bold text-slate-800 ml-2">MONITORING</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div id="realtime-clock" class="text-sm text-slate-500 font-medium">
                            <i class="fas fa-clock mr-1.5"></i>
                            <span>{{ now()->format('H:i:s') }}</span>
                        </div>
                        <a href="{{ route('websites.create') }}" class="bg-gradient-to-r from-indigo-500 to-blue-500 hover:from-indigo-600 hover:to-blue-600 text-white px-4 py-2.5 rounded-lg flex items-center space-x-2 transition-all shadow-sm hover:shadow-md">
                            <i class="fas fa-plus text-sm"></i>
                            <span>Add Website</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12">
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center space-x-2">
                    <i class="fas fa-check-circle text-emerald-600"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <!-- Card Container -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                <!-- Card Header -->
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-800">Monitored Websites</h2>
                    <div class="flex items-center space-x-3">
                        <div class="text-sm text-slate-500">{{ $websites->count() }} sites monitored</div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Website</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Checked</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            @forelse($websites as $website)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center space-x-4">
                                        <img src="https://www.google.com/s2/favicons?domain={{ $website->url }}" class="h-6 w-6 rounded-sm" alt="favicon">
                                        <div>
                                            <div class="font-medium text-slate-800">{{ $website->name }}</div>
                                            <a href="{{ $website->url }}" target="_blank" class="text-sm text-slate-500 hover:text-indigo-600 transition-colors">{{ Str::limit($website->url, 40) }}</a>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusConfig = [
                                            'up' => [
                                                'label' => 'Up',
                                                'bg' => 'bg-emerald-100',
                                                'text' => 'text-emerald-800',
                                                'icon' => 'fa-check-circle',
                                                'pulse' => false
                                            ],
                                            'offline' => [
                                                'label' => 'Offline',
                                                'bg' => 'bg-rose-100',
                                                'text' => 'text-rose-800',
                                                'icon' => 'fa-exclamation-triangle',
                                                'pulse' => true
                                            ],
                                            'pending' => [
                                                'label' => 'Pending',
                                                'bg' => 'bg-amber-100',
                                                'text' => 'text-amber-800',
                                                'icon' => 'fa-clock',
                                                'pulse' => true
                                            ],
                                            'error' => [
                                                'label' => 'Error',
                                                'bg' => 'bg-violet-100',
                                                'text' => 'text-violet-800',
                                                'icon' => 'fa-bug',
                                                'pulse' => true
                                            ]
                                        ];
                                        
                                        $status = strtolower($website->status);
                                        $config = $statusConfig[$status] ?? [
                                            'label' => 'Unknown',
                                            'bg' => 'bg-slate-100',
                                            'text' => 'text-slate-800',
                                            'icon' => 'fa-question-circle',
                                            'pulse' => false
                                        ];
                                    @endphp
                                    <div class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium {{ $config['bg'] }} {{ $config['text'] }}">
                                        <i class="fas {{ $config['icon'] }} text-xs mr-2 {{ $config['pulse'] ? 'status-pulse' : '' }}"></i>
                                        {{ $config['label'] }}
                                    </div>
                                </td>
                              
                                <td class="px-6 py-4">
                                    <div class="text-sm text-slate-600">
                                        @if($website->last_checked)
                                            <span class="tabular-nums">{{ $website->last_checked->diffForHumans() }}</span>
                                        @else
                                            <span class="text-slate-400">Never</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Tombol View -->
                                        <a href="{{ route('websites.show', $website->id) }}" 
                                           class="p-2.5 text-indigo-600 hover:bg-indigo-100 rounded-lg transition-colors duration-200"
                                           title="Detail">
                                            <i class="fas fa-eye w-4 h-4"></i>
                                        </a>
                                        
                                        <!-- Tombol Scan -->
                                        <form action="{{ route('websites.scan', $website->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" 
                                                    class="p-2.5 text-amber-500 hover:bg-amber-100 rounded-lg transition-colors duration-200 scan-button"
                                                    title="Scan Sekarang">
                                                <i class="fas fa-sync-alt w-4 h-4"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Tombol Edit -->
                                        <a href="{{ route('websites.edit', $website) }}" 
                                           class="p-2.5 text-green-600 hover:bg-green-100 rounded-lg transition-colors duration-200"
                                           title="Edit">
                                            <i class="fas fa-pencil-alt w-4 h-4"></i>
                                        </a>
                                        
                                        <!-- Tombol Delete -->
                                        <form action="{{ route('websites.destroy', $website) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="p-2.5 text-red-600 hover:bg-red-100 rounded-lg transition-colors duration-200"
                                                    title="Hapus"
                                                    onclick="return confirm('Yakin ingin menghapus website ini?')">
                                                <i class="fas fa-trash w-4 h-4"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="text-slate-400 text-sm">
                                        <i class="fas fa-inbox text-3xl mb-2"></i>
                                        <p>No websites being monitored</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Section -->
    <script>
        // Handle loading state
        const loadingOverlay = document.getElementById('globalLoading');
        
        // Form submission handling
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const buttons = this.querySelectorAll('button');
                buttons.forEach(button => {
                    button.innerHTML = `
                        <i class="fas fa-spinner loading-spinner"></i>
                        Memproses...
                    `;
                    button.disabled = true;
                });
            });
        });

        // Link click handling
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if(this.href !== window.location.href) {
                    loadingOverlay.style.display = 'flex';
                }
            });
        });

        // Auto-refresh handling
        let refreshTimer = setTimeout(() => {
            loadingOverlay.style.display = 'flex';
            window.location.reload();
        }, 30000);

        // Cancel auto-refresh on interaction
        document.addEventListener('click', () => {
            clearTimeout(refreshTimer);
        });

        // Real-time clock
        function updateClock() {
            const clockElement = document.getElementById('realtime-clock');
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            clockElement.querySelector('span').textContent = timeString;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>