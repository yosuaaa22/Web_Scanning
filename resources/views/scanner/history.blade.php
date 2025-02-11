<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Riwayat Scanning - CSRIT Purwakarta</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #07121f;
            color: white;
            overflow-x: hidden;
            opacity: 1;
            transform: translateX(0);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }


        .page-container {
            position: relative;
            width: 100%;
            min-height: 100vh;
            padding: 2rem;
            overflow-x: hidden;
        }

         .background-left {
            position: fixed;
            width: 100%;
            height: 100%;
            left: 0;
            top: 0;
            background: rgba(7, 18, 31, 0.95); 
            clip-path: polygon(0 0, 65% 0, 35% 100%, 0 100%);
            z-index: 0;
            transition: all 0.3s ease;
            min-height: 100vh;
        }

        .background-right {
            position: fixed;
            width: 100%;
            height: 100%;
            right: 0;
            top: 0;
            background: rgba(0, 147, 233, 0.95); 
            clip-path: polygon(65% 0, 100% 0, 100% 100%, 35% 100%);
            z-index: 0;
            transition: all 0.3s ease;
            min-height: 100vh;
        } */

        .neon-line {
            position: fixed;
            width: 2px;
            height: 300vh; /* Tinggi tetap untuk memastikan garis cukup panjang */
            background: #00d9ff;
            top: -100vh; /* Mulai dari atas viewport */
            left: 50%;
            transform: translateX(-50%) rotate(30deg);
            transform-origin: center;
            box-shadow: 
                0 0 7px #00d9ff,
                0 0 10px #00d9ff,
                0 0 21px #00d9ff;
            opacity: 0.8;
            z-index: 1;
            animation: neonPulse 1.5s infinite alternate;
            pointer-events: none; /* Memastikan garis tidak mengganggu interaksi */
        }

        .content-scroll {
            position: relative;
            min-height: 100vh;
            width: 100%;
            z-index: 1;
        }

        /* Media queries untuk responsif */
        @media screen and (max-width: 768px) {
            .background-left {
                clip-path: polygon(0 0, 75% 0, 45% 100%, 0 100%);
            }
            
            .background-right {
                clip-path: polygon(75% 0, 100% 0, 100% 100%, 45% 100%);
            }

            .neon-line {
                left: 60%;
                height: calc(250vh + 100%); /* Tambah panjang untuk tablet */
                transform: translateX(-50%) rotate(35deg);
            }
        }

        @media screen and (max-width: 480px) {
            .background-left {
                clip-path: polygon(0 0, 85% 0, 55% 100%, 0 100%);
            }
            
            .background-right {
                clip-path: polygon(85% 0, 100% 0, 100% 100%, 55% 100%);
            }

            .neon-line {
                left: 70%;
                height: calc(300vh + 100%); /* Tambah panjang untuk mobile */
                transform: translateX(-50%) rotate(40deg);
            }
        }

        @media screen and (min-height: 1200px) {
            .background-left,
            .background-right {
                height: 100%;
                position: fixed;
                min-height: 100%;
            }
            
            .neon-line {
                height: 400vh;
                top: -150vh;
            }
        }

        @keyframes neonPulse {
            from {
                opacity: 0.8;
            }
            to {
                opacity: 0.6;
            }
        }
        .content-wrapper {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(13, 17, 23, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid rgba(0, 217, 255, 0.2);
            animation: headerAppear 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }

        /* Add this pseudo-element for the moving neon light effect */
        .header::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 16px;
            background: linear-gradient(90deg, transparent, rgba(0, 217, 255, 0.6), transparent);
            animation: borderLight 3s linear infinite;
            z-index: -1;
        }

        @keyframes headerAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes borderLight {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }

        .title {
            font-size: 2rem;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .button-container {
        display: flex;
        gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(0, 217, 255, 0.5);
            border-radius: 30px;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: visible !important;
        }


        /* Add transition animation for scan button */
        .btn-scan {
            position: relative;
            transition: transform 0.3s ease;
        }

        .btn-scan:hover {
            transform: translateX(-10px);
        }


        .btn-scan::after {
            content: '→';
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .btn-scan:hover::after {
            right: 10px;
            opacity: 1;
        }

/* Add car animation for PDF download button */
        .btn-download {
            position: relative;
            overflow: visible !important;
        }

        .btn-download::before {
            content: '🚗';
            position: absolute;
            left: -30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2em;
            opacity: 0;
            pointer-events: none;
            z-index: 10;
        }

        .btn-download:hover::before {
            animation: driveCar 1s forwards;
        }

        @keyframes driveCar {
            0% {
                left: -30px;
                opacity: 0;
            }
            20% {
                opacity: 1;
            }
            80% {
                left: calc(100% - 20px);
                opacity: 1;
            }
            100% {
                left: calc(100% + 30px);
                opacity: 0;
            }
        }

        .btn:hover {
        transform: translateY(-2px);
        background: rgba(0, 217, 255, 0.2);
        box-shadow: 0 5px 15px rgba(0, 217, 255, 0.4);
        }

        .background-left {
        position: fixed;
        width: 100%;
        height: 100%;
        left: 0;
        top: 0;
        background: rgba(7, 18, 31, 0.8);
        clip-path: polygon(0 0, 65% 0, 35% 100%, 0 100%);
        z-index: 0;
        backdrop-filter: blur(10px);
        }

        .background-right {
        position: fixed;
        width: 100%; 
        height: 100%;
        right: 0;
        top: 0;
        background: rgba(0, 147, 233, 0.8);
        clip-path: polygon(65% 0, 100% 0, 100% 100%, 35% 100%);
        z-index: 0;
        backdrop-filter: blur(10px);
        }

        .table-container {
            background: rgba(13, 17, 23, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(0, 217, 255, 0.2);
            animation: tableAppear 0.8s ease-out;
            position: relative;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            padding: 1px;
            background: linear-gradient(90deg, 
                transparent,
                #00d9ff,
                transparent
            );
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderGlow 3s linear infinite;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(0, 217, 255, 0.1);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #00d9ff;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        td {
            padding: 1rem;
            border-top: 1px solid rgba(0, 217, 255, 0.1);
        }

        tr {
            transition: all 0.3s ease;
        }

        tr:hover {
            background: rgba(0, 217, 255, 0.1);
            transform: scale(1.01);
        }

        .risk-high {
            color: #ff4d4d;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(255, 77, 77, 0.5);
        }

        .risk-medium {
            color: #ffd700;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .risk-low {
            color: #00ff00;
            font-weight: 600;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.5);
        }

        @keyframes headerAppear {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes tableAppear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes neonPulse {
            from {
                opacity: 0.8;
                box-shadow: 
                    0 0 7px #00d9ff,
                    0 0 10px #00d9ff,
                    0 0 21px #00d9ff;
            }
            to {
                opacity: 1;
                box-shadow: 
                    0 0 9px #00d9ff,
                    0 0 15px #00d9ff,
                    0 0 28px #00d9ff;
            }
        }

        @keyframes borderGlow {
            0%, 100% {
                opacity: 0.5;
            }
            50% {
                opacity: 1;
            }
        }

        .empty-message {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .title {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .table-container {
                overflow-x: auto;
            }

            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="background-left"></div>
        <div class="background-right"></div>
        <div class="neon-line"></div>
        <div class="content-scroll">

        
        <div class="content-wrapper">
            <div class="header">
                <h1 class="title">
                    <i class="fas fa-history"></i>
                    Riwayat Scanning
                </h1>
                <div class="button-container">
                    <a href="{{ route('scanner.index') }}" class="btn btn-scan">
                        <i class="fas fa-search"></i>
                        Scanner
                    </a>
                    <a href="{{ route('scanner.history.download') }}" class="btn btn-download">
                        <i class="fas fa-download"></i>
                        Download PDF
                    </a>
                </div>
            </div>


            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Waktu Scan</th>
                            <th>Risiko Backdoor</th>
                            <th>Risiko Judi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($scanResults as $result)
                        <tr>
                            <td>{{ $result->url }}</td>
                            <td>{{ $result->scan_time->format('Y-m-d H:i:s') }}</td>
                            <td class="risk-{{ strtolower($result->backdoor_risk) }}">
                                {{ $result->backdoor_risk }}
                            </td>
                            <td class="risk-{{ strtolower($result->gambling_risk) }}">
                                {{ $result->gambling_risk }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="empty-message">
                                Tidak ada data scanning
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Floating particles
        const container = document.querySelector('.page-container');
        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: fixed;
                width: ${Math.random() * 4}px;
                height: ${Math.random() * 4}px;
                background: rgba(0, 217, 255, ${Math.random() * 0.5});
                border-radius: 50%;
                pointer-events: none;
                left: ${Math.random() * 100}vw;
                top: ${Math.random() * 100}vh;
                animation: float ${5 + Math.random() * 10}s linear infinite;
                z-index: 1;
            `;
            container.appendChild(particle);
        }

        // Animation for table rows
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.5s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, 100 * index);
        });

        // Page transitions and button animations
        document.addEventListener('DOMContentLoaded', function() {
            // Get buttons
            const scannerBtn = document.querySelector('.btn-scan');
            const downloadBtn = document.querySelector('.btn-download');

            // Add smooth page transition for scanner button
            scannerBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const currentPage = document.body;
                
                // Add fade out effect
                currentPage.style.opacity = '0';
                currentPage.style.transform = 'translateX(-20px)';
                
                // Wait for animation to complete before changing page
                setTimeout(() => {
                    window.location.href = this.href;
                }, 500);
            });
        });

        // Add CSS keyframes for floating animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes float {
                0% {
                    transform: translateY(0) rotate(0deg);
                    opacity: 0;
                }
                50% {
                    opacity: 1;
                }
                100% {
                    transform: translateY(-100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>