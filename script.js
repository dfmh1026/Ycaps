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
tarjetasProductos.forEach(tarjeta => {
    tarjeta.addEventListener('click', (event) => {
        if (event.target.closest('.btn-agregar')) return;
        const imagen = tarjeta.querySelector('.imagen-producto');
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

    // Estructurar el texto del mensaje comercial
    let textoMensaje = "¡Hola Ycaps! Me interesa comprar las siguientes gorras:\n\n";
    
    carrito.forEach(item => {
        textoMensaje += `- ${item.nombre} (Cantidad: ${item.cantidad})\n`;
    });
    
    textoMensaje += `\n*Total estimado:* ${totalTxt.innerText}\n`;
    textoMensaje += "¿Cómo podemos coordinar el método de pago y el envío?";

    // Codificar caracteres especiales para la URL
    const urlTexto = encodeURIComponent(textoMensaje);
    
    // REEMPLAZAR AQUÍ: Pon tu número de WhatsApp de Ycaps con el código de país (Ej: Colombia es 57)
    const numeroTelefono = "573000000000"; 
    
    // Redirección directa a la API de WhatsApp
    window.open(`https://wa.me/${numeroTelefono}?text=${urlTexto}`, '_blank');
}