// Archivo: main.js
// Propósito: Lógica de JavaScript para la interactividad general de la aplicación SIGI ANCB.
// Comentario en español explicando el propósito de este archivo.

// Comentario: Este evento se dispara cuando el DOM está completamente cargado y parseado.
document.addEventListener('DOMContentLoaded', function() {
    // Comentario: Aquí se pueden añadir todas las inicializaciones y listeners de eventos.
    console.log('SIGI ANCB: DOM completamente cargado y listo.');

    // --- Funciones de Utilidad ---

    /**
     * Función para mostrar u ocultar un elemento.
     * @param {string} selector - El selector CSS del elemento.
     * @param {boolean} mostrar - True para mostrar, false para ocultar.
     * Comentario: Alterna la visibilidad de un elemento usando la clase 'oculto'.
     */
    function toggleVisibilidadElemento(selector, mostrar) {
        const elemento = document.querySelector(selector); // Comentario: Selecciona el elemento.
        if (elemento) { // Comentario: Verifica si el elemento existe.
            if (mostrar) {
                elemento.classList.remove('oculto'); // Comentario: Remueve la clase 'oculto' para mostrar.
            } else {
                elemento.classList.add('oculto'); // Comentario: Añade la clase 'oculto' para ocultar.
            }
        } else {
            console.warn(`Elemento no encontrado con selector: ${selector}`); // Comentario: Advierte si el elemento no se encuentra.
        }
    }

    // --- Interactividad del Menú de Navegación (Dropdowns) ---
    // Comentario: Manejo de menús desplegables si se prefiere control por JS en lugar de CSS :hover.
    // Comentario: El CSS actual ya maneja los dropdowns con :hover, esto es un ejemplo alternativo o para interacciones más complejas.
    /*
    const dropdowns = document.querySelectorAll('.navegacion-principal .dropdown'); // Comentario: Selecciona todos los elementos dropdown.
    dropdowns.forEach(dropdown => {
        const link = dropdown.querySelector('a'); // Comentario: Selecciona el enlace principal del dropdown.
        link.addEventListener('click', function(event) {
            // Comentario: Previene la navegación si el enlace principal es solo para abrir el menú.
            if (this.getAttribute('href') === '#') {
                event.preventDefault();
            }
            // Comentario: Alterna la visibilidad del submenú.
            const submenu = this.nextElementSibling; // Comentario: El submenú es el siguiente hermano (ul.dropdown-menu).
            if (submenu && submenu.classList.contains('dropdown-menu')) {
                submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
            }
        });

        // Comentario: Opcional: cerrar el dropdown si se hace clic fuera.
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target)) {
                const submenu = dropdown.querySelector('.dropdown-menu');
                if (submenu) {
                    submenu.style.display = 'none';
                }
            }
        });
    });
    */

    // --- Validación de Formularios del Lado del Cliente ---
    // Comentario: Ejemplo de validación para un formulario específico.
    // Comentario: Se puede generalizar o crear funciones específicas por formulario.

    const formulariosValidables = document.querySelectorAll('form.validar-js'); // Comentario: Selecciona formularios con la clase 'validar-js'.
    formulariosValidables.forEach(form => {
        form.addEventListener('submit', function(event) {
            let esValido = true; // Comentario: Bandera para el estado de validación.
            // Comentario: Selecciona todos los campos requeridos dentro de este formulario.
            const camposRequeridos = form.querySelectorAll('[required]');

            camposRequeridos.forEach(campo => {
                // Comentario: Elimina mensajes de error previos.
                eliminarMensajeError(campo);

                if (campo.type === 'checkbox' || campo.type === 'radio') {
                    // Comentario: Para checkboxes o radios, verificar si al menos uno del grupo está seleccionado (si es necesario).
                    // Comentario: Esta lógica puede ser más compleja para grupos de radios.
                    if (campo.type === 'checkbox' && !campo.checked && campo.hasAttribute('required')) {
                        esValido = false; // Comentario: No es válido.
                        mostrarMensajeError(campo, 'Debe marcar esta casilla.'); // Comentario: Muestra mensaje de error.
                    }
                    // Comentario: Para radios, la validación de 'required' usualmente se hace a nivel de grupo.
                } else if (campo.value.trim() === '') {
                    esValido = false; // Comentario: No es válido si está vacío.
                    mostrarMensajeError(campo, 'Este campo es obligatorio.'); // Comentario: Muestra mensaje de error.
                } else {
                    // Comentario: Validaciones específicas por tipo de campo.
                    if (campo.type === 'email') {
                        if (!validarEmail(campo.value.trim())) {
                            esValido = false; // Comentario: Email no válido.
                            mostrarMensajeError(campo, 'Por favor, ingrese un correo electrónico válido.'); // Comentario: Muestra mensaje de error.
                        }
                    }
                    if (campo.hasAttribute('minlength') && campo.value.trim().length < parseInt(campo.getAttribute('minlength'))) {
                        esValido = false; // Comentario: No cumple longitud mínima.
                        mostrarMensajeError(campo, `Debe tener al menos ${campo.getAttribute('minlength')} caracteres.`); // Comentario: Muestra mensaje de error.
                    }
                    if (campo.hasAttribute('pattern')) {
                        const regex = new RegExp(campo.getAttribute('pattern')); // Comentario: Crea expresión regular desde el atributo pattern.
                        if (!regex.test(campo.value.trim())) {
                            esValido = false; // Comentario: No cumple el patrón.
                            // Comentario: El mensaje de error podría venir del atributo 'title' del campo.
                            mostrarMensajeError(campo, campo.title || 'El formato no es válido.');
                        }
                    }
                    // Comentario: Añadir más validaciones según sea necesario (números, fechas, etc.).
                }
            });

            if (!esValido) {
                event.preventDefault(); // Comentario: Previene el envío del formulario si no es válido.
                console.warn('Formulario no válido. Envío prevenido.'); // Comentario: Advierte en consola.
                // Comentario: Opcionalmente, mostrar un mensaje general de error en el formulario.
                const errorGeneral = form.querySelector('.error-general-formulario');
                if (errorGeneral) {
                    errorGeneral.textContent = 'Por favor, corrija los errores marcados.';
                    errorGeneral.classList.remove('oculto');
                }
            } else {
                 // Comentario: Opcionalmente, ocultar mensaje general de error si todo está bien.
                const errorGeneral = form.querySelector('.error-general-formulario');
                if (errorGeneral) {
                    errorGeneral.classList.add('oculto');
                }
                console.log('Formulario válido. Enviando...'); // Comentario: Informa en consola.
            }
        });
    });

    /**
     * Muestra un mensaje de error debajo de un campo de formulario.
     * @param {HTMLElement} campo - El campo de formulario.
     * @param {string} mensaje - El mensaje de error a mostrar.
     * Comentario: Crea y muestra un span con el mensaje de error.
     */
    function mostrarMensajeError(campo, mensaje) {
        campo.classList.add('campo-error'); // Comentario: Añade clase para estilizar el campo con error.
        const mensajeError = document.createElement('span'); // Comentario: Crea el elemento span.
        mensajeError.className = 'mensaje-error-campo'; // Comentario: Asigna clase para estilizar el mensaje.
        mensajeError.textContent = mensaje; // Comentario: Establece el texto del mensaje.
        // Comentario: Inserta el mensaje después del campo o de su contenedor padre si es un input-group, etc.
        if (campo.parentNode.classList.contains('grupo-formulario') || campo.parentNode.tagName.toLowerCase() === 'div') {
            campo.parentNode.appendChild(mensajeError);
        } else {
            campo.parentNode.insertBefore(mensajeError, campo.nextSibling);
        }
    }

    /**
     * Elimina el mensaje de error asociado a un campo.
     * @param {HTMLElement} campo - El campo de formulario.
     * Comentario: Remueve la clase de error del campo y el span del mensaje.
     */
    function eliminarMensajeError(campo) {
        campo.classList.remove('campo-error'); // Comentario: Remueve la clase de error del campo.
        // Comentario: Busca el mensaje de error hermano o dentro del padre.
        let mensajeError = null;
        if (campo.nextSibling && campo.nextSibling.classList && campo.nextSibling.classList.contains('mensaje-error-campo')) {
            mensajeError = campo.nextSibling;
        } else if (campo.parentNode.querySelector('.mensaje-error-campo')) {
            // Comentario: Busca dentro del padre si el mensaje no es hermano directo (ej. si el campo está en un div).
            const mensajesEnPadre = campo.parentNode.querySelectorAll('.mensaje-error-campo');
            mensajesEnPadre.forEach(msg => {
                // Comentario: Asegurarse de que el mensaje realmente pertenece a este campo (más complejo, podría necesitar IDs).
                // Comentario: Por simplicidad, si está en el mismo grupo-formulario, se asume que puede ser.
                // Comentario: Una mejor aproximación sería dar IDs a los mensajes de error.
                if (msg.previousElementSibling === campo || !msg.previousElementSibling) { // Intenta ser un poco más específico
                    mensajeError = msg;
                }
            });
             // Como fallback, si hay un error general en el grupo, lo tomamos, aunque no es ideal.
            if(!mensajeError && campo.parentNode.classList.contains('grupo-formulario')){
                mensajeError = campo.parentNode.querySelector('.mensaje-error-campo');
            }
        }

        if (mensajeError) {
            mensajeError.remove(); // Comentario: Elimina el elemento del mensaje de error.
        }
    }

    /**
     * Valida una cadena de correo electrónico.
     * @param {string} email - El correo a validar.
     * @returns {boolean} - True si es válido, false si no.
     * Comentario: Utiliza una expresión regular simple para validar el formato del email.
     */
    function validarEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Comentario: Expresión regular para email.
        return re.test(String(email).toLowerCase()); // Comentario: Prueba la expresión regular.
    }

    // --- Manejo de Alertas / Mensajes Flash (Botón de Cierre) ---
    // Comentario: Si se usan alertas con un botón de cierre (ej. de Bootstrap o personalizadas).
    const alertas = document.querySelectorAll('.alert .btn-close'); // Comentario: Selecciona todos los botones de cierre en alertas.
    alertas.forEach(botonCierre => {
        botonCierre.addEventListener('click', function() {
            this.closest('.alert').remove(); // Comentario: Remueve el elemento 'alert' padre más cercano.
        });
    });

    // --- Confirmación antes de acciones destructivas ---
    // Comentario: Añade un diálogo de confirmación a enlaces o botones con la clase 'confirmar-accion'.
    const elementosConfirmables = document.querySelectorAll('.confirmar-accion'); // Comentario: Selecciona elementos que requieren confirmación.
    elementosConfirmables.forEach(elemento => {
        elemento.addEventListener('click', function(event) {
            const mensaje = this.dataset.mensajeConfirmacion || '¿Está seguro de que desea realizar esta acción?'; // Comentario: Mensaje de confirmación.
            if (!confirm(mensaje)) { // Comentario: Muestra el diálogo de confirmación.
                event.preventDefault(); // Comentario: Previene la acción por defecto si el usuario cancela.
                console.log('Acción cancelada por el usuario.'); // Comentario: Informa en consola.
            }
        });
    });

    // --- Interacciones AJAX (Ejemplo básico) ---
    // Comentario: Si se necesita cargar contenido dinámicamente sin recargar la página.
    /*
    async function cargarContenidoAjax(url, selectorDestino) {
        try {
            const response = await fetch(url); // Comentario: Realiza la petición fetch.
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`); // Comentario: Lanza error si la respuesta no es OK.
            }
            const dataHtml = await response.text(); // Comentario: Obtiene la respuesta como texto/HTML.
            const destino = document.querySelector(selectorDestino); // Comentario: Selecciona el elemento destino.
            if (destino) {
                destino.innerHTML = dataHtml; // Comentario: Inserta el HTML en el destino.
            } else {
                console.error(`Destino AJAX no encontrado: ${selectorDestino}`); // Comentario: Error si el destino no existe.
            }
        } catch (error) {
            console.error(`Error al cargar contenido AJAX desde ${url}:`, error); // Comentario: Muestra error en consola.
            const destino = document.querySelector(selectorDestino);
            if (destino) {
                destino.innerHTML = `<p class="error">Error al cargar el contenido. Intente más tarde.</p>`; // Comentario: Muestra mensaje de error en el destino.
            }
        }
    }
    */
    // Ejemplo de uso de AJAX:
    // const botonCargar = document.getElementById('miBotonAjax');
    // if (botonCargar) {
    //     botonCargar.addEventListener('click', function() {
    //         cargarContenidoAjax('ruta/a/tu/endpoint_que_devuelve_html.php', '#miContenedorAjax');
    //     });
    // }


    // --- Inicialización de componentes específicos de vistas (si es necesario) ---
    // Comentario: Por ejemplo, si una vista tiene un datepicker o un editor de texto enriquecido.
    // if (document.querySelector('#miCalendario')) {
    //     // new Calendar('#miCalendario', { /* opciones */ });
    // }


    // Comentario: Fin del script main.js
    console.log('SIGI ANCB: Listeners y funciones JS inicializados.');
});

