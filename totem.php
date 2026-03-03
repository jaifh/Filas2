<?php
// totem.php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Obtener Ticket - Municipalidad</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #020617;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { font-size: 3rem; margin: 0; color: #22c55e; }
        .header p { font-size: 1.5rem; color: #cbd5e1; margin: 5px 0; }
        
        .botones-container {
            display: flex;
            gap: 30px;
            width: 80%;
            max-width: 1000px;
        }
        .btn-totem {
            flex: 1;
            padding: 60px 20px;
            border-radius: 20px;
            border: none;
            font-size: 2.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.1s, box-shadow 0.1s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .btn-totem:active { transform: scale(0.95); }
        
        .btn-normal {
            background: linear-gradient(145deg, #2563eb, #1d4ed8);
            color: white;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.4);
        }
        .btn-preferencial {
            background: linear-gradient(145deg, #eab308, #ca8a04);
            color: #1e293b;
            box-shadow: 0 10px 30px rgba(234, 179, 8, 0.4);
        }
        
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 1000;
        }
        .ticket-print {
            background: white; color: black; padding: 40px; border-radius: 10px;
            text-align: center; width: 400px;
        }
        .ticket-print h2 { margin: 0; font-size: 1.5rem; }
        .ticket-num { font-size: 6rem; font-weight: 900; margin: 20px 0; border: 4px solid black; }
    </style>
</head>
<body>

    <div class="header">
        <h1>Permisos de Circulación</h1>
        <p>Toque la pantalla para obtener su número</p>
    </div>

    <div class="botones-container">
        <button class="btn-totem btn-normal" onclick="solicitarTicket('NORMAL')">
            <span style="font-size: 4rem;">🧑‍💼</span>
            ATENCIÓN GENERAL
        </button>

        <button class="btn-totem btn-preferencial" onclick="solicitarTicket('PREFERENCIAL')">
            <span style="font-size: 4rem;">🦽🤰</span>
            ATENCIÓN PREFERENCIAL
        </button>
    </div>

    <div class="modal" id="modal-impresion">
        <div class="ticket-print">
            <h2>MUNICIPALIDAD</h2>
            <p id="lbl-tipo">Permisos de Circulación</p>
            <div class="ticket-num" id="lbl-numero">--</div>
            <p>Por favor, espere a ser llamado en pantalla.</p>
            <p style="font-size: 0.8rem; color: #666;">Retire su comprobante</p>
        </div>
    </div>

    <script>
        async function solicitarTicket(tipo) {
            try {
                const res = await fetch('totem_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ tipo: tipo })
                });
                
                const data = await res.json();
                if(data.success) {
                    document.getElementById('lbl-numero').innerText = data.codigo;
                    document.getElementById('lbl-tipo').innerText = tipo === 'PREFERENCIAL' ? 'Atención Preferencial' : 'Atención General';
                    
                    // Mostrar modal
                    const modal = document.getElementById('modal-impresion');
                    modal.style.display = 'flex';
                    
                    // Ocultar modal después de 4 segundos (y simular impresión)
                    setTimeout(() => {
                        modal.style.display = 'none';
                    }, 4000);
                } else {
                    alert('Error al generar ticket: ' + data.message);
                }
            } catch(e) {
                alert('Error de conexión con el sistema.');
            }
        }
    </script>
</body>
</html>