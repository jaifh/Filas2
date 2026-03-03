<?php
// dashboard.php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dashboard de filas</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    :root {
        --primary:#0055aa;
        --bg:#f3f4f6;
        --card-bg:#ffffff;
        --text:#111827;
        --muted:#6b7280;
    }
    * { box-sizing:border-box; }
    body {
        margin:0;
        font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        background:var(--bg);
        color:var(--text);
    }
    header {
        padding:1rem 1.8rem;
        background:#003366;
        color:#fff;
        display:flex;
        justify-content:space-between;
        align-items:center;
    }
    header h1 { margin:0; font-size:1.6rem; }
    header .links a {
        color:#fff;
        text-decoration:none;
        font-size:0.85rem;
        margin-left:1rem;
        padding:0.3rem 0.7rem;
        border-radius:999px;
        border:1px solid rgba(255,255,255,0.5);
    }
    header .links a:hover { background:rgba(255,255,255,0.15); }
    main { max-width:1200px; margin:1.5rem auto 2rem; padding:0 1rem; }
    .kpi-grid {
        display:flex;
        flex-wrap:wrap;
        gap:1rem;
        margin-bottom:1.5rem;
    }
    .kpi-card {
        flex:1 1 220px;
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1rem 1.2rem;
        box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .kpi-title { font-size:0.85rem; color:var(--muted); margin-bottom:0.3rem; }
    .kpi-value { font-size:1.8rem; font-weight:600; }
    .kpi-sub { font-size:0.8rem; color:var(--muted); margin-top:0.2rem; }

    .charts-grid {
        display:flex;
        flex-wrap:wrap;
        gap:1.5rem;
        margin-bottom:1.5rem;
    }
    .chart-card {
        flex:1 1 380px;
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1rem 1.2rem;
        box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .chart-card h2 {
        margin:0 0 0.5rem;
        font-size:1rem;
    }
    .chart-wrapper {
        position:relative;
        height:280px;
        width:100%;
    }

    .calendar-card {
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1rem 1.2rem;
        box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .calendar-card h2 { margin:0 0 0.5rem; font-size:1rem; }
    .calendar-table {
        width:100%;
        border-collapse:collapse;
        font-size:0.9rem;
    }
    .calendar-table th, .calendar-table td {
        padding:0.4rem 0.6rem;
        border-bottom:1px solid #e5e7eb;
        text-align:left;
    }
    .calendar-table th {
        background:#f9fafb;
        font-weight:600;
    }
    .calendar-table tr:last-child td { border-bottom:none; }

    @media (max-width:768px) {
        header { flex-direction:column; align-items:flex-start; gap:0.4rem; }
        main { padding:0 0.5rem; }
    }
</style>
</head>
<body>

<header>
    <div>
        <h1>Dashboard de filas - Permisos de Circulación</h1>
    </div>
    <div class="links">
        <a href="index.php">Inicio</a>
        <a href="dashboard_calendario.php" class="btn">Informe diario</a>

        <a href="display.php" target="_blank">Pantalla pública</a>
        <a href="admin_usuarios.php" target="_blank">Usuarios</a>
    </div>
</header>

<main>
    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Tickets totales (histórico)</div>
            <div class="kpi-value" id="kpi-total">-</div>
            <div class="kpi-sub">Basado en tickets_logs</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Tickets hoy</div>
            <div class="kpi-value" id="kpi-hoy">-</div>
            <div class="kpi-sub">Fecha Chile (zona America/Santiago)</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Últimos 7 días</div>
            <div class="kpi-value" id="kpi-7">-</div>
            <div class="kpi-sub">Incluye hoy</div>
        </div>
    </section>

    <section class="charts-grid">
        <div class="chart-card">
            <h2>Tickets por día (últimos 14 días)</h2>
            <div class="chart-wrapper">
                <canvas id="chartPorDia"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h2>Tickets por módulo (últimos 7 días)</h2>
            <div class="chart-wrapper">
                <canvas id="chartModulos"></canvas>
            </div>
        </div>
    </section>

    <section class="calendar-card">
        <h2>Calendario de atenciones (últimos 30 días)</h2>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cantidad de tickets</th>
                </tr>
            </thead>
            <tbody id="tbody-calendario">
                <tr><td colspan="2">Cargando...</td></tr>
            </tbody>
        </table>
    </section>
</main>

<script>
function formatoFechaCL(iso) {
    // iso: YYYY-MM-DD -> dd-mm-yyyy
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    return `${d}-${m}-${y}`;
}

async function cargarDashboard() {
    const resp = await fetch('dashboard_data.php', {cache:'no-store'});
    if (!resp.ok) {
        console.error('Error HTTP dashboard_data', resp.status);
        return;
    }
    const data = await resp.json();

    // KPIs
    document.getElementById('kpi-total').textContent = data.kpi.total_tickets ?? 0;
    document.getElementById('kpi-hoy').textContent   = data.kpi.hoy ?? 0;
    document.getElementById('kpi-7').textContent     = data.kpi.ultimos_7 ?? 0;

    // -------- Tickets por día (línea, últimos 14) --------
    const labelsDia = (data.porDia || []).map(r => formatoFechaCL(r.fecha));
    const valoresDia = (data.porDia || []).map(r => Number(r.total));

    const ctxDia = document.getElementById('chartPorDia').getContext('2d');
    new Chart(ctxDia, {
        type: 'line',
        data: {
            labels: labelsDia,
            datasets: [{
                label: 'Tickets',
                data: valoresDia,
                borderColor: '#0055aa',
                backgroundColor: 'rgba(0,85,170,0.15)',
                tension: 0.2,
                fill: true,
                pointRadius: 3
            }]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            scales: {
                x: { ticks:{ color:'#4b5563' } },
                y: { beginAtZero:true, ticks:{ color:'#4b5563', precision:0 } }
            },
            plugins: {
                legend: { display:false },
                tooltip: {
                    callbacks: {
                        title: (items) => items[0].label // ya viene formateada dd-mm-yyyy
                    }
                }
            }
        }
    });

    // -------- Tickets por módulo (barras, últimos 7) --------
    const modMap = {};
    (data.modDia || []).forEach(r => {
        const m = r.modulo || 'Sin módulo';
        modMap[m] = (modMap[m] || 0) + Number(r.total);
    });

    const labelsMod = Object.keys(modMap);
    const valoresMod = labelsMod.map(m => modMap[m]);

    const ctxMod = document.getElementById('chartModulos').getContext('2d');
    new Chart(ctxMod, {
        type: 'bar',
        data: {
            labels: labelsMod,
            datasets: [{
                label: 'Tickets',
                data: valoresMod,
                backgroundColor: 'rgba(16,185,129,0.7)',
                borderColor: '#059669',
                borderWidth:1
            }]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            scales: {
                x: { ticks:{ color:'#4b5563' } },
                y: { beginAtZero:true, ticks:{ color:'#4b5563', precision:0 } }
            },
            plugins: {
                legend:{ display:false }
            }
        }
    });

    // -------- Calendario (tabla últimos 30 días) --------
    // data.porDia solo trae 14 días; para un calendario de 30, conviene ajustar backend.
    // Mientras tanto usamos lo disponible.
    const tbodyCal = document.getElementById('tbody-calendario');
    tbodyCal.innerHTML = '';
    if (!data.porDia || !data.porDia.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="2">Sin datos en el periodo.</td>';
        tbodyCal.appendChild(tr);
    } else {
        data.porDia.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${formatoFechaCL(r.fecha)}</td>
                <td>${r.total}</td>
            `;
            tbodyCal.appendChild(tr);
        });
    }
}

window.addEventListener('DOMContentLoaded', cargarDashboard);
</script>
</body>
</html>
