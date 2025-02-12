<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Website Baru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        .input-focus {
            @apply focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="antialiased">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-lg">
            <div class="bg-white rounded-xl shadow-sm p-8 border border-gray-200">
                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center justify-center mb-4">
                        <h1 class="text-2xl font-bold text-gray-900">Monitoring Website</h1>
                    </div>
                    <p class="text-center text-gray-500 text-sm">
                        Pantau ketersediaan website secara real-time
                    </p>
                </div>

                @if($errors->any())
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Terdapat {{ $errors->count() }} kesalahan
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('websites.store') }}" method="POST" id="createForm">
                    @csrf

                    <!-- Nama Website -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nama Website
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                name="name" 
                                required
                                class="block w-full rounded-lg shadow-sm input-focus border-gray-300 placeholder-gray-400"
                                placeholder="Contoh: Portal Pemerintah Daerah"
                                value="{{ old('name') }}"
                                autofocus
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-globe text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- URL -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            URL Website
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="url" 
                                name="url" 
                                required
                                pattern="https?://.+"
                                class="block w-full rounded-lg shadow-sm input-focus border-gray-300 placeholder-gray-400"
                                placeholder="https://example.com"
                                value="{{ old('url') }}"
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-link text-gray-400"></i>
                            </div>
                        </div>
                        <p class="mt-2 text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1.5"></i>
                            Pastikan URL diawali dengan http:// atau https://
                        </p>
                    </div>

                    <!-- Interval Pengecekan -->
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Interval Pengecekan
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select 
                                name="check_interval" 
                                required
                                class="block w-full rounded-lg shadow-sm input-focus border-gray-300 bg-white pr-10"
                            >
                                <option value="" disabled selected>Pilih Interval</option>
                                <option value="5" {{ old('check_interval') == 5 ? 'selected' : '' }}>Setiap 5 Menit</option>
                                <option value="15" {{ old('check_interval') == 15 ? 'selected' : '' }}>Setiap 15 Menit</option>
                                <option value="30" {{ old('check_interval') == 30 ? 'selected' : '' }}>Setiap 30 Menit</option>
                                <option value="60" {{ old('check_interval') == 60 ? 'selected' : '' }}>Setiap 1 Jam</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3">
                        <a 
                            href="{{ route('websites.index') }}"
                            class="inline-flex items-center px-5 py-2.5 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <i class="fas fa-times mr-2"></i>
                            Batal
                        </a>
                        <button 
                            type="submit" 
                            class="inline-flex items-center px-5 py-2.5 border border-transparent rounded-lg font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 relative"
                            id="submitBtn"
                        >
                            <span class="flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                <span id="btnText">Simpan Website</span>
                                <i id="spinner" class="fas fa-spinner animate-spin ml-2 hidden"></i>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('createForm');
        const submitBtn = document.getElementById('submitBtn');
        const spinner = document.getElementById('spinner');
        const btnText = document.getElementById('btnText');

        form.addEventListener('submit', function(e) {
            // Tampilkan spinner dan nonaktifkan tombol
            spinner.classList.remove('hidden');
            btnText.style.marginRight = '0.5rem';
            submitBtn.disabled = true;
            submitBtn.classList.add('cursor-not-allowed', 'opacity-75');
        });
    </script>
</body>
</html>