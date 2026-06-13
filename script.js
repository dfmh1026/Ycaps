// --- VARIABLES GLOBALES Y SELECTORES ---
let carrito = [];

const panel = document.getElementById('carrito-panel');
const abrirBtn = document.getElementById('abrir-carrito');
const cerrarBtn = document.getElementById('cerrar-carrito');
const contenedorItems = document.getElementById('items-carrito');
const contador = document.getElementById('contador-carrito');
const totalTxt = document.getElementById('precio-total');

// --- EVENT LISTENERS PARA EL PANEL ---
abrirBtn.addEventListener('click', () => panel.classList.add('activo'));
cerrarBtn.addEventListener('click', () => panel.classList.remove('activo'));

document.addEventListener('click', (event) => {
    if (!panel.classList.contains('activo')) return;

    const clickDentroPanel = panel.contains(event.target);
    const clickEnBotonAbrir = abrirBtn.contains(event.target);

    if (!clickDentroPanel && !clickEnBotonAbrir) {
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
        if (imagenes.length <= 1) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'gallery-wrapper';
        const firstImg = imagenes[0];
        tarjeta.insertBefore(wrapper, firstImg);
        imagenes.forEach(img => wrapper.appendChild(img));

        let currentIndex = 0;
        imagenes.forEach((img, index) => {
            img.classList.toggle('activo', index === 0);
            img.setAttribute('data-gallery-index', index);
        });

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
        if (event.target.closest('.btn-agregar') || event.target.closest('.nav-btn')) return;
        const imagen = getVisibleImage(tarjeta);
        if (imagen) {
            const imagenes = Array.from(tarjeta.querySelectorAll('.imagen-producto'));
            const index = imagenes.indexOf(imagen);
            abrirModalImagen(imagen.src, imagen.alt, imagenes, index);
        }
    });
});

// --- FUNCIONES DEL CARRITO ---

// Añadir un modelo de gorra al carrito
function agregarAlCarrito(nombre, precio) {
    const existe = carrito.find(item => item.nombre === nombre);
    
    if (existe) {
        existe.cantidad++;
    } else {
        carrito.push({ nombre, precio, cantidad: 1 });
    }
    
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
        div.innerHTML = `
            <div class="item-detalles">
                <h4>${item.nombre} (x${item.cantidad})</h4>
                <p>$${(item.precio * item.cantidad).toLocaleString('es-CO')}</p>
            </div>
            <button class="btn-eliminar" onclick="eliminarDelCarrito('${item.nombre}')">Eliminar</button>
        `;
        contenedorItems.appendChild(div);
    });

    // Actualizar el botón flotante y el total de la factura
    contador.innerText = totalGorras;
    totalTxt.innerText = `$${total.toLocaleString('es-CO')}`;
}

// --- PROCESAR PEDIDO Y ENVIAR A WHATSAPP ---
function procesarPedido() {
    if (carrito.length === 0) {
        alert("Tu carrito está vacío. ¡Añade algunas Ycaps primero!");
        return;
    }

    // Opción 1: Mensaje breve prellenado con los items del carrito
    let textoMensaje = "Hola, quiero comprar estas gorras:\n\n";
    carrito.forEach(item => {
        textoMensaje += `- ${item.nombre} x${item.cantidad}\n`;
    });
    textoMensaje += "\n¿Tienen stock?";

    const urlTexto = encodeURIComponent(textoMensaje);
    // Usar el enlace directo proporcionado y añadir el texto prellenado
    const waLink = `https://wa.me/message/FJMYKB6OTYB3M1?text=${urlTexto}`;
    window.open(waLink, '_blank');
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