// Comentario: Estilos CSS para errores de validación en JS (se pueden mover a estilos.css)
// Comentario: Esto es solo un ejemplo, es mejor tenerlos en el CSS principal.
/*
const style = document.createElement('style');
style.textContent = `
    .campo-error {
        border-color: #dc3545 !important; // Comentario: Borde rojo para campos con error.
        background-color: #f8d7da !important; // Comentario: Fondo rosa claro.
    }
    .mensaje-error-campo {
        color: #dc3545; // Comentario: Color de texto rojo para mensajes de error.
        font-size: 0.875em; // Comentario: Tamaño de fuente más pequeño.
        display: block; // Comentario: Para que ocupe su propia línea.
        margin-top: 0.25rem; // Comentario: Espacio superior.
    }
    .error-general-formulario {
        color: #dc3545;
        background-color: #f8d7da;
        border: 1px solid #f5c2c7;
        padding: 0.75rem;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
    }
    .oculto {
        display: none !important;
    }
`;
document.head.appendChild(style); // Comentario: Añade los estilos al head.
*/
// Comentario: Se recomienda que los estilos anteriores estén en `css/estilos.css` para mantener la separación de responsabilidades.
// Comentario: Las clases .campo-error, .mensaje-error-campo, .error-general-formulario y .oculto deben definirse en estilos.css.
// Comentario: La clase .oculto ya está definida en estilos.css.
// Comentario: Añadir .campo-error, .mensaje-error-campo, .error-general-formulario a estilos.css
// Comentario: .campo-error { border: 1px solid var(--color-error) !important; background-color: #fff0f1; }
// Comentario: .mensaje-error-campo { color: var(--color-error); font-size: 0.8em; display: block; margin-top: 4px; }
// Comentario: .error-general-formulario { background-color: #f8d7da; border: 1px solid var(--color-error); color: var(--color-error); padding: 10px; margin-bottom:15px; border-radius: var(--borde-radio); }
