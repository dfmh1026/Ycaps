// --- VARIABLES GLOBALES Y SELECTORES ---
const CARRITO_STORAGE_KEY = 'ycaps-carrito';

// Evita que el navegador restaure el scroll anterior al volver con el botón
// "atrás" (por ejemplo, al regresar desde WhatsApp) — así la recarga siempre
// empieza arriba de la página.
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

// Si el cliente vuelve a esta página después de ser redirigido a WhatsApp
// (con el botón "atrás" en computador, o cambiando de vuelta a la app del
// navegador en el celular), se fuerza una recarga limpia para que el carrito
// ya vacío, los modales cerrados y la página se vean frescos desde el inicio.
function volverLimpioDesdeWhatsapp() {
    if (sessionStorage.getItem('ycaps-whatsapp-redirigido')) {
        sessionStorage.removeItem('ycaps-whatsapp-redirigido');
        window.scrollTo(0, 0);
        location.reload();
    }
}

window.addEventListener('pageshow', volverLimpioDesdeWhatsapp);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        volverLimpioDesdeWhatsapp();
    }
});

function cargarCarrito() {
    try {
        const guardado = localStorage.getItem(CARRITO_STORAGE_KEY);
        const datos = guardado ? JSON.parse(guardado) : [];
        return Array.isArray(datos) ? datos : [];
    } catch (e) {
        console.log('No se pudo leer el carrito guardado:', e);
        return [];
    }
}

function guardarCarrito() {
    try {
        localStorage.setItem(CARRITO_STORAGE_KEY, JSON.stringify(carrito));
    } catch (e) {
        console.log('No se pudo guardar el carrito:', e);
    }
}

let carrito = cargarCarrito();

const panel = document.getElementById('carrito-panel');
const abrirBtn = document.getElementById('abrir-carrito');
const cerrarBtn = document.getElementById('cerrar-carrito');
const contenedorItems = document.getElementById('items-carrito');
const contador = document.getElementById('contador-carrito');
const totalTxt = document.getElementById('precio-total');

// --- CÓDIGO PROMOCIONAL ---
// El descuento real SIEMPRE se vuelve a calcular en el servidor (ver
// wompi/crear-transaccion.php y wompi/crear-pedido-whatsapp.php) — aquí solo
// se calcula para mostrarle al cliente el total con descuento antes de pagar.
const CODIGOS_PROMOCIONALES = { 'INIT25': 0.15 };

let codigoPromoAplicado = null;

const inputCodigoPromo = document.getElementById('input-codigo-promo');
const btnAplicarPromo = document.getElementById('btn-aplicar-promo');
const mensajeCodigoPromo = document.getElementById('mensaje-codigo-promo');
const filaDescuentoPromo = document.getElementById('fila-descuento-promo');
const montoDescuentoPromo = document.getElementById('monto-descuento-promo');

btnAplicarPromo.addEventListener('click', () => {
    if (carrito.length === 0) {
        mensajeCodigoPromo.textContent = 'Agrega un producto al carrito para usar un código promocional.';
        mensajeCodigoPromo.classList.remove('exito');
        mensajeCodigoPromo.classList.add('error');
        return;
    }

    const codigo = inputCodigoPromo.value.trim().toUpperCase();

    if (codigo === '') {
        mensajeCodigoPromo.textContent = 'Escribe un código promocional.';
        mensajeCodigoPromo.classList.remove('exito');
        mensajeCodigoPromo.classList.add('error');
        return;
    }

    if (!CODIGOS_PROMOCIONALES[codigo]) {
        codigoPromoAplicado = null;
        mensajeCodigoPromo.textContent = 'Ese código promocional no es válido.';
        mensajeCodigoPromo.classList.remove('exito');
        mensajeCodigoPromo.classList.add('error');
        actualizarInterfaz();
        return;
    }

    codigoPromoAplicado = codigo;
    mensajeCodigoPromo.textContent = '¡Código aplicado! 15% de descuento.';
    mensajeCodigoPromo.classList.remove('error');
    mensajeCodigoPromo.classList.add('exito');
    actualizarInterfaz();
});

// --- EVENT LISTENERS PARA EL PANEL ---
abrirBtn.addEventListener('click', () => panel.classList.add('activo'));
cerrarBtn.addEventListener('click', () => panel.classList.remove('activo'));

// Restaurar el carrito guardado al cargar la página
actualizarInterfaz();

document.addEventListener('click', (event) => {
    if (!panel.classList.contains('activo')) return;

    const clickDentroPanel = panel.contains(event.target);
    const clickEnBotonAbrir = abrirBtn.contains(event.target);
    const clickEnAgregar = event.target.closest('.btn-agregar');

    if (!clickDentroPanel && !clickEnBotonAbrir && !clickEnAgregar) {
        panel.classList.remove('activo');
    }
});

// --- FAVORITOS ---
const FAVORITOS_STORAGE_KEY = 'ycaps-favoritos';

