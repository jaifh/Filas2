<?php
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pantalla de llamados - Municipalidad</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --bg-dark: #020617;
            --panel-bg: #0f172a;
            --primary-green: #22c55e;
            --secondary-green: #16a34a;
            --accent-color: #facc15;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-dark);
            color: #ffffff;
            overflow: hidden;
            height: 100vh;
        }

        /* DISEÑO DE GRILLA PRINCIPAL */
        .layout {
            display: grid;
            grid-template-columns: 28% 42% 30%;
            grid-template-rows: 90px 1fr;
            grid-template-areas:
                "header header header"
                "col-left col-center col-right";
            height: 100vh;
            gap: 12px;
            padding: 0 10px 10px 10px;
        }

        header {
            grid-area: header;
            padding: 0 2rem;
            background: var(--secondary-green);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        .header-left { display: flex; align-items: center; gap: 1.5rem; }
        .header-logo { height: 60px; width: auto; object-fit: contain; }
        header h1 { margin: 0; font-size: 1.8rem; font-weight: 800; text-transform: uppercase; }

        .hora-actual {
            font-size: 2.2rem;
            font-family: monospace;
            font-weight: bold;
            background: rgba(0,0,0,0.2);
            padding: 5px 20px;
            border-radius: 10px;
        }

        /* COLUMNA IZQUIERDA: HISTORIAL CORRELATIVO */
        .col-left {
            grid-area: col-left;
            padding: 5px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 0 0 20px 20px;
            overflow: hidden;
        }

        .historial-titulo {
            text-align: center;
            padding: 8px;
            background: var(--secondary-green);
            border-radius: 8px;
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .ticket-card {
            display: grid;
            grid-template-columns: 60% 40%;
            background: var(--panel-bg);
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #1e293b;
            flex: 1; 
            min-height: 0; 
        }

        .ticket-col-num {
            background: #fff;
            color: var(--bg-dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .ticket-label { font-size: 0.55rem; font-weight: bold; color: var(--secondary-green); text-transform: uppercase; }
        .ticket-numero { font-size: 1.7rem; font-weight: 900; line-height: 1; }

        .ticket-col-mod {
            background: var(--secondary-green);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
        }
        .ticket-modulo-val { font-size: 1.6rem; font-weight: 900; line-height: 1; }

        /* PANEL CENTRAL: LLAMADO ACTUAL */
        .col-center {
            grid-area: col-center;
            padding: 10px;
            display: flex;
            flex-direction: column;
        }

        .panel-turno {
            flex: 1;
            background: linear-gradient(145deg, #16a34a, #15803d);
            border-radius: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 50px rgba(0,0,0,0.2), 0 20px 50px rgba(0,0,0,0.5);
            border: 5px solid rgba(255,255,255,0.2);
            transition: all 0.3s ease;
        }

        .panel-turno-label { font-size: 3.5rem; font-weight: 900; color: var(--accent-color); margin-bottom: 10px; }

        .panel-turno-numero {
            font-size: 8rem;
            font-weight: 900;
            background: #000;
            color: var(--primary-green);
            padding: 15px 60px;
            border-radius: 40px;
            border: 8px solid #fff;
        }

        .panel-turno-modulo-num { font-size: 5.5rem; font-weight: 900; color: var(--accent-color); }

        /* COLUMNA DERECHA: PUBLICIDAD Y QR */
        .col-right {
            grid-area: col-right;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .banner-container {
            flex: 1.2;
            background: #000;
            border-radius: 20px;
            overflow: hidden;
            border: 3px solid #334155;
        }
        .banner-container img { width: 100%; height: 100%; object-fit: cover; }

        .qr-container {
            flex: 1.5; /* Aumentado de 1 a 1.5 para dar más espacio vertical */
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #000;
            border: 3px solid var(--secondary-green);
        }

        .qr-container img {
            width: 85%; /* La imagen ocupará el 85% del ancho del contenedor */
            height: auto;
            max-height: 250px; /* Altura máxima permitida */
            object-fit: contain;
            margin: 10px 0;
        }

        .qr-container h3 {
            margin: 0;
            color: var(--secondary-green);
            font-size: 1.5rem; /* Título más grande */
            font-weight: 900;
        }

        .qr-container p {
            margin: 0;
            font-size: 1.1rem; /* Texto inferior más grande */
            font-weight: bold;
            color: #334155;
        }
        /* ANIMACIÓN DE LLAMADO */
        .llamado-activo { animation: pulse-active 1s infinite alternate; }
        @keyframes pulse-active {
            from { transform: scale(1); box-shadow: 0 0 40px rgba(34, 197, 94, 0.5); }
            to { transform: scale(1.05); box-shadow: 0 0 80px rgba(250, 204, 21, 0.8); }
        }

        #btn-audio {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            z-index: 9999; padding: 12px 25px; background: var(--accent-color);
            border: none; border-radius: 50px; font-weight: bold; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>

<button id="btn-audio">HAGA CLIC PARA ACTIVAR SONIDO Y VOZ</button>
<audio id="snd-ding" src="assets/sounds/ding.mp3" preload="auto"></audio>

<div class="layout">
    <header>
        <div class="header-left">
            <img src="assets/img/logo.png" alt="Logo" class="header-logo">
            <div>
                <h1>Municipalidad</h1>
                <span style="font-size: 1rem; font-weight: bold;">Permisos de Circulación</span>
            </div>
        </div>
        <div class="hora-actual" id="hora-actual">00:00:00</div>
    </header>

    <div class="col-left">
        <div class="historial-titulo">ÚLTIMOS LLAMADOS</div>
        <div id="lista-tickets" style="display: flex; flex-direction: column; gap: 5px; flex: 1;">
            </div>
    </div>

    <div class="col-center">
        <div class="panel-turno" id="main-panel">
            <div class="panel-turno-label">SU TURNO</div>
            <div id="turno-numero" class="panel-turno-numero">--</div>
            <div style="font-size: 2rem; font-weight: 700; margin-top: 15px;">MÓDULO</div>
            <div id="turno-modulo" class="panel-turno-modulo-num">--</div>
        </div>
    </div>

    <div class="col-right">
        <div class="banner-container">
            <img src="assets/img/banner_central.jpg" alt="Publicidad">
        </div>
        <div class="qr-container">
            <h3>PAGUE AQUÍ</h3>
            <img src="assets/img/qr_pago.png" alt="QR">
            <p>Escanee con su celular</p>
        </div>
    </div></div>

<script>
let ultimaMarcaTiempo = null; // Seguimiento por hora de llamado para detectar re-llamadas
let audioActivado = false;
let vocesCargadas = [];

// Configuración de Voz
function cargarVoces() {
    vocesCargadas = window.speechSynthesis.getVoices();
}
if (speechSynthesis.onvoiceschanged !== undefined) {
    speechSynthesis.onvoiceschanged = cargarVoces;
}

document.getElementById('btn-audio').addEventListener('click', function() {
    audioActivado = true;
    this.style.display = 'none';
    cargarVoces();
});

function hablar(texto) {
    if (!window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    const mensaje = new SpeechSynthesisUtterance(texto);
    mensaje.lang = 'es-ES';
    mensaje.rate = 0.9;
    const voz = vocesCargadas.find(v => (v.name.includes('Google') || v.lang.includes('es')) && v.lang.includes('es'));
    if (voz) mensaje.voice = voz;
    window.speechSynthesis.speak(mensaje);
}

function anunciarLlamado(numero, modulo) {
    const audio = document.getElementById('snd-ding');
    audio.play().then(() => {
        setTimeout(() => {
            const numLimpio = numero.replace(/-/g, ' ');
            hablar(`Turno. ${numLimpio}. Diríjase a módulo. ${modulo}`);
        }, 1300);
    }).catch(e => console.warn("Interacción requerida para audio"));
}

async function cargarDisplay() {
    try {
        const resp = await fetch('display_api.php', { cache: 'no-store' });
        const data = await resp.json();

        document.getElementById('hora-actual').textContent = new Date().toLocaleTimeString('es-CL');

        if (!data.tickets || data.tickets.length === 0) return;

        // 1. LÓGICA DE TURNO ACTUAL (Primero de la lista)
        const actual = data.tickets[0];
        const codigoActual = actual.prefijo + '-' + String(actual.numero).padStart(3, '0');
        
        // Detectar si es un nuevo llamado o un re-llamado mediante la hora
        if (ultimaMarcaTiempo !== actual.hora_llamado) {
            
            if (ultimaMarcaTiempo !== null && audioActivado) {
                anunciarLlamado(codigoActual, actual.modulo);
                
                // Efecto visual
                const mp = document.getElementById('main-panel');
                mp.classList.remove('llamado-activo');
                void mp.offsetWidth; // Trigger reflow para reiniciar animación
                mp.classList.add('llamado-activo');
                setTimeout(() => mp.classList.remove('llamado-activo'), 8000);
            }

            ultimaMarcaTiempo = actual.hora_llamado;
            document.getElementById('turno-numero').textContent = codigoActual;
            document.getElementById('turno-modulo').textContent = actual.modulo;
        }

        // 2. LÓGICA DE HISTORIAL (Últimos 8 correlativos)
        const contTickets = document.getElementById('lista-tickets');
        contTickets.innerHTML = ''; // Limpiar siempre para mantener el orden correcto

        // Filtramos para que el historial no repita el que está en grande
        let historial = data.tickets.filter(t => 
            !(t.prefijo === actual.prefijo && t.numero === actual.numero)
        );

        // Ordenamos por número de mayor a menor para que el más nuevo esté arriba
        historial.sort((a, b) => b.numero - a.numero);

        // Renderizar máximo 8 en el historial
        historial.slice(0, 8).forEach(t => {
            const codigo = t.prefijo + '-' + String(t.numero).padStart(3, '0');
            contTickets.insertAdjacentHTML('beforeend', `
                <div class="ticket-card">
                    <div class="ticket-col-num">
                        <span class="ticket-label">Turno</span>
                        <span class="ticket-numero">${codigo}</span>
                    </div>
                    <div class="ticket-col-mod">
                        <span style="font-size:0.5rem; font-weight:bold;">MOD</span>
                        <span class="ticket-modulo-val">${t.modulo}</span>
                    </div>
                </div>
            `);
        });

    } catch (e) {
        console.error("Error cargando display:", e);
    }
}

// Ejecución continua
setInterval(cargarDisplay, 3000);
cargarDisplay();
</script>

</body>
</html>