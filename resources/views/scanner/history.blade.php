<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Riwayat Scanning - Cosmic Security</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #000022, #000044);
            color: #00ffff;
        }
        .risk-high { @apply text-red-500 font-semibold; }
        .risk-medium { @apply text-yellow-500 font-semibold; }
        .risk-low { @apply text-green-500 font-semibold; }
        .table-row-hover:hover {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen p-8">
    <div class="container mx-auto max-w-6xl">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-4xl"><i class="fas fa-history mr-3"></i>Riwayat Scanning</h1>
            <div class="space-x-4">
                <a href="{{ route('scanner.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Scanner
                </a>
                <a href="{{ route('scanner.history.download') }}" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </a>
            </div>
        </div>

        <div class="bg-gray-900 bg-opacity-50 rounded-xl overflow-hidden shadow-xl">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-800 text-cyan-300">
                        <th class="p-4 text-left">URL</th>
                        <th class="p-4 text-left">Waktu Scan</th>
                        <th class="p-4 text-left">Risiko Backdoor</th>
                        <th class="p-4 text-left">Risiko Judi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scanResults as $result)
                    <tr class="table-row-hover border-t border-gray-800">
                        <td class="p-4">{{ $result->url }}</td>
                        <td class="p-4">{{ $result->scan_time->format('Y-m-d H:i:s') }}</td>
                        <td class="p-4 {{ strtolower($result->backdoor_risk) }}-risk">
                            {{ $result->backdoor_risk }}
                        </td>
                        <td class="p-4 {{ strtolower($result->gambling_risk) }}-risk">
                            {{ $result->gambling_risk }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="p-4 text-center text-gray-400">Tidak ada data scanning</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