function cargarFavoritos() {
    try {
        const guardado = localStorage.getItem(FAVORITOS_STORAGE_KEY);
        const datos = guardado ? JSON.parse(guardado) : [];
        return Array.isArray(datos) ? datos : [];
    } catch (e) {
        console.log('No se pudo leer los favoritos guardados:', e);
        return [];
    }
}

function guardarFavoritos() {
    try {
        localStorage.setItem(FAVORITOS_STORAGE_KEY, JSON.stringify(favoritos));
    } catch (e) {
        console.log('No se pudo guardar los favoritos:', e);
    }
}

let favoritos = cargarFavoritos();

const panelFavoritos = document.getElementById('favoritos-panel');
const abrirFavoritosBtn = document.getElementById('abrir-favoritos');
const cerrarFavoritosBtn = document.getElementById('cerrar-favoritos');
const contenedorFavoritos = document.getElementById('items-favoritos');
const contadorFavoritos = document.getElementById('contador-favoritos');

abrirFavoritosBtn.addEventListener('click', () => panelFavoritos.classList.add('activo'));
cerrarFavoritosBtn.addEventListener('click', () => panelFavoritos.classList.remove('activo'));

document.addEventListener('click', (event) => {
    if (!panelFavoritos.classList.contains('activo')) return;

    const clickDentroPanel = panelFavoritos.contains(event.target);
    const clickEnBotonAbrir = abrirFavoritosBtn.contains(event.target);
    const clickEnCorazon = event.target.closest('.btn-favorito');

    if (!clickDentroPanel && !clickEnBotonAbrir && !clickEnCorazon) {
        panelFavoritos.classList.remove('activo');
    }
});

function toggleFavorito(nombre, precio, btn) {
    const existe = favoritos.some(item => item.nombre === nombre);

    if (existe) {
        favoritos = favoritos.filter(item => item.nombre !== nombre);
    } else {
        favoritos.push({ nombre, precio });
    }

    if (btn) btn.classList.toggle('activo', !existe);

    guardarFavoritos();
    actualizarInterfazFavoritos();
}

function eliminarDeFavoritos(nombre) {
    favoritos = favoritos.filter(item => item.nombre !== nombre);
    guardarFavoritos();
    actualizarInterfazFavoritos();

    const btnTarjeta = document.querySelector(`.btn-favorito[data-nombre="${nombre}"]`);
    if (btnTarjeta) btnTarjeta.classList.remove('activo');
}

function actualizarInterfazFavoritos() {
    contenedorFavoritos.innerHTML = '';

    if (favoritos.length === 0) {
        const vacio = document.createElement('p');
        vacio.classList.add('favoritos-vacio');
        vacio.textContent = 'Aún no tienes gorras favoritas.';
        contenedorFavoritos.appendChild(vacio);
    }

    favoritos.forEach(item => {
        const div = document.createElement('div');
        div.classList.add('item-en-carrito');

        const detalles = document.createElement('div');
        detalles.classList.add('item-detalles');

        const titulo = document.createElement('h4');
        titulo.textContent = item.nombre;
        detalles.appendChild(titulo);

        if (item.precio > 0) {
            const precio = document.createElement('p');
            precio.textContent = `$${item.precio.toLocaleString('es-CO')}`;
            detalles.appendChild(precio);
        }

        const acciones = document.createElement('div');
        acciones.classList.add('item-favorito-acciones');

        if (item.precio > 0) {
            const btnAgregar = document.createElement('button');
            btnAgregar.classList.add('btn-agregar-mini');
            btnAgregar.textContent = 'Agregar al carrito';
            btnAgregar.addEventListener('click', () => agregarAlCarrito(item.nombre, item.precio, null));
            acciones.appendChild(btnAgregar);
        }

        const btnQuitar = document.createElement('button');
        btnQuitar.classList.add('btn-eliminar');
        btnQuitar.textContent = 'Quitar';
        btnQuitar.addEventListener('click', () => eliminarDeFavoritos(item.nombre));
        acciones.appendChild(btnQuitar);

        div.append(detalles, acciones);
        contenedorFavoritos.appendChild(div);
    });

    contadorFavoritos.textContent = favoritos.length;
}

// Restaurar favoritos guardados y marcar los corazones activos en el catálogo
actualizarInterfazFavoritos();
document.querySelectorAll('.btn-favorito').forEach(btn => {
    const nombre = btn.dataset.nombre;
    const precio = Number(btn.dataset.precio) || 0;

    if (favoritos.some(item => item.nombre === nombre)) {
        btn.classList.add('activo');
    }

    btn.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleFavorito(nombre, precio, btn);
    });
});

const modalImagen = document.getElementById('modal-imagen');
const modalImg = document.getElementById('modal-img');
const modalCaption = document.getElementById('modal-caption');
const cerrarModal = document.getElementById('cerrar-modal');
const modalControls = document.getElementById('modal-controls');
const modalPrevBtn = document.getElementById('modal-prev-btn');
const modalNextBtn = document.getElementById('modal-next-btn');
const modalIndicator = document.getElementById('modal-indicator');

let modalGaleria = [];
let modalGaleriaIndex = 0;

