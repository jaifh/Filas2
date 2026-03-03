<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Verificar acceso (solo admin)
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();

// Obtener estadísticas
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = "ESPERA" THEN 1 ELSE 0 END) as espera,
        SUM(CASE WHEN estado = "LLAMADO" THEN 1 ELSE 0 END) as llamado,
        SUM(CASE WHEN estado = "FINALIZADO" THEN 1 ELSE 0 END) as finalizado
    FROM tickets
    WHERE fecha = ?
');
$stmt->execute([date('Y-m-d')]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel de Impresión Masiva</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #003366, #004488);
            color: white;
            padding: 30px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        header h1 {
            margin-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0055aa;
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #003366;
        }

        .panel {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .panel h2 {
            margin-bottom: 20px;
            color: #003366;
            border-bottom: 2px solid #0055aa;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        select:focus {
            outline: none;
            border-color: #0055aa;
            box-shadow: 0 0 5px rgba(0, 85, 170, 0.3);
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0055aa, #003366);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0077dd, #004488);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 85, 170, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #00aa55, #008844);
            color: white;
            width: 100%;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #00dd77, #00aa55);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 170, 85, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffaa00, #ff8800);
            color: white;
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #ffcc33, #ffaa00);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .option-btn {
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-weight: 600;
        }

        .option-btn:hover {
            border-color: #0055aa;
            background: #f0f7ff;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 85, 170, 0.2);
        }

        .option-btn.selected {
            background: #0055aa;
            color: white;
            border-color: #003366;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-info {
            background: #e3f2fd;
            border-color: #2196f3;
            color: #1565c0;
        }

        .alert-success {
            background: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .alert-warning {
            background: #fff3e0;
            border-color: #ff9800;
            color: #e65100;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0055aa;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }

            header {
                padding: 20px;
            }

            .panel {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <h1>🖨️ Panel de Impresión Masiva</h1>
        <p>Generar y imprimir múltiples tickets de turnos</p>
    </header>

    <div class="stats">
        <div class="stat-card">
            <h3>Tickets Hoy</h3>
            <div class="value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card">
            <h3>En Espera</h3>
            <div class="value" style="color: #ff9800;"><?= $stats['espera'] ?></div>
        </div>
        <div class="stat-card">
            <h3>Llamados</h3>
            <div class="value" style="color: #2196f3;"><?= $stats['llamado'] ?></div>
        </div>
        <div class="stat-card">
            <h3>Finalizados</h3>
            <div class="value" style="color: #4caf50;"><?= $stats['finalizado'] ?></div>
        </div>
    </div>

    <div class="panel">
        <h2>📋 Generar Tickets Masivos</h2>

        <div class="alert alert-info">
            ℹ️ Selecciona la cantidad de tickets a generar. Se crearán de forma secuencial con prefijo automático.
        </div>

        <form id="formGenerarTickets">
            <div class="grid-2">
                <div class="form-group">
                    <label>Cantidad de Tickets:</label>
                    <div class="options">
                        <button type="button" class="option-btn" data-cantidad="10">10</button>
                        <button type="button" class="option-btn" data-cantidad="25">25</button>
                        <button type="button" class="option-btn" data-cantidad="50">50</button>
                        <button type="button" class="option-btn" data-cantidad="100">100</button>
                    </div>
                    <input type="hidden" id="cantidad" name="cantidad" value="">
                </div>

                <div class="form-group">
                    <label>Tamaño de Papel:</label>
                    <div class="options">
                        <button type="button" class="option-btn" data-papel="58">58mm</button>
                        <button type="button" class="option-btn selected" data-papel="80">80mm</button>
                    </div>
                    <input type="hidden" id="papel" name="papel" value="80">
                </div>
            </div>

            <div class="form-group">
                <label>Opciones de Impresión:</label>
                <div style="margin-top: 15px;">
                    <label style="display: flex; align-items: center; font-weight: normal; margin-bottom: 10px;">
                        <input type="checkbox" id="incluirQR" checked> Incluir QR de Verificación
                    </label>
                    <label style="display: flex; align-items: center; font-weight: normal; margin-bottom: 10px;">
                        <input type="checkbox" id="incluirLogo" checked> Incluir Logo Municipal
                    </label>
                    <label style="display: flex; align-items: center; font-weight: normal;">
                        <input type="checkbox" id="formatoRecorte" checked> Formato para Recorte
                    </label>
                </div>
            </div>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Generando tickets masivos...</p>
            </div>

            <button type="submit" class="btn btn-success" id="btnGenerar">
                🖨️ GENERAR E IMPRIMIR
            </button>
        </form>
    </div>

    <div class="panel">
        <h2>📄 Reimprimir Histórico</h2>

        <div class="alert alert-info">
            ℹ️ Reimprimir tickets de fechas anteriores.
        </div>

        <form id="formReimprimir">
            <div class="grid-2">
                <div class="form-group">
                    <label>Fecha:</label>
                    <input type="date" id="fechaFiltro" name="fecha" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Prefijo:</label>
                    <input type="text" id="prefijo" name="prefijo" placeholder="Ej: H, P, C" value="H">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Estado:</label>
                    <select id="estadoFiltro">
                        <option value="">Todos</option>
                        <option value="ESPERA">Espera</option>
                        <option value="LLAMADO">Llamado</option>
                        <option value="FINALIZADO">Finalizado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tamaño de Papel:</label>
                    <select id="papelReimprimir">
                        <option value="58">58mm</option>
                        <option value="80" selected>80mm</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-warning" style="width: 100%;">
                🔄 REIMPRIMIR HISTÓRICO
            </button>
        </form>
    </div>

</div>

<script>
// Seleccionar cantidad
document.querySelectorAll('[data-cantidad]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('[data-cantidad]').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('cantidad').value = btn.dataset.cantidad;
    });
});

// Seleccionar papel
document.querySelectorAll('[data-papel]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('[data-papel]').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('papel').value = btn.dataset.papel;
    });
});

