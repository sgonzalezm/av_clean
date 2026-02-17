// Manejo avanzado del formulario de contacto
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        // Máscara para teléfono
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 10) value = value.substring(0, 10);
                
                if (value.length > 6) {
                    value = `(${value.substring(0,3)}) ${value.substring(3,6)}-${value.substring(6)}`;
                } else if (value.length > 3) {
                    value = `(${value.substring(0,3)}) ${value.substring(3)}`;
                } else if (value.length > 0) {
                    value = `(${value}`;
                }
                
                e.target.value = value;
            });
        }
        
        // Validación en tiempo real
        const inputs = contactForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                clearError(this);
            });
        });
        
        // Función de validación de campo
        function validateField(field) {
            const value = field.value.trim();
            const fieldId = field.id;
            
            clearError(field);
            
            // Validaciones específicas por campo
            if (field.required && !value) {
                showError(field, 'Este campo es obligatorio');
                return false;
            }
            
            switch(fieldId) {
                case 'email':
                    if (value && !isValidEmail(value)) {
                        showError(field, 'Ingresa un correo electrónico válido');
                        return false;
                    }
                    break;
                    
                case 'phone':
                    if (value && !isValidPhone(value)) {
                        showError(field, 'Ingresa un número de teléfono válido (10 dígitos)');
                        return false;
                    }
                    break;
            }
            
            return true;
        }
        
        // Mostrar error
        function showError(field, message) {
            // Limpiar error anterior
            clearError(field);
            
            // Crear elemento de error
            const error = document.createElement('div');
            error.className = 'error-message';
            error.textContent = message;
            error.style.color = '#dc3545';
            error.style.fontSize = '14px';
            error.style.marginTop = '5px';
            
            // Insertar después del campo
            field.parentNode.appendChild(error);
            
            // Estilo al campo
            field.style.borderColor = '#dc3545';
        }
        
        // Limpiar error
        function clearError(field) {
            // Remover mensaje de error
            const error = field.parentNode.querySelector('.error-message');
            if (error) {
                error.remove();
            }
            
            // Restaurar borde
            field.style.borderColor = '#ddd';
        }
        
        // Validar email
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
        
        // Validar teléfono (mínimo 10 dígitos)
        function isValidPhone(phone) {
            const digits = phone.replace(/\D/g, '');
            return digits.length === 10;
        }
        
        // Manejo del envío del formulario
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validar todos los campos
            let isValid = true;
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                alert('Por favor corrige los errores en el formulario');
                return;
            }
            
            // Mostrar estado de envío
            const submitBtn = contactForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            submitBtn.disabled = true;
            
            // Simular envío (reemplazar con tu API real)
            setTimeout(() => {
                // Aquí iría tu petición fetch/axios a Django
                console.log('Datos del formulario:', {
                    name: document.getElementById('name').value,
                    email: document.getElementById('email').value,
                    phone: document.getElementById('phone').value,
                    interest: document.getElementById('interest').value,
                    message: document.getElementById('message').value
                });
                
                // Mostrar confirmación
                alert('¡Mensaje enviado con éxito! Te contactaremos en un máximo de 24 horas.');
                
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Limpiar formulario
                contactForm.reset();
                
                // Enfocar primer campo
                document.getElementById('name').focus();
                
            }, 1500);
        });
    }
});