function abrirModalImagen(src, alt, galeria = null, index = 0) {
    modalImg.src = src;
    modalImg.alt = alt;
    modalCaption.textContent = alt || 'Gorra Ycaps';
    
    if (galeria && galeria.length > 1) {
        modalGaleria = galeria;
        modalGaleriaIndex = index;
        modalControls.style.display = 'flex';
        modalIndicator.textContent = `${index + 1}/${galeria.length}`;
    } else {
        modalGaleria = [];
        modalControls.style.display = 'none';
    }
    
    modalImagen.classList.add('activo');
}

function updateModalImage() {
    if (modalGaleria.length === 0) return;
    const img = modalGaleria[modalGaleriaIndex];
    modalImg.src = img.src;
    modalImg.alt = img.alt;
    modalCaption.textContent = img.alt || 'Gorra Ycaps';
    modalIndicator.textContent = `${modalGaleriaIndex + 1}/${modalGaleria.length}`;
}

function cerrarModalImagen() {
    modalImagen.classList.remove('activo');
    modalImg.src = '';
    modalGaleria = [];
}

modalPrevBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (modalGaleria.length > 1) {
        modalGaleriaIndex = (modalGaleriaIndex - 1 + modalGaleria.length) % modalGaleria.length;
        updateModalImage();
    }
});

modalNextBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (modalGaleria.length > 1) {
        modalGaleriaIndex = (modalGaleriaIndex + 1) % modalGaleria.length;
        updateModalImage();
    }
});

const tarjetasProductos = document.querySelectorAll('.tarjeta-producto');

function getVisibleImage(tarjeta) {
    const imagenes = Array.from(tarjeta.querySelectorAll('.imagen-producto'));
    const visible = imagenes.find(img => img.classList.contains('activo'));
    return visible || imagenes[0];
}

function initGalerias() {
    tarjetasProductos.forEach(tarjeta => {
        const imagenes = Array.from(tarjeta.querySelectorAll('.imagen-producto'));
        if (imagenes.length === 0) return;

        // Wrap all images (even single) in gallery-wrapper for consistent layout
        const wrapper = document.createElement('div');
        wrapper.className = 'gallery-wrapper';
        tarjeta.insertBefore(wrapper, imagenes[0]);
        imagenes.forEach((img, index) => {
            wrapper.appendChild(img);
            img.classList.toggle('activo', index === 0);
            img.setAttribute('data-gallery-index', index);
        });

        // Only add navigation buttons when there are multiple images
        if (imagenes.length <= 1) return;

        let currentIndex = 0;

        const nav = document.createElement('div');
        nav.className = 'gallery-nav';

        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'nav-btn prev-btn';
        prevBtn.textContent = '‹';
        prevBtn.setAttribute('aria-label', 'Ver la imagen anterior');

        const indicator = document.createElement('span');
        indicator.className = 'gallery-indicator';
        indicator.textContent = `${currentIndex + 1}/${imagenes.length}`;

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'nav-btn next-btn';
        nextBtn.textContent = '›';
        nextBtn.setAttribute('aria-label', 'Ver la siguiente imagen');

        function updateGaleria() {
            imagenes.forEach((img, index) => {
                img.classList.toggle('activo', index === currentIndex);
            });
            indicator.textContent = `${currentIndex + 1}/${imagenes.length}`;
        }

        prevBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            currentIndex = (currentIndex - 1 + imagenes.length) % imagenes.length;
            updateGaleria();
        });

        nextBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            currentIndex = (currentIndex + 1) % imagenes.length;
            updateGaleria();
        });

        nav.append(prevBtn, indicator, nextBtn);
        const titulo = tarjeta.querySelector('h3');
        tarjeta.insertBefore(nav, titulo);
    });
}

if (document.readyState !== 'loading') {
    initGalerias();
} else {
    document.addEventListener('DOMContentLoaded', initGalerias);
}

cerrarModal.addEventListener('click', cerrarModalImagen);
modalImagen.addEventListener('click', (event) => {
    if (event.target === modalImagen) {
        cerrarModalImagen();
    }
});

tarjetasProductos.forEach(tarjeta => {
    tarjeta.addEventListener('click', (event) => {
        if (event.target.closest('.btn-agregar') || event.target.closest('.nav-btn') || event.target.closest('.selector-cantidad') || event.target.closest('h3')) return;
        if (window.getSelection().toString().length > 0) return;
        const imagen = getVisibleImage(tarjeta);
        if (imagen) {
            const imagenes = Array.from(tarjeta.querySelectorAll('.imagen-producto'));
            const index = imagenes.indexOf(imagen);
            abrirModalImagen(imagen.src, imagen.alt, imagenes, index);
        }
    });
});

