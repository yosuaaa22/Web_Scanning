<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Security Scanner</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
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
            overflow: hidden;
            background: #07121f;
        }

        .page-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }

        .background-left {
            position: absolute;
            width: 100%;
            height: 100%;
            left: 0;
            top: 0;
            background: #07121f;
            clip-path: polygon(0 0, 65% 0, 35% 100%, 0 100%);
            z-index: 0;
        }

        .background-right {
            position: absolute;
            width: 100%;
            height: 100%;
            right: 0;
            top: 0;
            background: #0093E9;
            clip-path: polygon(65% 0, 100% 0, 100% 100%, 35% 100%);
            z-index: 0;
        }

        .neon-line {
            position: absolute;
            top: -20%;
            left: 50%;
            width: 2px;
            height: 140%;
            background: #00d9ff;
            transform: rotate(35deg);
            transform-origin: center;
            box-shadow: 
                0 0 7px #00d9ff,
                0 0 10px #00d9ff,
                0 0 21px #00d9ff;
            opacity: 0.8;
            z-index: 1;
            animation: neonPulse 1.5s infinite alternate;
            pointer-events: none;
        }

        .content-container {
            position: relative;
            width: 100%;
            height: 100%;
            z-index: 2;
            display: flex;
        }

        .login-side {
            width: 50%;
            height: 100vh;
            display: flex;
            align-items: center;
            padding-left: 5%;
            position: relative;
        }

        .welcome-side {
            width: 50%;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-right: 10%;
            position: relative;
        }

        .login-box {
            width: 100%;
            max-width: 450px;
            color: white;
            padding: 40px;
            position: relative;
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: none;
            animation: formAppear 0.6s ease-out;
            background: rgba(13, 17, 23, 0.6);
        }

        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            padding: 2px;
            background: linear-gradient(90deg, 
                transparent,
                transparent,
                #00d9ff,
                transparent,
                transparent
            );
            -webkit-mask: 
                linear-gradient(#fff 0 0) content-box, 
                linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            background-size: 200% 200%;
            animation: neonBorderRun 3s linear infinite;
        }

            .login-title {
            color: white;
            font-size: 32px;
            margin-bottom: 40px;
            text-align: center;
            font-weight: 600;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

            .login-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 20px;
            box-shadow: 0 0 15px #00d9ff,
            inset 0 0 15px #00d9ff;
            opacity: 0.5;
            pointer-events: none;
        }

        @keyframes neonBorderRun {
            0% {
                background-position: 100% 0;
            }
            100% {
                background-position: -100% 0;
            }
        }

        /* Optional: Add subtle pulsing glow */
        @keyframes glowPulse {
            0%, 100% {
                opacity: 0.5;
            }
            50% {
                opacity: 0.7;
            }
        }

        .login-box::after {
            animation: glowPulse 2s ease-in-out infinite;
        }

        .input-group {
            margin-bottom: 30px;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            background: rgba(255, 255, 255, 0.1);
            outline: none;
        }

        .input-group label {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
            transition: all 0.3s ease;
            pointer-events: none;
        }

        .input-group input:focus ~ label,
        .input-group input:valid ~ label {
            top: 0;
            left: 10px;
            font-size: 12px;
            padding: 0 5px;
            color: #00d9ff;
            background: #07121f;
        }

        .input-line {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #00d9ff, transparent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .input-group input:focus ~ .input-line {
            transform: scaleX(1);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.8);
        }

        .remember-label input[type="checkbox"] {
            display: none;
        }

        .checkmark {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            display: inline-block;
            position: relative;
            transition: all 0.3s ease;
        }

        .remember-label input[type="checkbox"]:checked ~ .checkmark {
            background: #00d9ff;
            border-color: #00d9ff;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #00d9ff, #00fff2);
            border: none;
            border-radius: 30px;
            color: white;
            font-size: 18px;
            font-weight: 500;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 217, 255, 0.4);
        }

        .btn-loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            display: none;
            animation: spin 0.8s linear infinite;
        }

        .welcome-content {
            text-align: center;
            color: white;
            max-width: 600px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-left: 200px;
        }

        .welcome-logo {
            width: 200px;
            height: auto;
            margin-bottom: 30px;
            animation: floatUpDown 3s ease-in-out infinite;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 0 10px rgba(0, 0, 0, 0.6));
        }

        .welcome-logo:hover { 
            transform: scale(1.1);
        }

        .welcome-content p {
            font-size: 27px;
            line-height: 1.6;
            opacity: 0.9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @keyframes floatUpDown {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);  /* Logo akan bergerak ke atas 15px */
            }
        }

        @keyframes formAppear {
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

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

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

        @media (max-width: 768px) {
            .background-left {
                width: 100%;
                height: 50%;
                clip-path: polygon(0 0, 100% 0, 100% 50%, 0 50%);
            }

            .background-right {
                width: 100%;
                height: 50%;
                top: 50%;
                clip-path: polygon(0 50%, 100% 50%, 100% 100%, 0 100%);
            }

            .content-container {
                flex-direction: column;
            }

            .login-side,
            .welcome-side {
                width: 100%;
                height: 50vh;
                padding: 20px;
            }

            .welcome-content {
                margin-left: 0;
                padding: 20px;
            }

            .welcome-logo {
                width: 150px;
            }

            .welcome-content p {
                font-size: 12px;
            }

            .neon-line {
                display: none;
            }

            .login-box {
                padding: 20px;
            }

            .login-title {
                font-size: 28px;
                margin-bottom: 30px;
            }
        }
    </style>
<body>
    <div class="page-container">
        <div class="background-left"></div>
        <div class="background-right"></div>
        <div class="neon-line"></div>
        <div class="content-container">
            <div class="login-side" id="loginSide">
                <div class="login-box">
                    <h1 class="login-title">LOGIN</h1>
                    <form id="loginForm" method="POST" action="{{ route('login') }}">
                        @csrf
                        @csrf
                        <div class="input-group">
                            <input type="text" name="username" required>
                            <label>Username</label>
                            <div class="input-line"></div>
                            <div class="input-group-border-left"></div>
                            <div class="input-group-border-right"></div>
                            @error('username')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="input-group">
                            <input type="password" name="password" required>
                            <label>Password</label>
                            <div class="input-line"></div>
                            <div class="input-group-border-left"></div>
                            <div class="input-group-border-right"></div>
                            <button type="button" class="toggle-password">
                                <i class="show-icon">👁️</i>
                            </button>
                            @error('password')
                                <span class="error-message">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="remember-forgot">
                            <label class="remember-label">
                                <input type="checkbox" name="remember">
                                <span class="checkmark"></span>
                                <span>Ingatkan Saya</span>
                            </label>
                        </div>
                        <button type="submit" class="login-btn">
                            <span class="btn-text">LOGIN</span>
                            <span class="btn-loader"></span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="welcome-side" id="welcomeSide">
                <div class="welcome-content">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="welcome-logo">
                    <p>Selamat Datang Di CSRIT Purwakarta</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle Password Visibility
        document.querySelector('.toggle-password').addEventListener('click', function() {
            const passwordInput = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.textContent = '👁️‍🗨️';
            } else {
                passwordInput.type = 'password';
                icon.textContent = '👁️';
            }
            
            icon.style.animation = 'none';
            icon.offsetHeight;
            icon.style.animation = 'pop 0.3s ease';
        });

        // Form Submit Animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
    // Jangan hentikan form submission
    // e.preventDefault();
    
        const btn = this.querySelector('.login-btn');
        const btnText = btn.querySelector('.btn-text');
        const btnLoader = btn.querySelector('.btn-loader');
        
        btn.disabled = true;
        btnText.style.opacity = '0';
        btnLoader.style.display = 'block';
        
        // Animasi input fields
        const inputs = this.querySelectorAll('.input-group');
        inputs.forEach((input, index) => {
            setTimeout(() => {
                input.style.transform = 'translateX(-100%)';
                input.style.opacity = '0';
                input.style.transition = 'all 2s ease';
            }, index * 100);
        });

    // Matikan animasi neon
    const neonLine = document.querySelector('.neon-line');
    if (neonLine) {
        neonLine.style.animation = 'none';
        gsap.to('.neon-line', {
            duration: 0.3,
            opacity: 0,
            ease: 'power2.inOut'
        });
    }
    
    // Animasi background
    const tl = gsap.timeline({
        defaults: {
            duration: 1,
            ease: 'power2.inOut'
        }
    });
    
    tl.to(['.background-left', '#loginSide'], {
        x: '-100%',
    }, 0);
    
    tl.to(['.background-right', '#welcomeSide'], {
        x: '100%',
    }, 0);
            
            // Set timeout untuk redirect agar animasi terlihat
            setTimeout(() => {
                window.location.href = '/scanner';
            }, 800);
        });

        // Input Focus Animations
        document.querySelectorAll('.input-group input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
                this.parentElement.style.transition = 'transform 0.3s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Add floating particles background
        const loginBox = document.querySelector('.login-box');
        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.cssText = `
                position: absolute;
                width: ${Math.random() * 5}px;
                height: ${Math.random() * 5}px;
                background: rgba(0, 217, 255, ${Math.random() * 0.5});
                border-radius: 50%;
                pointer-events: none;
                left: ${Math.random() * 100}%;
                top: ${Math.random() * 100}%;
                animation: float ${5 + Math.random() * 10}s linear infinite;
            `;
            loginBox.appendChild(particle);
        }

        // Add CSS keyframes dynamically
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pop {
                0% { transform: scale(1); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }

            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                background-color: rgba(255, 255, 255, 0.7);
            }

            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }

            .error-message {
                color: #ff4d4d;
                font-size: 12px;
                margin-top: 5px;
                display: block;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);

        // Initialize any error messages with animation
        document.querySelectorAll('.error-message').forEach(error => {
            error.style.animation = 'fadeIn 0.1s ease';
        });
    </script>
</body>
</html>