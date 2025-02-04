<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Website Monitoring Dashboard')</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Vite-managed assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <!-- Pusher -->
    <script src="https://js.pusher.com/8.0/pusher.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @yield('head-scripts')
</head>
<body class="bg-gray-50 antialiased">
    <div id="app" class="flex min-h-screen">
        <!-- Sidebar Navigation -->
        <div class="md:w-64 bg-indigo-800 text-white p-6 hidden md:block">
            <div class="mb-10">
                <h1 class="text-2xl font-bold">
                    <i class="fas fa-chart-line mr-2"></i>
                    Monitoring
                </h1>
            </div>

            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('monitor.index') }}" 
                           class="flex items-center py-2 px-4 {{ request()->routeIs('monitor.index') ? 'bg-indigo-700 rounded' : 'hover:bg-indigo-700' }}">
                            <i class="fas fa-desktop mr-3"></i>
                            Website Monitor
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('security.login-attempts') }}" 
                           class="flex items-center py-2 px-4 {{ request()->routeIs('security.login-attempts') ? 'bg-indigo-700 rounded' : 'hover:bg-indigo-700' }}">
                            <i class="fas fa-shield-alt mr-3"></i>
                            Security Logs
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('security.report') }}" 
                           class="flex items-center py-2 px-4 {{ request()->routeIs('security.report') ? 'bg-indigo-700 rounded' : 'hover:bg-indigo-700' }}">
                            <i class="fas fa-file-alt mr-3"></i>
                            Security Reports
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- System Status -->
            <div class="mt-10 bg-indigo-700 rounded-lg p-4">
                <h3 class="font-semibold mb-2">System Status</h3>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span>Websites Monitored</span>
                        <span class="bg-green-500 text-white px-2 py-1 rounded-full text-xs">
                            {{ \App\Models\Website::count() }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span>Security Alerts</span>
                        <span class="bg-red-500 text-white px-2 py-1 rounded-full text-xs">
                            {{ \App\Models\LoginAttempt::where('status', 'failed')->count() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Top Navigation -->
            <header class="bg-white shadow-md p-4 flex justify-between items-center">
                <div class="flex items-center">
                    <!-- Mobile Sidebar Toggle -->
                    <button id="mobile-sidebar-toggle" class="md:hidden mr-4">
                        <i class="fas fa-bars text-gray-600"></i>
                    </button>
                    
                    <h2 class="text-xl font-semibold text-gray-800">
                        @yield('page-title')
                    </h2>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Notifications -->
                    <div class="relative">
                        <button id="notifications-btn" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-bell"></i>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-4 h-4 rounded-full flex items-center justify-center">
                                3
                            </span>
                        </button>
                    </div>

                    <!-- User Profile -->
                    <div class="relative">
                        <button id="user-menu-btn" class="flex items-center space-x-2">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'admin') }}" 
                                 class="w-8 h-8 rounded-full">
                            <span class="hidden md:block">
                                {{ Auth::user()->name ?? 'admin' }}
                            </span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content Wrapper -->
            <main class="p-6 flex-1 overflow-y-auto">
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white p-4 text-center text-gray-600 text-sm">
                © {{ date('Y') }} Website Monitoring System. All rights reserved.
            </footer>
        </div>
    </div>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" 
         class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden md:hidden">
        <div class="w-64 bg-indigo-800 text-white h-full p-6">
            <div class="flex justify-between items-center mb-10">
                <h1 class="text-2xl font-bold">
                    <i class="fas fa-chart-line mr-2"></i>
                    Monitoring
                </h1>
                <button id="close-mobile-sidebar" class="text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('monitor.index') }}" 
                           class="flex items-center py-2 px-4 hover:bg-indigo-700">
                            <i class="fas fa-desktop mr-3"></i>
                            Website Monitor
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('security.login-attempts') }}" 
                           class="flex items-center py-2 px-4 hover:bg-indigo-700">
                            <i class="fas fa-shield-alt mr-3"></i>
                            Security Logs
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('security.report') }}" 
                           class="flex items-center py-2 px-4 hover:bg-indigo-700">
                            <i class="fas fa-file-alt mr-3"></i>
                            Security Reports
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Notification Dropdown -->
    <div id="notifications-dropdown" 
         class="absolute top-16 right-4 w-80 bg-white rounded-lg shadow-lg border p-4 hidden">
        <h3 class="text-lg font-semibold mb-4">Notifications</h3>
        <ul class="space-y-2">
            <li class="border-b pb-2">
                <div class="flex items-center">
                    <span class="bg-red-500 w-2 h-2 rounded-full mr-2"></span>
                    Website Down: Example.com
                </div>
                <small class="text-gray-500">5 minutes ago</small>
            </li>
            <li class="border-b pb-2">
                <div class="flex items-center">
                    <span class="bg-yellow-500 w-2 h-2 rounded-full mr-2"></span>
                    Security Alert: Multiple Login Attempts
                </div>
                <small class="text-gray-500">15 minutes ago</small>
            </li>
        </ul>
    </div>

    <!-- User Menu Dropdown -->
    <div id="user-menu-dropdown" 
         class="absolute top-16 right-4 w-48 bg-white rounded-lg shadow-lg border hidden">
        <ul>
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100">
                    <i class="fas fa-user mr-2"></i> Profile
                </a>
            </li>
            <li>
                <a href="#" class="block px-4 py-2 hover:bg-gray-100">
                    <i class="fas fa-cog mr-2"></i> Settings
                </a>
            </li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <!-- JavaScript for Layout Interactivity -->
    <script>
        // Mobile Sidebar Toggle
        const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
        const mobileSidebar = document.getElementById('mobile-sidebar');
        const closeMobileSidebarBtn = document.getElementById('close-mobile-sidebar');

        mobileSidebarToggle.addEventListener('click', () => {
            mobileSidebar.classList.remove('hidden');
        });

        closeMobileSidebarBtn.addEventListener('click', () => {
            mobileSidebar.classList.add('hidden');
        });

        // Notifications Dropdown
        const notificationsBtn = document.getElementById('notifications-btn');
        const notificationsDropdown = document.getElementById('notifications-dropdown');

        notificationsBtn.addEventListener('click', () => {
            notificationsDropdown.classList.toggle('hidden');
        });

        // User Menu Dropdown
        const userMenuBtn = document.getElementById('user-menu-btn');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');

        userMenuBtn.addEventListener('click', () => {
            userMenuDropdown.classList.toggle('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (event) => {
            if (!notificationsBtn.contains(event.target)) {
                notificationsDropdown.classList.add('hidden');
            }

            if (!userMenuBtn.contains(event.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    </script>

    @yield('scripts')
</body>
</html>