// ========================================
// FUNCIONES PARA MÓVIL Y MENÚ HAMBURGUESA
// ========================================

// Toggle del menú lateral en móvil
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('active');
        
        // Cambiar ícono del botón
        const toggleBtn = document.querySelector('.menu-toggle i');
        if (toggleBtn) {
            if (sidebar.classList.contains('active')) {
                toggleBtn.className = 'fas fa-times';
            } else {
                toggleBtn.className = 'fas fa-bars';
            }
        }
    }
}

// Cerrar sidebar al hacer clic fuera (solo en móvil)
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar && toggle) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('active');
            const toggleBtn = document.querySelector('.menu-toggle i');
            if (toggleBtn) {
                toggleBtn.className = 'fas fa-bars';
            }
        }
    }
});

// Ajustar al cambiar tamaño de ventana
window.addEventListener('resize', function() {
    const sidebar = document.querySelector('.sidebar');
    if (window.innerWidth > 768 && sidebar) {
        sidebar.classList.remove('active');
        const toggleBtn = document.querySelector('.menu-toggle i');
        if (toggleBtn) {
            toggleBtn.className = 'fas fa-bars';
        }
    }
});

// ========================================
// FUNCIONES PARA TABLAS RESPONSIVES
// ========================================

// Hacer que las tablas tengan scroll horizontal en móvil
function ajustarTablas() {
    const tablas = document.querySelectorAll('table');
    tablas.forEach(tabla => {
        const contenedor = tabla.parentElement;
        if (!contenedor.classList.contains('table-container')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-container';
            tabla.parentNode.insertBefore(wrapper, tabla);
            wrapper.appendChild(tabla);
        }
    });
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    ajustarTablas();
});

// ========================================
// FUNCIONES PARA FORMULARIOS (si las necesitas)
// ========================================

// Validación de formularios en tiempo real (opcional)
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });
            
            input.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    });
}

// ========================================
// CONFIRMACIONES PARA ELIMINAR
// ========================================

// Confirmar antes de eliminar (si usas enlaces directos)
function confirmarEliminacion(event, mensaje = '¿Estás seguro de eliminar este elemento?') {
    if (!confirm(mensaje)) {
        event.preventDefault();
        return false;
    }
    return true;
}

// Asignar a todos los botones de eliminar
function initDeleteButtons() {
    const deleteBtns = document.querySelectorAll('.btn-small.danger, .btn-danger');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('¿Estás seguro de eliminar este elemento?')) {
                e.preventDefault();
            }
        });
    });
}

// ========================================
// NOTIFICACIONES TEMPORALES
// ========================================

// Ocultar mensajes después de 5 segundos
function autoHideMessages() {
    const messages = document.querySelectorAll('.mensaje');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => {
                msg.style.display = 'none';
            }, 500);
        }, 5000);
    });
}

// ========================================
// INICIALIZAR TODO CUANDO CARGA LA PÁGINA
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    // Ajustar tablas
    ajustarTablas();
    
    // Inicializar validación de formularios
    initFormValidation();
    
    // Inicializar botones de eliminar
    initDeleteButtons();
    
    // Auto-ocultar mensajes
    autoHideMessages();
    
    console.log('Admin JS cargado correctamente');
});

// ========================================
// MEJORAS PARA FORMULARIOS
// ========================================

// Activar/desactivar botón de guardar si hay campos vacíos
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const submitBtn = form.querySelector('button[type="submit"], .btn-guardar');
        const requiredFields = form.querySelectorAll('[required]');
        
        if (submitBtn && requiredFields.length > 0) {
            // Función para verificar campos
            function checkRequiredFields() {
                let allFilled = true;
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        allFilled = false;
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                submitBtn.disabled = !allFilled;
                if (submitBtn.disabled) {
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                } else {
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            }
            
            // Verificar al cargar
            checkRequiredFields();
            
            // Verificar al escribir
            requiredFields.forEach(field => {
                field.addEventListener('input', checkRequiredFields);
                field.addEventListener('blur', checkRequiredFields);
            });
        }
    });
}

// Mostrar/ocultar contraseña (opcional)
function initPasswordToggle() {
    const passwordFields = document.querySelectorAll('input[type="password"]');
    
    passwordFields.forEach(field => {
        // Crear botón de mostrar/ocultar
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);
        
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.innerHTML = '👁️';
        toggleBtn.style.position = 'absolute';
        toggleBtn.style.right = '10px';
        toggleBtn.style.background = 'none';
        toggleBtn.style.border = 'none';
        toggleBtn.style.cursor = 'pointer';
        toggleBtn.style.fontSize = '1.2rem';
        toggleBtn.style.padding = '5px';
        
        toggleBtn.addEventListener('click', function() {
            if (field.type === 'password') {
                field.type = 'text';
                toggleBtn.innerHTML = '👁️‍🗨️';
            } else {
                field.type = 'password';
                toggleBtn.innerHTML = '👁️';
            }
        });
        
        wrapper.appendChild(toggleBtn);
    });
}

// Inicializar mejoras de formularios
document.addEventListener('DOMContentLoaded', function() {
    initFormValidation();
    // initPasswordToggle(); // Descomentar si quieres botón para ver contraseña
});