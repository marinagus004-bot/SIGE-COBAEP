function abrirModalJustificar(idAsistencia, nombreAlumno) {
            document.getElementById('idAsistenciaModal').value = idAsistencia;
            document.getElementById('nombreAlumnoJustificar').innerText = nombreAlumno;
            document.getElementById('modalJustificar').style.display = 'flex';
        }
        
        // Agregar cierre del modal al hacer clic afuera
        window.addEventListener('click', function(event) {
            let modalJustificar = document.getElementById('modalJustificar');
            if (event.target === modalJustificar) {
                modalJustificar.style.display = 'none';
            }
        });