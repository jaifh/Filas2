async function apiCaja(accion) {
    const panel = document.getElementById('panel-caja');
    if (!panel) return;
    const moduloId = panel.dataset.moduloId;
    if (!moduloId) return;

    const formData = new FormData();
    formData.append('accion', accion);
    formData.append('modulo_id', moduloId);

    const resp = await fetch('caja_api.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    });

    const data = await resp.json();

    if (!resp.ok || data.error) {
        alert(data.error || 'Error en la operación');
        return;
    }

    const numeroActual = document.getElementById('numero-actual');
    numeroActual.textContent = data.codigo || '--';
}

function llamarSiguiente() { apiCaja('siguiente'); }
function rellamar()       { apiCaja('rellamar'); }
function finalizar()      { apiCaja('finalizar'); }
