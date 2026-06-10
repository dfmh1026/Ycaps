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

function abrirModalImagen(src, alt) {
    modalImg.src = src;
    modalImg.alt = alt;
    modalCaption.textContent = alt || 'Gorra Ycaps';
    modalImagen.classList.add('activo');
}

function cerrarModalImagen() {
    modalImagen.classList.remove('activo');
    modalImg.src = '';
}

cerrarModal.addEventListener('click', cerrarModalImagen);
modalImagen.addEventListener('click', (event) => {
    if (event.target === modalImagen) {
        cerrarModalImagen();
    }
});

const tarjetasProductos = document.querySelectorAll('.tarjeta-producto');

function getVisibleImage(tarjeta) {
    const principales = tarjeta.querySelectorAll('.imagen-principal');
    for (const img of principales) {
        const style = window.getComputedStyle(img);
        if (style.display !== 'none' && style.visibility !== 'hidden' && img.naturalWidth !== 0) {
            return img;
        }
    }
    // fallback to any imagen-producto
    return tarjeta.querySelector('.imagen-producto');
}

// Inicializar galerías: si hay varias imágenes dentro de la tarjeta, crear miniaturas
function initGalerias() {
    tarjetasProductos.forEach(tarjeta => {
        const imgs = Array.from(tarjeta.querySelectorAll('.imagen-producto'));
        if (imgs.length === 0) return;

        // marcar la primera como imagen principal
        imgs.forEach((img, i) => {
            img.classList.add('imagen-principal');
            if (i !== 0) img.style.display = 'none';
        });

        if (imgs.length > 1) {
            // crear navegación compacta (prev / next)
            tarjeta.dataset.currentIndex = 0;

            const nav = document.createElement('div');
            nav.className = 'gallery-nav';

            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = 'nav-btn prev-btn';
            prev.setAttribute('aria-label', 'Anterior');
            prev.textContent = '<';

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'nav-btn next-btn';
            next.setAttribute('aria-label', 'Siguiente');
            next.textContent = '>';

            nav.appendChild(prev);
            nav.appendChild(next);
            tarjeta.appendChild(nav);

            function showIndex(i) {
                const idx = (i + imgs.length) % imgs.length;
                imgs.forEach((im, j) => im.style.display = (j === idx) ? 'block' : 'none');
                tarjeta.dataset.currentIndex = idx;
            }

            prev.addEventListener('click', (e) => {
                e.stopPropagation();
                const cur = parseInt(tarjeta.dataset.currentIndex, 10) || 0;
                showIndex(cur - 1);
            });

            next.addEventListener('click', (e) => {
                e.stopPropagation();
                const cur = parseInt(tarjeta.dataset.currentIndex, 10) || 0;
                showIndex(cur + 1);
            });

            // iniciar mostrando la primera
            showIndex(0);
        }
    });
}

tarjetasProductos.forEach(tarjeta => {
    tarjeta.addEventListener('click', (event) => {
        if (event.target.closest('.btn-agregar')) return;
        const imagen = getVisibleImage(tarjeta);
        if (imagen) {
            abrirModalImagen(imagen.src, imagen.alt);
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