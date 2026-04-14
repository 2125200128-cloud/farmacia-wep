<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediClick - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo time(); ?>">
    <style>
        .login-body {
            background: linear-gradient(135deg, var(--azul-oscuro) 0%, var(--azul-medio) 50%, #0a4d68 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card-wrapper {
            width: 100%;
            max-width: 960px;
            min-height: 540px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(13, 43, 78, 0.25);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            animation: aparecer 0.6s ease-out;
        }

        .carousel-section {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--azul-oscuro), var(--azul-medio));
        }

        .carousel-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.6s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-slide.active {
            opacity: 1;
        }

        .carousel-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(13, 43, 78, 0.3), rgba(64, 224, 208, 0.15));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 40px;
            z-index: 5;
        }

        .carousel-overlay h3 {
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .carousel-overlay p {
            font-size: 0.95rem;
            opacity: 0.95;
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
        }

        .carousel-dots {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 10;
        }

        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: background 0.3s;
        }

        .carousel-dot.active {
            background: var(--turquesa-base);
            width: 24px;
            border-radius: 4px;
        }

        .form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
            background: white;
        }

        .form-section-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--turquesa-base), var(--agua-brillante));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 4px 15px rgba(64, 224, 208, 0.35);
        }

        .login-logo-circle i {
            color: var(--azul-oscuro);
            font-size: 1.8rem;
        }

        .form-section-header h2 {
            font-weight: 700;
            color: var(--texto-oscuro);
            margin-bottom: 8px;
        }

        .form-section-header p {
            color: var(--texto-gris);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--texto-oscuro);
            font-size: 0.9rem;
            margin-bottom: 8px;
            display: block;
        }

        .form-control-group {
            position: relative;
            display: flex;
            align-items: center;
            background: #f5f7fa;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .form-control-group:focus-within {
            border-color: var(--turquesa-base);
            box-shadow: 0 0 0 3px rgba(64, 224, 208, 0.1);
        }

        .form-control-group i {
            padding-left: 14px;
            color: #a0aec0;
            font-size: 0.95rem;
        }

        .form-control-group input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 12px 14px;
            font-size: 0.95rem;
            color: var(--texto-oscuro);
        }

        .form-control-group input::placeholder {
            color: #cbd5e0;
        }

        .form-control-group input:focus {
            outline: none;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(90deg, var(--turquesa-base), var(--agua-brillante));
            border: none;
            border-radius: 10px;
            color: var(--azul-oscuro);
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(64, 224, 208, 0.35);
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(64, 224, 208, 0.45);
        }

        .error-alert {
            background: #fed7d7;
            border: 1px solid #fc8181;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .login-card-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .carousel-section {
                display: none;
            }

            .form-section {
                padding: 30px;
            }
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="login-body">
    <div class="login-card-wrapper">
        <!-- Sección Carrusel -->
        <div class="carousel-section">
            <div class="carousel-container">
                <div class="carousel-slide active">
                    <img src="assets/img/login1.jpg" alt="Farmacia 1" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%230d2b4e%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2214%22 fill=%22%2340e0d0%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EImagen 1%3C/text%3E%3C/svg%3E'">
                    <div class="carousel-overlay">
                        <h3>Farmacia MediClick</h3>
                        <p>Tu salud es nuestra prioridad</p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="assets/img/login2.jpg" alt="Farmacia 2" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%230d2b4e%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2214%22 fill=%22%2340e0d0%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EImagen 2%3C/text%3E%3C/svg%3E'">
                    <div class="carousel-overlay">
                        <h3>Productos de Calidad</h3>
                        <p>Medicamentos certificados y garantizados</p>
                    </div>
                </div>
                <div class="carousel-slide">
                    <img src="assets/img/login3.jpg" alt="Farmacia 3" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%220a4d68%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2214%22 fill=%22%2340e0d0%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3EImagen 3%3C/text%3E%3C/svg%3E'">
                    <div class="carousel-overlay">
                        <h3>Servicio a tu Alcance</h3>
                        <p>Atendimiento rápido y profesional</p>
                    </div>
                </div>
                <div class="carousel-dots">
                    <span class="carousel-dot active" onclick="cambiarSlide(0)"></span>
                    <span class="carousel-dot" onclick="cambiarSlide(1)"></span>
                    <span class="carousel-dot" onclick="cambiarSlide(2)"></span>
                </div>
            </div>
        </div>

        <!-- Sección Formulario -->
        <div class="form-section">
            <div class="form-section-header">
                <div class="login-logo-circle">
                    <i class="fa-solid fa-capsules"></i>
                </div>
                <h2>Medi<span style="color: var(--turquesa-base);">Click</span></h2>
                <p>Inicia sesión en tu cuenta</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-alert">
                    <?php
                    $errores = [
                        'credenciales' => 'Usuario o contraseña incorrectos.',
                        'campos'       => 'Por favor completa todos los campos.',
                        'sesion'       => 'Tu sesión ha expirado.',
                        'rol'          => 'Acceso denegado.',
                        'servidor'     => 'Error de servidor. Intenta más tarde.'
                    ];
                    echo $errores[$_GET['error']] ?? 'Error al iniciar sesión.';
                    ?>
                </div>
            <?php endif; ?>

            <form action="procesar_login.php" method="POST" id="formulario-login">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <div class="form-control-group">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="usuario" placeholder="Ingresa tu usuario" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <div class="form-control-group">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="contrasena" name="contrasena" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="btn-login">
                    <span id="texto-btn">Entrar</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        let slideActual = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const dots = document.querySelectorAll('.carousel-dot');

        function mostrarSlide(indice) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            
            slideActual = indice % slides.length;
            slides[slideActual].classList.add('active');
            dots[slideActual].classList.add('active');
        }

        function cambiarSlide(indice) {
            mostrarSlide(indice);
        }

        function siguienteSlide() {
            mostrarSlide(slideActual + 1);
        }

        // Cambiar slide cada 6 segundos
        setInterval(siguienteSlide, 6000);
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