// --- SELECTOR DE CANTIDAD POR PRODUCTO ---
tarjetasProductos.forEach(tarjeta => {
    const input = tarjeta.querySelector('.input-cantidad');
    const btnRestar = tarjeta.querySelector('.btn-restar');
    const btnSumar = tarjeta.querySelector('.btn-sumar');
    if (!input || !btnRestar || !btnSumar) return;

    const cantidadMin = Number(input.min) || 1;
    const cantidadMax = Number(input.max) || 99;

    function ajustarCantidad(delta) {
        const valorActual = parseInt(input.value, 10) || cantidadMin;
        const nuevoValor = Math.min(cantidadMax, Math.max(cantidadMin, valorActual + delta));
        input.value = nuevoValor;
    }

    btnRestar.addEventListener('click', () => ajustarCantidad(-1));
    btnSumar.addEventListener('click', () => ajustarCantidad(1));

    input.addEventListener('change', () => {
        const valor = parseInt(input.value, 10) || cantidadMin;
        input.value = Math.min(cantidadMax, Math.max(cantidadMin, valor));
    });
});

// --- FUNCIONES DEL CARRITO ---

// Añadir un modelo de gorra al carrito
function agregarAlCarrito(nombre, precio, btn) {
    let cantidad = 1;
    const tarjeta = btn ? btn.closest('.tarjeta-producto') : null;
    const inputCantidad = tarjeta ? tarjeta.querySelector('.input-cantidad') : null;
    if (inputCantidad) {
        cantidad = Math.max(1, parseInt(inputCantidad.value, 10) || 1);
        inputCantidad.value = inputCantidad.min || 1;
    }

    const existe = carrito.find(item => item.nombre === nombre);

    if (existe) {
        existe.cantidad += cantidad;
    } else {
        carrito.push({ nombre, precio, cantidad });
    }

    guardarCarrito();
    actualizarInterfaz();

    // Abre automáticamente el panel para dar feedback al usuario
    panel.classList.add('activo');

    // Microanimación: pulso en el botón del carrito al agregar un producto
    abrirBtn.classList.remove('pulso');
    requestAnimationFrame(() => abrirBtn.classList.add('pulso'));
}

// Eliminar un producto por completo
function eliminarDelCarrito(nombre) {
    carrito = carrito.filter(item => item.nombre !== nombre);
    guardarCarrito();

    // Si el carrito queda vacío, se quita también el código promocional
    // aplicado — no debe quedar un descuento "guardado" sin productos.
    if (carrito.length === 0) {
        codigoPromoAplicado = null;
        inputCodigoPromo.value = '';
        mensajeCodigoPromo.textContent = '';
        mensajeCodigoPromo.classList.remove('exito', 'error');
    }

    actualizarInterfaz();
}

// Renderizar los productos en la interfaz y calcular totales
function actualizarInterfaz() {
    contenedorItems.innerHTML = '';
    let total = 0;
    let totalGorras = 0;

    carrito.forEach(item => {
        total += item.precio * item.cantidad;
        totalGorras += item.cantidad;

        const div = document.createElement('div');
        div.classList.add('item-en-carrito');

        const detalles = document.createElement('div');
        detalles.classList.add('item-detalles');

        const titulo = document.createElement('h4');
        titulo.textContent = `${item.nombre} (x${item.cantidad})`;

        const precio = document.createElement('p');
        precio.textContent = `$${(item.precio * item.cantidad).toLocaleString('es-CO')}`;

        detalles.append(titulo, precio);

        const btnEliminar = document.createElement('button');
        btnEliminar.classList.add('btn-eliminar');
        btnEliminar.textContent = 'Eliminar';
        btnEliminar.addEventListener('click', () => eliminarDelCarrito(item.nombre));

        div.append(detalles, btnEliminar);
        contenedorItems.appendChild(div);
    });

    // Actualizar el botón flotante y el total de la factura
    contador.innerText = totalGorras;

    // El código promocional solo se puede usar si hay productos en el carrito
    inputCodigoPromo.disabled = carrito.length === 0;
    btnAplicarPromo.disabled = carrito.length === 0;

    const porcentajeDescuento = codigoPromoAplicado ? CODIGOS_PROMOCIONALES[codigoPromoAplicado] : 0;
    const montoDescuento = Math.round(total * porcentajeDescuento);

    if (porcentajeDescuento > 0 && total > 0) {
        filaDescuentoPromo.hidden = false;
        filaDescuentoPromo.style.display = 'flex';
        montoDescuentoPromo.innerText = `-$${montoDescuento.toLocaleString('es-CO')}`;
    } else {
        filaDescuentoPromo.hidden = true;
        filaDescuentoPromo.style.display = 'none';
        montoDescuentoPromo.innerText = '-$0';
    }

    totalTxt.innerText = `$${(total - montoDescuento).toLocaleString('es-CO')}`;
}

// --- MODAL DE CHECKOUT (DATOS DE ENVÍO Y PAGO) ---
const modalCheckout = document.getElementById('modal-checkout');
const btnAbrirCheckout = document.getElementById('btn-abrir-checkout');
const cerrarModalCheckoutBtn = document.getElementById('cerrar-modal-checkout');
const formCheckout = document.getElementById('form-checkout');
const btnPedirWhatsapp = document.getElementById('btn-pedir-whatsapp');
const checkoutMensaje = document.getElementById('checkout-mensaje');

