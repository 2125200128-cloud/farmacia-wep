


const estilosSearch = document.createElement('style');
estilosSearch.innerHTML = `
    .search-results-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        border-radius: 0 0 8px 8px;
    }
    .search-item {
        padding: 10px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #f0f0f0;
    }
    .search-item:hover {
        background-color: #f8f9fa;
    }
    .search-item .item-stock {
        font-size: 0.85em;
        color: #666;
    }
    .buscador-producto-modal, #producto_nombre {
        position: relative;
    }
`;
document.head.appendChild(estilosSearch);


(function initLayout() {
    const sidebar          = document.getElementById('sidebar');
    const contenidoWrapper = document.getElementById('contenido-wrapper');
    const btnToggle        = document.getElementById('btn-toggle');

    if (!sidebar) return;

    if (btnToggle) {
        btnToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleMovil();
            } else {
                toggleEscritorio();
            }
        });
    }

    function toggleEscritorio() {
        sidebar.classList.toggle('colapsado');
        contenidoWrapper.classList.toggle('expandido');
    }

    function toggleMovil() {
        sidebar.classList.contains('movil-abierto') ? cerrarMovil() : abrirMovil();
    }

    function abrirMovil() {
        sidebar.classList.add('movil-abierto');
        crearOverlay();
    }

    function cerrarMovil() {
        sidebar.classList.remove('movil-abierto');
        quitarOverlay();
    }

    function crearOverlay() {
        if (document.getElementById('sidebar-overlay')) return;
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.id = 'sidebar-overlay';
        overlay.addEventListener('click', cerrarMovil);
        document.body.appendChild(overlay);
    }

    function quitarOverlay() {
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) overlay.remove();
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') cerrarMovil();
    });

    
    const rutaActual = window.location.pathname;
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        const href = item.getAttribute('href');
        if (href && rutaActual.includes(href)) {
            item.classList.add('active');
        }
    });

    const tituloPagina = document.getElementById('pagina-titulo');
    if (window.paginaTitulo && tituloPagina) {
        tituloPagina.textContent = window.paginaTitulo;
    }

    
    document.querySelectorAll('.btn-cerrar-modal, .btn-modal-cancelar').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modal = e.target.closest('.modal-overlay');
            if (modal) modal.style.display = 'none';
        });
    });
})();


(function initLogin() {
    const formulario   = document.getElementById('formulario-login');
    if (!formulario) return;

    const btnOjo       = document.getElementById('btn-ojo');
    const campoPass    = document.getElementById('contrasena');
    const iconoOjo     = document.getElementById('icono-ojo');
    const btnLogin     = document.getElementById('btn-login');
    const textoBtn     = document.getElementById('texto-btn');
    const spinner      = document.getElementById('spinner-btn');
    const mensajeError = document.getElementById('mensaje-error');
    const textoError   = document.getElementById('texto-error');

    if (btnOjo) {
        btnOjo.addEventListener('click', () => {
            const visible  = campoPass.type === 'text';
            campoPass.type = visible ? 'password' : 'text';
            iconoOjo.className = visible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        });
    }

    function mostrarError(mensaje) {
        textoError.textContent = mensaje;
        mensajeError.classList.add('visible');
    }

    formulario.addEventListener('submit', function(e) {
        mensajeError.classList.remove('visible');
        const usuario    = document.getElementById('usuario').value.trim();
        const contrasena = campoPass.value.trim();

        if (!usuario || !contrasena) {
            e.preventDefault();
            mostrarError('Por favor completa todos los campos.');
            return;
        }

        
        textoBtn.style.display = 'none';
        spinner.style.display  = 'block';
        btnLogin.disabled      = true;
    });

    
    const errores = {
        credenciales : 'Usuario o contraseña incorrectos.',
        campos       : 'Por favor completa todos los campos.',
        sesion       : 'Tu sesión ha expirado. Inicia sesión nuevamente.',
        rol          : 'Tu cuenta no tiene un rol asignado. Contacta al encargado.',
        servidor     : 'Error de conexión. Intenta más tarde.',
    };
    const params = new URLSearchParams(window.location.search);
    const error  = params.get('error');
    if (error && errores[error]) mostrarError(errores[error]);

    
    const carruselContainer = document.getElementById('carrusel-imagenes');
    if (carruselContainer) {
        const imagenes = carruselContainer.querySelectorAll('.imagen-carrusel');
        const puntos = document.querySelectorAll('.puntos-deco .punto');
        let indiceActual = 0;

        function cambiarImagen(indice) {
            imagenes.forEach((img, idx) => {
                img.classList.remove('activa');
                if (puntos[idx]) puntos[idx].classList.remove('activo');
            });
            imagenes[indice].classList.add('activa');
            if (puntos[indice]) puntos[indice].classList.add('activo');
        }

        setInterval(() => {
            indiceActual = (indiceActual + 1) % imagenes.length;
            cambiarImagen(indiceActual);
        }, 6000);
    }
})();

