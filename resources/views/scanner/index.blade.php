<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cosmic Security Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #000022, #000044);
            color: #00ffff;
        }
        .cosmic-input {
            background: rgba(0,0,100,0.5);
            border: 2px solid #00ffff;
            color: #00ffff;
        }
        .cosmic-button {
            background: #00ffff;
            color: #000022;
            transition: all 0.3s ease;
        }
        .cosmic-button:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px #00ffff;
        }
        .logout-button {
            background: rgba(255,0,0,0.2);
            border: 2px solid #ff3333;
            color: #ff3333;
            transition: all 0.3s ease;
        }
        .logout-button:hover {
            background: rgba(255,0,0,0.3);
            box-shadow: 0 0 15px #ff3333;
            transform: scale(1.05);
        }
        .nav-bar {
            background: rgba(0,0,50,0.5);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,255,255,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="nav-bar fixed top-0 w-full px-6 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-2">
            <i class="fas fa-satellite-dish text-2xl text-cyan-400"></i>
            <span class="text-xl font-bold">CSRIT Scanner</span>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="logout-button px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center pt-16">
        <div class="w-full max-w-md p-8 space-y-8 bg-opacity-50 rounded-xl">
            <h1 class="text-4xl text-center mb-6">🚀 Purwakarta Security Scanner</h1>

            <form action="{{ route('scanner.scan') }}" method="POST" class="space-y-6">
                @csrf
                <div class="relative">
                    <i class="fas fa-link absolute left-3 top-1/2 transform -translate-y-1/2 text-cyan-300"></i>
                    <input
                        type="url"
                        name="url"
                        placeholder="Masukkan URL untuk di-scan"
                        required
                        class="w-full p-3 pl-10 cosmic-input rounded-lg focus:ring-2 focus:ring-cyan-400 focus:outline-none"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full p-3 cosmic-button rounded-lg font-bold uppercase tracking-wide flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-search"></i>
                    <span>Mulai Scanning</span>
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="{{ route('scanner.history') }}" class="text-cyan-300 hover:underline flex items-center justify-center space-x-2">
                    <i class="fas fa-history"></i>
                    <span>Lihat Riwayat Scanning</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Toast Notification for Logout -->
    <div id="logout-toast" class="fixed bottom-4 right-4 hidden bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        <div class="flex items-center space-x-2">
            <i class="fas fa-check-circle"></i>
            <span>Berhasil logout!</span>
        </div>
    </div>

    <script>
        // Show toast notification when logout is successful
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('logout') === 'success') {
            const toast = document.getElementById('logout-toast');
            toast.classList.remove('hidden');
            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }
    </script>
</body>
</html>