function abrirModalCheckout() {
    if (carrito.length === 0) {
        alert("Tu carrito está vacío. ¡Añade algunas Ycaps primero!");
        return;
    }
    checkoutMensaje.textContent = '';
    checkoutMensaje.classList.remove('error');
    modalCheckout.classList.add('activo');
}

function cerrarModalCheckout() {
    modalCheckout.classList.remove('activo');
}

btnAbrirCheckout.addEventListener('click', abrirModalCheckout);
cerrarModalCheckoutBtn.addEventListener('click', cerrarModalCheckout);
// A diferencia de los demás modales, este solo se cierra con el botón "×" —
// es fácil cerrarlo por accidente haciendo clic afuera mientras se llena el formulario.

// --- MODALES LEGALES (Política de Privacidad y Términos y Condiciones) ---
function inicializarModalLegal(modalId, abrirId, cerrarId) {
    const modal = document.getElementById(modalId);
    const abrirBtnLegal = document.getElementById(abrirId);
    const cerrarBtnLegal = document.getElementById(cerrarId);

    abrirBtnLegal.addEventListener('click', (event) => {
        event.preventDefault();
        modal.classList.add('activo');
    });

    cerrarBtnLegal.addEventListener('click', () => {
        modal.classList.remove('activo');
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('activo');
        }
    });

    return modal;
}

const modalPolitica = inicializarModalLegal('modal-politica', 'abrir-politica-privacidad', 'cerrar-modal-politica');
inicializarModalLegal('modal-terminos', 'abrir-terminos-condiciones', 'cerrar-modal-terminos');

// Acordeones de la política de privacidad, ocultos hasta que el usuario hace clic
function inicializarAcordeon(toggleId, contenidoId) {
    const toggle = document.getElementById(toggleId);
    const contenido = document.getElementById(contenidoId);

    toggle.addEventListener('click', () => {
        const expandido = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expandido));
        contenido.hidden = expandido;
    });

    return { toggle, contenido };
}

inicializarAcordeon('toggle-tratamiento-datos', 'contenido-tratamiento-datos');
const acordeonCookies = inicializarAcordeon('toggle-cookies', 'contenido-cookies');

// --- AVISO DE COOKIES ---
const COOKIES_STORAGE_KEY = 'ycaps-cookies-aceptadas';
const GA_MEDIDA_ID = 'G-Z2XV57ZH7W';
const bannerCookies = document.getElementById('banner-cookies');
const btnCookiesAceptar = document.getElementById('btn-cookies-aceptar');
const bannerCookiesEnlace = document.getElementById('banner-cookies-enlace');

// Google Analytics solo se carga si el usuario aceptó el aviso de cookies —
// antes se cargaba siempre, lo cual contradecía la propia Política de Privacidad.
function cargarGoogleAnalytics() {
    if (window.gaCargado) return;
    window.gaCargado = true;

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${GA_MEDIDA_ID}`;
    document.head.appendChild(script);

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_MEDIDA_ID);
}

if (localStorage.getItem(COOKIES_STORAGE_KEY)) {
    cargarGoogleAnalytics();
} else {
    bannerCookies.hidden = false;
    document.body.style.paddingBottom = `${bannerCookies.offsetHeight}px`;
}

btnCookiesAceptar.addEventListener('click', () => {
    localStorage.setItem(COOKIES_STORAGE_KEY, 'true');
    bannerCookies.hidden = true;
    document.body.style.paddingBottom = '';
    cargarGoogleAnalytics();
});

bannerCookiesEnlace.addEventListener('click', (event) => {
    event.preventDefault();
    modalPolitica.classList.add('activo');
    acordeonCookies.toggle.setAttribute('aria-expanded', 'true');
    acordeonCookies.contenido.hidden = false;
    acordeonCookies.toggle.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

function obtenerDatosComprador() {
    return {
        nombre: document.getElementById('checkout-nombre').value.trim(),
        cedula: document.getElementById('checkout-cedula').value.trim(),
        email: document.getElementById('checkout-email').value.trim(),
        telefono: document.getElementById('checkout-telefono').value.trim(),
        direccion: document.getElementById('checkout-direccion').value.trim(),
        ciudad: document.getElementById('checkout-ciudad').value.trim(),
        departamento: document.getElementById('checkout-departamento').value.trim()
    };
}

// --- ENVIAR PEDIDO POR WHATSAPP (incluye datos del comprador) ---
// Antes de abrir WhatsApp, el pedido se guarda en la base de datos (estado
// "pendiente", método "whatsapp") para que quede registrado en el panel admin
// y la tienda pueda marcarlo como pagado luego de verificar la transferencia.
btnPedirWhatsapp.addEventListener('click', async () => {
    if (!formCheckout.checkValidity()) {
        formCheckout.reportValidity();
        return;
    }

    const comprador = obtenerDatosComprador();

    checkoutMensaje.textContent = 'Registrando tu pedido...';
    checkoutMensaje.classList.remove('error');
    btnPedirWhatsapp.disabled = true;

    let referencia = '';
    try {
        const respuesta = await fetch('wompi/crear-pedido-whatsapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: carrito, comprador, codigo_promocional: codigoPromoAplicado || '' })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.ok) {
            throw new Error(datos.error || 'No se pudo registrar el pedido.');
        }

        referencia = datos.referencia;
    } catch (error) {
        checkoutMensaje.textContent = error.message || 'No se pudo registrar el pedido. Intenta de nuevo.';
        checkoutMensaje.classList.add('error');
        console.log('Error al registrar pedido de WhatsApp:', error);
        btnPedirWhatsapp.disabled = false;
        return;
    }

    let textoMensaje = "Hola, quiero comprar estas gorras:\n\n";
    carrito.forEach(item => {
        textoMensaje += `- ${item.nombre} x${item.cantidad}\n`;
    });
    textoMensaje += `\nDatos de envío:\n`;
    textoMensaje += `Nombre: ${comprador.nombre}\n`;
    textoMensaje += `Cédula: ${comprador.cedula}\n`;
    textoMensaje += `Email: ${comprador.email}\n`;
    textoMensaje += `Teléfono: ${comprador.telefono}\n`;
    textoMensaje += `Dirección: ${comprador.direccion}, ${comprador.ciudad}, ${comprador.departamento}\n`;
    textoMensaje += `\nReferencia: ${referencia}`;
    textoMensaje += "\n¿Tienen stock?";

    carrito = [];
    codigoPromoAplicado = null;
    guardarCarrito();
    actualizarInterfaz();
    btnPedirWhatsapp.disabled = false;
    sessionStorage.setItem('ycaps-whatsapp-redirigido', '1');
    sessionStorage.setItem('ycaps-whatsapp-mensaje-pendiente', '1');

    const urlTexto = encodeURIComponent(textoMensaje);
    const waLink = `https://wa.me/573004710483?text=${urlTexto}`;
    window.location.href = waLink;
});

