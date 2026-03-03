<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();

// =====================================================================
// 1. MODO SELECTOR DE PANTALLAS (SI NO HAY DEPARTAMENTO EN LA URL)
// =====================================================================
if (!isset($_GET['dept'])) {
    $departamentos = $pdo->query("SELECT * FROM departamentos ORDER BY nombre ASC")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Selector de Pantallas - Municipalidad de Hualqui</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { 
                margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; 
                background: #020617; color: white; 
                display: flex; flex-direction: column; align-items: center; justify-content: center; 
                min-height: 100vh; padding: 20px; box-sizing: border-box; 
            }
            /* LOGO MUNICIPAL EN EL LOBBY */
            .logo-lobby { 
                height: 120px; 
                width: auto; 
                margin-bottom: 10px; 
                object-fit: contain;
                filter: drop-shadow(0 0 10px rgba(255,255,255,0.2));
            }
            h1 { color: #22c55e; font-size: 2.5rem; margin-top: 0; text-transform: uppercase; letter-spacing: 1px; text-align: center; }
            p { color: #94a3b8; font-size: 1.2rem; margin-bottom: 40px; text-align: center; }
            
            .grid-pantallas { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; max-width: 1200px; width: 100%; }
            
            .card-pantalla { 
                background: #0f172a; border: 2px solid #1e293b; padding: 40px 30px; 
                border-radius: 20px; text-align: center; text-decoration: none; color: white; 
                flex: 1 1 300px; max-width: 400px; transition: all 0.3s ease; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; flex-direction: column; 
                align-items: center; justify-content: center; 
            }
            .card-pantalla:hover { transform: translateY(-10px) scale(1.02); border-color: #22c55e; box-shadow: 0 15px 40px rgba(34, 197, 94, 0.2); background: #13203b; }
            
            .icon-tv { font-size: 4rem; margin-bottom: 15px; display: block; }
            .card-pantalla h2 { margin: 0; font-size: 1.8rem; color: #f8fafc; }
            .card-pantalla .prefijos { margin-top: 15px; background: rgba(0,0,0,0.3); padding: 8px 15px; border-radius: 10px; font-size: 0.9rem; color: #cbd5e1; }
        </style>
    </head>
    <body>
        <img src="assets/img/logo.png" alt="Municipalidad de Hualqui" class="logo-lobby">
        <h1>Gestor de Pantallas</h1>
        <p>Seleccione el departamento que desea visualizar en este monitor</p>
        
        <div class="grid-pantallas">
            <?php foreach ($departamentos as $d): ?>
                <a href="?dept=<?= $d['id'] ?>" class="card-pantalla">
                    <span class="icon-tv">🖥️</span>
                    <h2><?= htmlspecialchars($d['nombre']) ?></h2>
                    <div class="prefijos">
                        Gen: <strong><?= $d['prefijo_cola'] ?></strong> | Pref: <strong><?= $d['prefijo_preferencial'] ?></strong>
                    </div>
                </a>
            <?php endforeach; ?>
            
            <?php if (empty($departamentos)): ?>
                <h3 style="color: #ef4444;">No hay departamentos creados en el sistema.</h3>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =====================================================================
// 2. MODO PANTALLA DE LLAMADOS (SI HAY DEPARTAMENTO EN LA URL)
// =====================================================================
$dept_id = (int)$_GET['dept'];
$stmt = $pdo->prepare("SELECT * FROM departamentos WHERE id = ?");
$stmt->execute([$dept_id]);
$dept = $stmt->fetch();

if (!$dept) {
    header("Location: display.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pantalla de llamados - <?= htmlspecialchars($dept['nombre']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root { --bg-dark: #020617; --panel-bg: #0f172a; --primary-green: #22c55e; --secondary-green: #16a34a; --accent-color: #facc15; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: sans-serif; background: var(--bg-dark); color: #ffffff; overflow: hidden; height: 100vh; }
        
        /* DISEÑO RESPONSIVO: PANTALLA DIVIDIDA */
        .layout { 
            display: grid; 
            grid-template-columns: 28% 42% 30%; 
            grid-template-rows: 90px 1fr; 
            grid-template-areas: "header header header" "col-left col-center col-right"; 
            height: 100vh; 
            gap: 12px; 
            padding: 0 10px 10px 10px; 
        }

        /* AJUSTES HEADER PARA ACOMODAR EL LOGO Y TEXTO */
        header { 
            grid-area: header; 
            padding: 0 1.5rem; 
            background: var(--secondary-green); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.4); 
            border-radius: 0 0 15px 15px; /* Bordes redondeados sutiles */
        }
        
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .header-logo { height: 70px; width: auto; object-fit: contain; background: white; padding: 5px; border-radius: 8px;} /* Logo con fondo blanco para que resalte */
        
        .header-titulos { display: flex; flex-direction: column; justify-content: center; }
        .header-titulos h1 { margin: 0; font-size: 1.6rem; font-weight: 900; text-transform: uppercase; line-height: 1; }
        .header-titulos span { font-size: 1rem; font-weight: bold; color: #d1fae5; }
        
        .btn-volver { background: rgba(0,0,0,0.3); color: white; text-decoration: none; padding: 8px 15px; border-radius: 8px; font-size: 1.2rem; font-weight: bold; border: 1px solid rgba(255,255,255,0.2); transition: 0.2s; display: flex; align-items: center; justify-content: center; height: 45px;}
        .btn-volver:hover { background: rgba(0,0,0,0.6); }

        .hora-actual { font-size: 2.2rem; font-family: monospace; font-weight: bold; background: rgba(0,0,0,0.2); padding: 5px 20px; border-radius: 10px; }
        
        /* COLUMNAS */
        .col-right { grid-area: col-right; padding: 10px 0 10px 10px; display: flex; flex-direction: column; gap: 15px; min-height: 0; }
        .banner-container { flex: 1; background: #000; border-radius: 20px; overflow: hidden; border: 3px solid #334155; min-height: 0; display: flex; align-items: center; justify-content: center; position: relative; }
        .banner-container img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; }
        
        .col-left { grid-area: col-left; padding: 5px; display: flex; flex-direction: column; gap: 5px; background: rgba(15, 23, 42, 0.5); border-radius: 0 0 20px 20px; min-height: 0; }
        .historial-titulo { text-align: center; padding: 8px; background: var(--secondary-green); border-radius: 8px; font-weight: bold; font-size: 0.9rem; margin-bottom: 2px; }
        .ticket-card { display: grid; grid-template-columns: 60% 40%; background: var(--panel-bg); border-radius: 10px; border: 2px solid #1e293b; flex: 1; min-height: 0; }
        .ticket-col-num { background: #fff; color: var(--bg-dark); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .ticket-label { font-size: 0.55rem; font-weight: bold; color: var(--secondary-green); text-transform: uppercase; }
        .ticket-numero { font-size: 1.7rem; font-weight: 900; line-height: 1; }
        .ticket-col-mod { background: var(--secondary-green); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; }
        .ticket-modulo-val { font-size: 1.6rem; font-weight: 900; line-height: 1; }
        
        .col-center { grid-area: col-center; padding: 10px; display: flex; flex-direction: column; min-height: 0; }
        .panel-turno { flex: 1; background: linear-gradient(145deg, #16a34a, #15803d); border-radius: 30px; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: inset 0 0 50px rgba(0,0,0,0.2), 0 20px 50px rgba(0,0,0,0.5); border: 5px solid rgba(255,255,255,0.2); transition: all 0.3s ease; min-height: 0; }
        .panel-turno-label { font-size: 3.5rem; font-weight: 900; color: var(--accent-color); margin-bottom: 10px; }
        .panel-turno-numero { font-size: 8vw; font-weight: 900; background: #000; color: var(--primary-green); padding: 15px 40px; border-radius: 40px; border: 8px solid #fff; white-space: nowrap; }
        .panel-turno-modulo-num { font-size: 6vw; font-weight: 900; color: var(--accent-color); }
        
        /* ANIMACIONES */
        .llamado-activo { animation: pulse-active 1s infinite alternate; }
        @keyframes pulse-active { from { transform: scale(1); box-shadow: 0 0 40px rgba(34, 197, 94, 0.5); } to { transform: scale(1.05); box-shadow: 0 0 80px rgba(250, 204, 21, 0.8); } }
        
        #btn-audio { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; padding: 12px 25px; background: var(--accent-color); border: none; border-radius: 50px; font-weight: bold; cursor: pointer; color: black; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    </style>
</head>
<body>

<button id="btn-audio">HAGA CLIC PARA ACTIVAR SONIDO Y VOZ</button>
<audio id="snd-ding" src="assets/sounds/ding.mp3" preload="auto"></audio>

<div class="layout">
    <header>
        <div class="header-left">
            <a href="display.php" class="btn-volver" title="Volver al menú de pantallas">⬅</a>
            <img src="assets/img/logo.png" alt="Logo Hualqui" class="header-logo">
            <div class="header-titulos">
                <h1>MUNICIPALIDAD DE HUALQUI</h1>
                <span><?= htmlspecialchars($dept['nombre']) ?></span>
            </div>
        </div>
        <div class="hora-actual" id="hora-actual">00:00:00</div>
    </header>

    <div class="col-left">
        <div class="historial-titulo">ÚLTIMOS LLAMADOS</div>
        <div id="lista-tickets" style="display: flex; flex-direction: column; gap: 5px; flex: 1;"></div>
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
            <img src="<?= htmlspecialchars($dept['banner_1']) ?>" alt="Publicidad 1">
        </div>
        <div class="banner-container">
            <img src="<?= htmlspecialchars($dept['banner_2']) ?>" alt="Publicidad 2">
        </div>
    </div>
</div>

<script>
let ultimaMarcaTiempo = null;
let audioActivado = false;
let vocesCargadas = [];

function cargarVoces() { vocesCargadas = window.speechSynthesis.getVoices(); }
if (speechSynthesis.onvoiceschanged !== undefined) { speechSynthesis.onvoiceschanged = cargarVoces; }

document.getElementById('btn-audio').addEventListener('click', function() {
    audioActivado = true; this.style.display = 'none'; cargarVoces();
});

function hablar(texto) {
    if (!window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    const mensaje = new SpeechSynthesisUtterance(texto);
    mensaje.lang = 'es-ES'; mensaje.rate = 0.9;
    const voz = vocesCargadas.find(v => (v.name.includes('Google') || v.lang.includes('es')));
    if (voz) mensaje.voice = voz;
    window.speechSynthesis.speak(mensaje);
}

function anunciarLlamado(numero, modulo) {
    const audio = document.getElementById('snd-ding');
    audio.play().then(() => {
        setTimeout(() => {
            let numLimpio = numero.replace(/-/g, ' ');
            if(numero.toUpperCase().startsWith('P')) numLimpio = numLimpio.replace(/P/i, 'Preferencial, ');
            else if(numero.toUpperCase().startsWith('S')) numLimpio = numLimpio.replace(/S/i, 'Preferencial, ');
            
            hablar(`Turno. ${numLimpio}. Diríjase a módulo. ${modulo}`);
        }, 1300);
    }).catch(e => console.warn("Interacción requerida para audio"));
}

async function cargarDisplay() {
    try {
        const resp = await fetch('display_api.php?dept=<?= $dept_id ?>', { cache: 'no-store' });
        const data = await resp.json();

        document.getElementById('hora-actual').textContent = new Date().toLocaleTimeString('es-CL');

        if (!data.tickets || data.tickets.length === 0) return;

        const actual = data.tickets[0];
        const codigoActual = actual.prefijo + '-' + String(actual.numero).padStart(3, '0');
        
        if (ultimaMarcaTiempo !== actual.hora_llamado) {
            if (ultimaMarcaTiempo !== null && audioActivado) {
                anunciarLlamado(codigoActual, actual.modulo);
                const mp = document.getElementById('main-panel');
                mp.classList.remove('llamado-activo');
                void mp.offsetWidth; 
                mp.classList.add('llamado-activo');
                setTimeout(() => mp.classList.remove('llamado-activo'), 8000);
            }
            ultimaMarcaTiempo = actual.hora_llamado;
            document.getElementById('turno-numero').textContent = codigoActual;
            document.getElementById('turno-modulo').textContent = actual.modulo;
        }

        const contTickets = document.getElementById('lista-tickets');
        contTickets.innerHTML = '';
        let historial = data.tickets.filter(t => !(t.prefijo === actual.prefijo && t.numero === actual.numero));
        historial.sort((a, b) => b.id - a.id);

        historial.slice(0, 8).forEach(t => {
            const codigo = t.prefijo + '-' + String(t.numero).padStart(3, '0');
            contTickets.insertAdjacentHTML('beforeend', `
                <div class="ticket-card">
                    <div class="ticket-col-num"><span class="ticket-label">Turno</span><span class="ticket-numero">${codigo}</span></div>
                    <div class="ticket-col-mod"><span style="font-size:0.5rem; font-weight:bold;">MOD</span><span class="ticket-modulo-val">${t.modulo}</span></div>
                </div>
            `);
        });
    } catch (e) { console.error("Error cargando display:", e); }
}

setInterval(cargarDisplay, 3000);
cargarDisplay();
</script>
</body>
</html>