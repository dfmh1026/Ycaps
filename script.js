// --- VARIABLES GLOBALES Y SELECTORES ---
const CARRITO_STORAGE_KEY = 'ycaps-carrito';

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
        if (event.target.closest('.btn-agregar') || event.target.closest('.nav-btn') || event.target.closest('.selector-cantidad')) return;
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
    totalTxt.innerText = `$${total.toLocaleString('es-CO')}`;
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
modalCheckout.addEventListener('click', (event) => {
    if (event.target === modalCheckout) {
        cerrarModalCheckout();
    }
});

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
const bannerCookies = document.getElementById('banner-cookies');
const btnCookiesAceptar = document.getElementById('btn-cookies-aceptar');
const bannerCookiesEnlace = document.getElementById('banner-cookies-enlace');

if (!localStorage.getItem(COOKIES_STORAGE_KEY)) {
    bannerCookies.hidden = false;
    document.body.style.paddingBottom = `${bannerCookies.offsetHeight}px`;
}

btnCookiesAceptar.addEventListener('click', () => {
    localStorage.setItem(COOKIES_STORAGE_KEY, 'true');
    bannerCookies.hidden = true;
    document.body.style.paddingBottom = '';
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
        email: document.getElementById('checkout-email').value.trim(),
        telefono: document.getElementById('checkout-telefono').value.trim(),
        direccion: document.getElementById('checkout-direccion').value.trim(),
        ciudad: document.getElementById('checkout-ciudad').value.trim(),
        departamento: document.getElementById('checkout-departamento').value.trim()
    };
}

// --- ENVIAR PEDIDO POR WHATSAPP (incluye datos del comprador) ---
btnPedirWhatsapp.addEventListener('click', () => {
    if (!formCheckout.checkValidity()) {
        formCheckout.reportValidity();
        return;
    }

    const comprador = obtenerDatosComprador();

    let textoMensaje = "Hola, quiero comprar estas gorras:\n\n";
    carrito.forEach(item => {
        textoMensaje += `- ${item.nombre} x${item.cantidad}\n`;
    });
    textoMensaje += `\nDatos de envío:\n`;
    textoMensaje += `Nombre: ${comprador.nombre}\n`;
    textoMensaje += `Email: ${comprador.email}\n`;
    textoMensaje += `Teléfono: ${comprador.telefono}\n`;
    textoMensaje += `Dirección: ${comprador.direccion}, ${comprador.ciudad}, ${comprador.departamento}\n`;
    textoMensaje += "\n¿Tienen stock?";

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
            body: JSON.stringify({ items: carrito, comprador })
        });

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.checkout_url) {
            throw new Error(datos.error || 'No se pudo iniciar el pago con Wompi.');
        }

        window.location.href = datos.checkout_url;
    } catch (error) {
        checkoutMensaje.textContent = 'No se pudo iniciar el pago. Intenta de nuevo o usa WhatsApp.';
        checkoutMensaje.classList.add('error');
        console.log('Error al crear transacción Wompi:', error);
    } finally {
        btnPagarWompi.disabled = false;
    }
});

// --- MOSTRAR RESULTADO DEL PAGO AL VOLVER DE WOMPI ---
(function mostrarResultadoPago() {
    const params     = new URLSearchParams(window.location.search);
    const estadoPago = params.get('pago');
    if (!estadoPago) return;

    const banner = document.getElementById('banner-resultado-pago');
    if (!banner) return;

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

    banner.innerHTML = `<span>${mensajeHtml}</span>`
        + `<button type="button" id="banner-cerrar-btn" aria-label="Cerrar aviso" style="background:none;border:none;color:inherit;font-size:1.3rem;line-height:1;cursor:pointer;margin-left:14px;vertical-align:middle">&times;</button>`;

    banner.dataset.tipo = estadoPago;
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

    // Solo los avisos de pendiente/fallo se cierran solos; el de éxito espera la descarga
    if (autoCerrar) {
        setTimeout(cerrarBanner, 10000);
    }

    // Limpiar los parámetros de la URL sin recargar la página
    window.history.replaceState({}, document.title, window.location.pathname);
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
    const logoH1 = document.querySelector('.logo h1');

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