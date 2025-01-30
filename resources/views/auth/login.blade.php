<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login CSRIT Purwakarta</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        section {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100vh;
            background: url('/images/bumi.jpg') no-repeat;
            background-size: cover;
            background-position: center;
            animation: animateBg 5s linear infinite;
        }

        @keyframes animateBg {
            100% {
                filter: hue-rotate(360deg);
            }
        }

        .container {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .welcome-container {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            animation: fadeInDown 1s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-text {
            text-align: right;
            color: #fff;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        .welcome-text h1 {
            font-size: 2.5em;
            margin-bottom: 5px;
            font-weight: 600;
            letter-spacing: 2px;
        }

        .welcome-text h2 {
            font-size: 2em;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .logo-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            padding: 15px;
            backdrop-filter: blur(5px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .login-box{
            position: relative;
            width: 400px;
            height: 450px;
            background: transparent;
            border: 2px solid rgba(255, 255, 255, .5);
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(15px);
        }

        h2 {
            font-size: 2em;
            color: #fff;
            text-align: center;
        }
        .input-box{
            position: relative;
            width: 310px;
            margin: 30px 0;
            border-bottom: 2px solid #fff;
        }
        .input-box label {
            position: absolute;
            top: 50%;
            left: 5px;
            transform: translateY(-50%);
            font-size: 1em;
            color: #fff;
            pointer-events: none;
            transition: .5s;
        }

        .input-box input:focus~label,
        .input-box input:valid~label {
            top: -5px;
        }

        .input-box input {
            width: 100%;
            height: 50px;
            background: transparent;
            border: none;
            outline: none;
            font-size: 1em;
            color: #fff;
            padding: 0 35px 0 5px;
        }
        .input-box .icon{
            position: absolute;
            right: 8px;
            color: #fff;
            font-size: 1.2em;
            line-height: 57px;
        }
        .remember-forgot {
            margin: -15px 0 15px;
            font-size: .9em;
            color: #fff;
            justify-content: space-between;
        }
        .remember-forgot label input {
            margin-right: 3px;
        }
        .remember-forgot a {
            color: #fff;
            text-decoration: none;
        }
        .remember-forgot a:hover {
            text-decoration: underline;
        }
        button {
            width: 100%;
            height: 40px;
            background: #fff;
            border: none;
            outline: none;
            border-radius: 40px;
            cursor: pointer;
            font-size: 1em;
            color: #000;
            font-weight: 500;
            transition: 0.3s;
        }

        button:hover {
            background: rgba(255, 255, 255, 0.8);
            transform: scale(1.02);
        }

        @media (max-width: 600px){
            .welcome-container {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .welcome-text {
                text-align: center;
            }

            .welcome-text h1 {
                font-size: 2em;
            }

            .welcome-text h2 {
                font-size: 1.6em;
            }

            .login-box {
                width: 100%;
                height: 100vh;
                border: none;
                border-radius: 0;
            }

            .input-box {
                width: 290px;
            }
        }
    </style>
</head>
<body>
    <section>
        <div class="container">
            <div class="welcome-container">
                <div class="welcome-text">
                    <h1>WELCOME TO</h1>
                    <h2>CSRIT PURWAKARTA</h2>
                </div>
                <div class="logo-container">
                    <img src="{{ asset('images/logo.png') }}" alt="CSRIT Logo" class="logo">

                </div>
            </div>
            <div class="login-box">
                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    <h2>Login</h2>
                    <div class="input-box">
                        <span class="icon"><ion-icon name="mail"></ion-icon></span>
                        <input type="text" name="username" required>
                        <label>Username</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><ion-icon name="lock-closed"></ion-icon></span>
                        <input type="password" name="password" required>
                        <label>Password</label>
                    </div>
                    <div class="remember-forgot">
                        <label><input type="checkbox" name="remember">Remember me</label>
                    </div>
                    @if ($errors->any())
                        <div style="color: red; margin-bottom: 10px;">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <button type="submit">Login</button>
                </form>
            </div>
        </div>
    </section>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script>
        // Tambahkan di dalam <head> atau di akhir <body>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Konfigurasikan fetch dengan header tambahan
            const originalFetch = window.fetch;
            window.fetch = function() {
                const [url, config] = arguments;
                config = config || {};
                config.headers = {
                    ...config.headers,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                };
                return originalFetch(url, config);
            };

            // Untuk form submit
            document.querySelector('form').addEventListener('submit', function(e) {
                this.appendChild(createHiddenInput('X-Requested-With', 'XMLHttpRequest'));
            });

            function createHiddenInput(name, value) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                return input;
            }
        });
    </script>
</body>
</html>
