<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purwakarta Security Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        /* Page Transition Animation */
        .page-transition {
            animation: fadeInScale 0.8s ease-out;
        }

        @keyframes fadeInScale {
            0% {
                opacity: 0;
                transform: scale(0.95);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Background Animation */
        body {
            background: linear-gradient(45deg, #001F3F, #000A1F);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50% }
            50% { background-position: 100% 50% }
            100% { background-position: 0% 50% }
        }

        /* Floating Animation for Elements */
        .float-animation {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }

        /* Navbar with enhanced glassmorphism */
        .nav-bar {
            background: rgba(0, 0, 50, 0.2);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.2);
            box-shadow: 0px 2px 20px rgba(255, 255, 255, 0.15);
            animation: slideDown 0.8s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Enhanced Input Field */
        .cosmic-input {
            background: rgba(0, 0, 30, 0.4);
            border: 2px solid rgba(0, 255, 255, 0.4);
            color: #00ffff;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .cosmic-input:focus {
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.4);
            border-color: #00ffff;
            background: rgba(0, 0, 50, 0.6);
            transform: translateY(-2px);
        }

        /* Enhanced Button Styles */
        .cosmic-button {
            background: linear-gradient(90deg, #00ffff, #00ccff);
            color: #000022;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cosmic-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .cosmic-button:hover::before {
            left: 100%;
        }

        .cosmic-button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.5);
        }

        /* Enhanced Logout Button */
        .logout-button {
            background: rgba(255, 0, 0, 0.1);
            border: 2px solid rgba(255, 0, 0, 0.4);
            color: #ffffff;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logout-button:hover {
            background: rgba(255, 0, 0, 0.2);
            box-shadow: 0 0 20px rgba(255, 0, 0, 0.3);
            transform: translateY(-2px);
        }

         /* Loader Animasi */
         .loader {
            width: 50px;
            aspect-ratio: 1;
            border-radius: 50%;
            border: 8px solid;
            border-color: #ffffff #ffffff00;
            animation: l1 1s infinite;
        }
        @keyframes l1 {to{transform: rotate(.5turn)}}


 /* Overlay Blur Saat Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 50, 0.85);
            backdrop-filter: blur(15px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .loading-overlay.active {
            visibility: visible;
            opacity: 1;
        }
        

        /* Particle Background Effect */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(0, 255, 255, 0.5);
            border-radius: 50%;
            animation: moveParticle 20s infinite linear;
        }

        @keyframes moveParticle {
            0% {
                transform: translate(0, 0);
                opacity: 0;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                transform: translate(100vw, 100vh);
                opacity: 0;
            }
        }
        .page-entrance-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #07121f;
            z-index: 9999;
            transform: translateX(-100%);
        }

        /* Modify the page transition animation */
        .page-transition {
            opacity: 0;
            transform: translateY(20px);
        }

        /* Modify the nav-bar animation */
        .nav-bar {
            opacity: 0;
            transform: translateY(-20px);
        }

        /* Add transition classes */
        .fade-in {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.6s ease;
        }

        .slide-in {
            transform: translateX(0);
            transition: transform 0.6s ease;
        }
    </style>
</head>
<body>
    <!-- Particle Background -->
    <div class="particles" id="particles"></div>

    <!-- Navigation Bar -->
    <nav class="nav-bar fixed top-0 w-full px-6 py-4 flex justify-between items-center z-50">
        <div class="flex items-center space-x-2 float-animation">
            <i class="fas fa-shield-alt text-2xl text-cyan-400"></i>
            <span class="text-xl font-bold">Purwakarta Security Scanner</span>
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
    <div class="min-h-screen flex items-center justify-center pt-16 page-transition">
        <div class="w-full max-w-md p-8 space-y-8 bg-opacity-50 rounded-xl backdrop-blur-lg">
            <h1 class="text-4xl text-center mb-2 float-animation">Mulai Scanning</h1>

            <form action="{{ route('scanner.scan') }}" method="POST" class="space-y-6" id="scan-form">
                @csrf
                <div class="relative">
                    <i class="fas fa-globe absolute left-3 top-1/2 transform -translate-y-1/2 text-cyan-300"></i>
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
                    id="scan-button"
                >
                    <i class="fas fa-search"></i>
                    <span>Scan Sekarang</span>
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="{{ route('scanner.history') }}" class="text-cyan-300 hover:underline flex items-center justify-center space-x-2 transition-all duration-300 hover:text-cyan-400">
                    <i class="fas fa-history"></i>
                    <span>Lihat Riwayat</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay Loader -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loader"></div>
    </div>


    <script>
        // Add particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + 'vw';
                particle.style.top = Math.random() * 100 + 'vh';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Handle form submission and loading overlay
        document.getElementById("scan-form").addEventListener("submit", function() {
            document.getElementById("loading-overlay").classList.add("active");
        });

        // Initialize particles on page load
        document.addEventListener('DOMContentLoaded', createParticles);

        document.addEventListener('DOMContentLoaded', function() {
            const hasTransition = sessionStorage.getItem('pageTransition');
            
            if (hasTransition) {
                // Create entrance overlay
                const entranceOverlay = document.createElement('div');
                entranceOverlay.className = 'page-entrance-overlay';
                document.body.appendChild(entranceOverlay);

                // Animation timeline
                const tl = gsap.timeline({
                    defaults: { duration: 0.6, ease: 'power2.inOut' }
                });

                tl.to(entranceOverlay, {
                    x: 0,
                    duration: 0
                })
                .to(entranceOverlay, {
                    x: '100%',
                    duration: 0.6,
                    onComplete: () => {
                        entranceOverlay.remove();
                        sessionStorage.removeItem('pageTransition');
                    }
                })
                .from('.nav-bar', {
                    y: -50,
                    opacity: 0,
                    duration: 0.4
                }, '-=0.3')
                .from('.page-transition', {
                    y: 30,
                    opacity: 0,
                    duration: 0.4
                }, '-=0.2');
            } else {
                // If no transition (direct URL access), simply show elements
                document.querySelector('.nav-bar').classList.add('fade-in');
                document.querySelector('.page-transition').classList.add('fade-in');
            }
        });

        // Add page transition effect when coming from login
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                document.body.classList.add('page-transition');
            }
        });
    </script>
</body>
</html>