// --- PAGAR CON WOMPI (genera la URL firmada del checkout y redirige) ---
formCheckout.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formCheckout.checkValidity()) {
        formCheckout.reportValidity();
        return;
    }

    const btnPagarWompi = document.getElementById('btn-pagar-wompi');
    const comprador = obtenerDatosComprador();

    checkoutMensaje.textContent = 'Conectando con Wompi...';
    checkoutMensaje.classList.remove('error');
    btnPagarWompi.disabled = true;

    try {
        const respuesta = await fetch('wompi/crear-transaccion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: carrito, comprador, codigo_promocional: codigoPromoAplicado || '' })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.checkout_url) {
            throw new Error(datos.error || 'No se pudo iniciar el pago con Wompi.');
        }

        window.location.href = datos.checkout_url;
    } catch (error) {
        checkoutMensaje.textContent = error.message || 'No se pudo iniciar el pago. Intenta de nuevo o usa WhatsApp.';
        checkoutMensaje.classList.add('error');
        console.log('Error al crear transacción Wompi:', error);
    } finally {
        btnPagarWompi.disabled = false;
    }
});

// --- FORMULARIO DE CONTACTO ---
const formContacto = document.getElementById('form-contacto');
const contactoMensajeEstado = document.getElementById('contacto-mensaje-estado');

formContacto.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formContacto.checkValidity()) {
        formContacto.reportValidity();
        return;
    }

    const btnEnviarContacto = document.getElementById('btn-enviar-contacto');
    const datosContacto = {
        nombre: document.getElementById('contacto-nombre').value.trim(),
        email: document.getElementById('contacto-email').value.trim(),
        telefono: document.getElementById('contacto-telefono').value.trim(),
        mensaje: document.getElementById('contacto-texto').value.trim(),
    };

    contactoMensajeEstado.textContent = 'Enviando mensaje...';
    contactoMensajeEstado.classList.remove('error');
    btnEnviarContacto.disabled = true;

    try {
        const respuesta = await fetch('wompi/contacto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datosContacto)
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.ok) {
            throw new Error(datos.error || 'No se pudo enviar el mensaje.');
        }

        contactoMensajeEstado.textContent = '¡Mensaje enviado! Gracias por escribirnos, te responderemos pronto.';
        formContacto.reset();
    } catch (error) {
        contactoMensajeEstado.textContent = error.message || 'No se pudo enviar el mensaje. Intenta de nuevo.';
        contactoMensajeEstado.classList.add('error');
        console.log('Error al enviar el formulario de contacto:', error);
    } finally {
        btnEnviarContacto.disabled = false;
    }
});

