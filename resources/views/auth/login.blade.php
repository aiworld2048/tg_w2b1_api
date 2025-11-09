<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TG Slot | Login</title>

    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/adminlte.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Poppins', sans-serif;
            overflow: hidden;
            position: relative;
        }

        #rainbow-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
        }

        .login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.5),
                         0 0 40px rgba(255, 0, 255, 0.3);
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                text-shadow: 0 0 20px rgba(255, 255, 255, 0.5),
                             0 0 40px rgba(255, 0, 255, 0.3);
            }
            to {
                text-shadow: 0 0 30px rgba(255, 255, 255, 0.8),
                             0 0 60px rgba(0, 255, 255, 0.5);
            }
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 300;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            padding: 3rem 2.5rem;
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            box-shadow: 0 12px 48px 0 rgba(0, 0, 0, 0.5);
            transform: translateY(-5px);
        }

        .login-box-msg {
            text-align: center;
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 2rem;
            font-weight: 400;
        }

        /* Modern Input Groups */
        .modern-input-group {
            position: relative;
            margin-bottom: 2rem;
        }

        .modern-input-group input {
            width: 100%;
            padding: 15px 50px 15px 20px;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .modern-input-group input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .modern-input-group input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .modern-input-group .input-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modern-input-group .input-icon:hover {
            color: #fff;
            transform: translateY(-50%) scale(1.1);
        }

        /* Alert Styles */
        .modern-alert {
            background: rgba(255, 59, 48, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 59, 48, 0.5);
            border-radius: 15px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            color: #fff;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modern-alert .close {
            color: #fff;
            opacity: 0.8;
            font-size: 1.5rem;
            text-shadow: none;
        }

        /* Checkbox */
        .modern-checkbox {
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }

        .modern-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
            accent-color: #00ffff;
        }

        /* Modern Button */
        .modern-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .modern-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }

        .modern-btn:active {
            transform: translateY(-1px);
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
        }

        /* Floating Particles */
        .particle {
            position: fixed;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            pointer-events: none;
            animation: float 3s infinite ease-in-out;
            z-index: 1;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-logo h2 {
                font-size: 2rem;
            }

            .glass-card {
                padding: 2rem 1.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 1rem;
            }

            .modern-checkbox {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .login-logo h2 {
                font-size: 1.7rem;
            }

            .glass-card {
                padding: 1.5rem 1.2rem;
            }
        }
    </style>
</head>

<body class="login-page">
    <canvas id="rainbow-bg"></canvas>
    
    <div class="login-container">
        <div class="login-logo">
            <h2>TG Slot</h2>
            <p class="login-subtitle">Welcome Back</p>
        </div>
        
        <div class="glass-card">
            <p class="login-box-msg">Sign in to start your session</p>
            
            @if(session('error'))
                <div class="modern-alert" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            
            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                
                <div class="modern-input-group">
                    <input 
                        type="text"
                        class="@error('user_name') is-invalid @enderror" 
                        name="user_name"
                        value="{{ old('user_name') }}" 
                        required 
                        placeholder="Enter Username" 
                        autofocus>
                    <span class="input-icon">
                        <i class="fas fa-user"></i>
                    </span>
                    @error('user_name')
                        <span style="color: #ff3b30; font-size: 0.85rem; display: block; margin-top: 0.5rem;">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                
                <div class="modern-input-group">
                    <input 
                        id="password" 
                        type="password"
                        class="@error('password') is-invalid @enderror" 
                        name="password" 
                        required
                        placeholder="Enter Password">
                    <span class="input-icon" onclick="togglePassword()" id="toggleIcon">
                        <i class="fas fa-eye"></i>
                    </span>
                    @error('password')
                        <span style="color: #ff3b30; font-size: 0.85rem; display: block; margin-top: 0.5rem;">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                
                <div class="form-row">
                    <div class="modern-checkbox">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember Me</label>
                    </div>
                    
                    <button type="submit" class="modern-btn">Sign In</button>
                </div>
            </form>
        </div>
    </div>

    


    <script>
        // Password Toggle Function
        function togglePassword() {
            const passwordInput = document.getElementById("password");
            const toggleIcon = document.getElementById("toggleIcon");
            const iconElement = toggleIcon.querySelector('i');

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = "password";
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

        // Enhanced Rainbow Waves Animation
        const canvas = document.getElementById('rainbow-bg');
        const ctx = canvas.getContext('2d');

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Ultra Vivid Rainbow Colors with more saturation
        const colors = [
            "#FF0055", // Hot pink
            "#FF0000", // Pure red
            "#FF4400", // Red-orange
            "#FF8800", // Orange
            "#FFBB00", // Amber
            "#FFFF00", // Yellow
            "#88FF00", // Lime
            "#00FF00", // Green
            "#00FF88", // Spring green
            "#00FFFF", // Cyan
            "#00BBFF", // Sky blue
            "#0088FF", // Blue
            "#0044FF", // Deep blue
            "#0000FF", // Pure blue
            "#4400FF", // Indigo
            "#8800FF", // Purple
            "#BB00FF", // Violet
            "#FF00FF", // Magenta
            "#FF0088", // Rose
            "#FF0055"  // Loop back
        ];

        let t = 0;

        function drawWaves() {
            // Create gradient background for depth
            const bgGradient = ctx.createRadialGradient(
                canvas.width / 2, canvas.height / 2, 0,
                canvas.width / 2, canvas.height / 2, canvas.width
            );
            bgGradient.addColorStop(0, '#0a0a1a');
            bgGradient.addColorStop(1, '#000000');
            ctx.fillStyle = bgGradient;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            let amplitude = 100;
            let waveCount = 8;
            let heightUnit = canvas.height / (waveCount + 1);

            // Draw waves from back to front
            for (let i = 0; i < waveCount; i++) {
                ctx.beginPath();
                
                for (let x = 0; x <= canvas.width; x += 1) {
                    let angle1 = (x / (150 + i * 15)) + t * (0.4 + 0.12 * i);
                    let angle2 = (x / (200 + i * 20)) - t * (0.3 + 0.08 * i);
                    
                    // Combine multiple sine waves for complex motion
                    let y = Math.sin(angle1 + i * 1.5) * amplitude * 0.7 + 
                            Math.sin(angle2) * amplitude * 0.4 +
                            (i + 1) * heightUnit;
                    
                    if (x === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                
                ctx.lineTo(canvas.width, canvas.height);
                ctx.lineTo(0, canvas.height);
                ctx.closePath();

                // Create vivid multi-color gradient
                let grad = ctx.createLinearGradient(
                    0, 0, 
                    canvas.width, canvas.height
                );
                
                let colorOffset = Math.floor(t * 2 + i * 2) % colors.length;
                let colorStops = 8;
                
                for (let j = 0; j < colorStops; j++) {
                    let idx = (colorOffset + j * 2) % colors.length;
                    grad.addColorStop(j / (colorStops - 1), colors[idx]);
                }
                
                ctx.fillStyle = grad;
                
                // More visible and dynamic opacity
                let baseOpacity = 0.25 + (i * 0.05);
                let dynamicOpacity = Math.sin(t * 1.5 + i * 0.8) * 0.15;
                ctx.globalAlpha = baseOpacity + dynamicOpacity;
                
                // Add shadow for depth
                ctx.shadowBlur = 30;
                ctx.shadowColor = colors[(colorOffset + i) % colors.length];
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            ctx.globalAlpha = 1;
            t += 0.012;
            requestAnimationFrame(drawWaves);
        }

        drawWaves();

        // Floating Particles Effect
        function createParticles() {
            for (let i = 0; i < 20; i++) {
                setTimeout(() => {
                    const particle = document.createElement('div');
                    particle.className = 'particle';
                    particle.style.left = Math.random() * window.innerWidth + 'px';
                    particle.style.top = Math.random() * window.innerHeight + 'px';
                    particle.style.animationDelay = Math.random() * 3 + 's';
                    particle.style.animationDuration = (3 + Math.random() * 4) + 's';
                    document.body.appendChild(particle);

                    setTimeout(() => {
                        particle.remove();
                    }, 8000);
                }, i * 500);
            }
        }

        // Create particles periodically
        createParticles();
        setInterval(createParticles, 10000);

        // Add input focus effects
        document.querySelectorAll('.modern-input-group input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>

</body>

</html>