// Generar tickets masivos
document.getElementById('formGenerarTickets').addEventListener('submit', async (e) => {
    e.preventDefault();

    const cantidad = document.getElementById('cantidad').value;
    if (!cantidad) {
        alert('Por favor selecciona una cantidad');
        return;
    }

    const loading = document.getElementById('loading');
    const btn = document.getElementById('btnGenerar');
    loading.style.display = 'block';
    btn.disabled = true;

    try {
        const response = await fetch('generar_tickets_masivos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cantidad: parseInt(cantidad),
                papel: parseInt(document.getElementById('papel').value),
                incluirQR: document.getElementById('incluirQR').checked,
                incluirLogo: document.getElementById('incluirLogo').checked,
                formatoRecorte: document.getElementById('formatoRecorte').checked
            })
        });

        const data = await response.json();

        if (data.success) {
            alert(`✓ ${cantidad} tickets generados e impresos exitosamente`);
            // Abrir en nueva ventana para vista previa
            if (data.print_url) {
                window.open(data.print_url, '_blank');
            }
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error al generar: ' + error.message);
    } finally {
        loading.style.display = 'none';
        btn.disabled = false;
    }
});

// Reimprimir histórico
document.getElementById('formReimprimir').addEventListener('submit', async (e) => {
    e.preventDefault();

    const fecha = document.getElementById('fechaFiltro').value;
    const prefijo = document.getElementById('prefijo').value;
    const estado = document.getElementById('estadoFiltro').value;
    const papel = document.getElementById('papelReimprimir').value;

    try {
        const response = await fetch('reimprimir_historico.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                fecha: fecha,
                prefijo: prefijo,
                estado: estado,
                papel: parseInt(papel)
            })
        });

        const data = await response.json();

        if (data.success) {
            alert(`✓ ${data.tickets_count} tickets listos para imprimir`);
            if (data.print_url) {
                window.open(data.print_url, '_blank');
            }
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>
</body>
</html>