// --- BANNER SUPERIOR DE RESULTADO (pago con Wompi o pedido por WhatsApp) ---
function mostrarBannerResultado(tipo, mensajeHtml, autoCerrar = true) {
    const banner = document.getElementById('banner-resultado-pago');
    if (!banner) return;

    banner.innerHTML = `<span>${mensajeHtml}</span>`
        + `<button type="button" id="banner-cerrar-btn" aria-label="Cerrar aviso" style="background:none;border:none;color:inherit;font-size:1.3rem;line-height:1;cursor:pointer;margin-left:14px;vertical-align:middle">&times;</button>`;

    banner.dataset.tipo = tipo;
    banner.hidden = false;
    banner.scrollIntoView({ behavior: 'smooth', block: 'center' });

    const cerrarBanner = () => {
        banner.style.transition = 'opacity 0.6s ease';
        banner.style.opacity    = '0';
        setTimeout(() => {
            banner.hidden        = true;
            banner.style.opacity = '';
        }, 650);
    };

    const btnCerrar = document.getElementById('banner-cerrar-btn');
    if (btnCerrar) btnCerrar.addEventListener('click', cerrarBanner);

    // Si hay enlace de descarga, cerrar el banner poco después de que el cliente descargue
    const linkDescarga = document.getElementById('banner-descarga-recibo');
    if (linkDescarga) {
        linkDescarga.addEventListener('click', () => setTimeout(cerrarBanner, 800));
    }

    if (autoCerrar) {
        setTimeout(cerrarBanner, 10000);
    }
}

// --- MOSTRAR RESULTADO DEL PAGO AL VOLVER DE WOMPI ---
(function mostrarResultadoPago() {
    const params     = new URLSearchParams(window.location.search);
    const estadoPago = params.get('pago');
    if (!estadoPago) return;

    const ref = params.get('ref') || '';
    let mensajeHtml = '';
    let autoCerrar  = true;

    if (estadoPago === 'exito') {
        // Vaciar carrito al confirmar pago exitoso
        carrito = [];
        guardarCarrito();
        actualizarInterfaz();

        // No cerrar automáticamente: dejamos que el cliente descargue su recibo primero
        autoCerrar = false;

        mensajeHtml = '¡Pago exitoso! Gracias por tu compra. Te contactaremos pronto para coordinar el envío.'
            + (ref ? ` — Referencia: <strong>${ref}</strong>` : '')
            + (ref ? ` <a id="banner-descarga-recibo" href="wompi/recibo-pdf.php?ref=${encodeURIComponent(ref)}" target="_blank" style="color:#fff;text-decoration:underline;margin-left:8px;white-space:nowrap;font-weight:700">Descargar recibo PDF</a>` : '');
    } else if (estadoPago === 'pendiente') {
        mensajeHtml = 'Tu pago está siendo procesado. Te notificaremos cuando se confirme.'
            + (ref ? ` — Referencia: <strong>${ref}</strong>` : '');
    } else {
        mensajeHtml = 'El pago no se completó. Inténtalo de nuevo o pide tu pedido por WhatsApp.';
    }

    mostrarBannerResultado(estadoPago, mensajeHtml, autoCerrar);

    // Limpiar los parámetros de la URL sin recargar la página
    window.history.replaceState({}, document.title, window.location.pathname);
}());

// --- MOSTRAR AVISO AL VOLVER DEL PEDIDO POR WHATSAPP ---
// No confirma la compra (eso requiere verificar manualmente la transferencia
// y el comprobante con el banco) — solo avisa que el pedido quedó en proceso.
(function mostrarAvisoWhatsapp() {
    if (!sessionStorage.getItem('ycaps-whatsapp-mensaje-pendiente')) return;
    sessionStorage.removeItem('ycaps-whatsapp-mensaje-pendiente');

    mostrarBannerResultado(
        'whatsapp',
        '¡Tu pedido fue recibido! En breve te enviaremos por WhatsApp la información de pago para completar tu compra.'
    );
}());

// --- MODAL DE RASTREO DE PEDIDO ---
const modalRastreo        = document.getElementById('modal-rastreo');
const btnAbrirRastreo     = document.getElementById('btn-abrir-rastreo');
const cerrarRastreoBtn    = document.getElementById('cerrar-modal-rastreo');
const formRastreo         = document.getElementById('form-rastreo');
const rastreoResultado    = document.getElementById('rastreo-resultado');
const rastreoMensaje      = document.getElementById('rastreo-mensaje');

if (btnAbrirRastreo) {
    btnAbrirRastreo.addEventListener('click', () => {
        rastreoResultado.hidden = true;
        rastreoMensaje.textContent = '';
        formRastreo.reset();
        modalRastreo.classList.add('activo');
    });
}

if (cerrarRastreoBtn) {
    cerrarRastreoBtn.addEventListener('click', () => modalRastreo.classList.remove('activo'));
}

if (modalRastreo) {
    modalRastreo.addEventListener('click', (e) => {
        if (e.target === modalRastreo) modalRastreo.classList.remove('activo');
    });
}

if (formRastreo) {
    formRastreo.addEventListener('submit', async (e) => {
        e.preventDefault();
        const referencia  = document.getElementById('rastreo-referencia').value.trim();
        const btnBuscar   = document.getElementById('btn-buscar-pedido');

        rastreoMensaje.textContent = 'Buscando pedido...';
        rastreoResultado.hidden    = true;
        btnBuscar.disabled         = true;

        try {
            const resp  = await fetch('wompi/rastrear-pedido.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ referencia }),
            });
            const datos = await resp.json();

            if (!resp.ok || datos.error) {
                rastreoMensaje.textContent = datos.error || 'No se encontró el pedido.';
                return;
            }

            rastreoMensaje.textContent = '';
            document.getElementById('rastreo-ref').textContent    = datos.referencia;
            document.getElementById('rastreo-nombre').textContent  = datos.nombre;
            document.getElementById('rastreo-ciudad').textContent  = datos.ciudad + (datos.departamento ? ', ' + datos.departamento : '');
            document.getElementById('rastreo-total').textContent   = '$' + datos.total.toLocaleString('es-CO');
            document.getElementById('rastreo-estado').textContent  = datos.estadoTexto;
            document.getElementById('rastreo-estado').dataset.estado = datos.estado;
            document.getElementById('rastreo-fecha').textContent   = new Date(datos.fecha).toLocaleDateString('es-CO', { year:'numeric', month:'long', day:'numeric' });

            const guiaFila = document.getElementById('rastreo-guia-fila');
            if (datos.guia) {
                document.getElementById('rastreo-guia').textContent = datos.guia;
                guiaFila.hidden = false;
            } else {
                guiaFila.hidden = true;
            }

            const listaEl = document.getElementById('rastreo-items');
            listaEl.innerHTML = '';
            datos.items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item;
                listaEl.appendChild(li);
            });

            const descargaDiv  = document.getElementById('rastreo-descarga');
            const descargaLink = document.getElementById('rastreo-descarga-link');
            if (datos.estado === 'aprobado') {
                descargaLink.href = 'wompi/recibo-pdf.php?ref=' + encodeURIComponent(datos.referencia);
                descargaDiv.hidden = false;
            } else {
                descargaDiv.hidden = true;
            }

            rastreoResultado.hidden = false;
        } catch (err) {
            rastreoMensaje.textContent = 'Error al consultar. Intenta de nuevo.';
        } finally {
            btnBuscar.disabled = false;
        }
    });
}

// Click en logo o H1 recarga la página (mejor experiencia móvil/desktop)
document.addEventListener('DOMContentLoaded', () => {
    const logoImg = document.querySelector('.imagen-logo');
    const logoH1 = document.querySelector('.logo p');

    function recargarPagina(event) {
        event.preventDefault();
        // Usar location.reload para refrescar la página actual
        window.location.reload();
    }

    if (logoImg) logoImg.addEventListener('click', recargarPagina);
    if (logoH1) logoH1.addEventListener('click', recargarPagina);
});

// --- ANIMACIONES DE ENTRADA AL HACER SCROLL ---
const elementosReveal = document.querySelectorAll('.reveal');

if ('IntersectionObserver' in window) {
    const observadorReveal = new IntersectionObserver((entradas) => {
        entradas.forEach(entrada => {
            if (entrada.isIntersecting) {
                entrada.target.classList.add('visible');
                observadorReveal.unobserve(entrada.target);
            }
        });
    }, { threshold: 0.15 });

    elementosReveal.forEach(el => observadorReveal.observe(el));
} else {
    elementosReveal.forEach(el => el.classList.add('visible'));
}

// --- BOTÓN VOLVER ARRIBA ---
const btnArriba = document.getElementById('btn-arriba');

if (btnArriba) {
    window.addEventListener('scroll', () => {
        btnArriba.classList.toggle('visible', window.scrollY > 480);
    });

    btnArriba.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// ── Filtros del catálogo ──
(function () {
    const botones   = document.querySelectorAll('.btn-filtro');
    const tarjetas  = document.querySelectorAll('.tarjeta-producto[data-categoria]');
    if (!botones.length || !tarjetas.length) return;

    function filtrar(categoria) {
        tarjetas.forEach(t => {
            const cat = t.dataset.categoria;
            if (categoria === 'todos' || cat === categoria) {
                t.classList.remove('oculta-filtro');
            } else {
                t.classList.add('oculta-filtro');
            }
        });
    }

    botones.forEach(btn => {
        btn.addEventListener('click', () => {
            botones.forEach(b => b.classList.remove('activo-filtro'));
            btn.classList.add('activo-filtro');
            filtrar(btn.dataset.filtro);
        });
    });
})();

// ── Nav activo al hacer scroll ──
(function () {
    const secciones = document.querySelectorAll('section[id]');
    const links     = document.querySelectorAll('.nav-link-sec[href^="#"]');
    if (!secciones.length || !links.length) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                links.forEach(l => l.classList.remove('activo-nav'));
                const link = document.querySelector(`.nav-link-sec[href="#${entry.target.id}"]`);
                if (link) link.classList.add('activo-nav');
            }
        });
    }, { rootMargin: '-40% 0px -55% 0px' });

    secciones.forEach(s => observer.observe(s));
})();

// Intentar forzar reproducción del video en móvil: asegurar muted y playsinline
document.addEventListener('DOMContentLoaded', () => {
    const video = document.querySelector('.hero-video');
    if (!video) return;
    try {
        video.muted = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        // algunos navegadores requieren llamar a play() desde código
        const playPromise = video.play();
        if (playPromise && typeof playPromise.then === 'function') {
            playPromise.catch((err) => {
                // no bloquear; registrar para diagnóstico
                console.log('hero video play rejected:', err);
            });
        }
    } catch (e) {
        console.log('error forcing hero video play', e);
    }
});