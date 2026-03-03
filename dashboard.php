<?php
// dashboard.php
require_once __DIR__ . '/auth.php';
requireLogin(['ADMIN']);
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
        --primary-light:#e6f2ff;
        --success:#10b981;
        --warning:#f59e0b;
        --danger:#ef4444;
        --bg:#f9fafb;
        --card-bg:#ffffff;
        --text:#111827;
        --text-secondary:#6b7280;
        --border:#e5e7eb;
        --shadow:0 1px 3px rgba(0,0,0,0.1);
        --shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1);
    }

    * { box-sizing:border-box; }
    
    html {
        scroll-behavior:smooth;
    }

    body {
        margin:0;
        font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        background:var(--bg);
        color:var(--text);
        line-height:1.6;
    }

    /* Header mejorado */
header {
    padding:1rem 1.8rem;
    background:linear-gradient(135deg,rgb(2, 71, 23) 0%,rgb(5, 170, 68) 100%);
    color:#fff;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:var(--shadow-lg);
    position:sticky;
    top:0;
    z-index:100;
}

    header h1 { 
        margin:0; 
        font-size:1.6rem;
        font-weight:700;
        letter-spacing:-0.5px;
    }

    header .links {
        display:flex;
        gap:0.5rem;
        align-items:center;
        flex-wrap:wrap;
    }

    header .links a {
        color:#fff;
        text-decoration:none;
        font-size:0.9rem;
        font-weight:500;
        padding:0.5rem 1rem;
        border-radius:0.5rem;
        border:1px solid rgba(255,255,255,0.3);
        transition:all 0.3s ease;
        display:inline-flex;
        align-items:center;
        gap:0.3rem;
    }

    header .links a:hover { 
        background:rgba(255,255,255,0.15);
        border-color:rgba(255,255,255,0.5);
        transform:translateY(-2px);
    }

    /* Layout principal */
    main { 
        max-width:1400px; 
        margin:2rem auto; 
        padding:0 1rem;
    }

    /* KPI Grid mejorado */
    .kpi-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
        gap:1.5rem;
        margin-bottom:2rem;
    }

    .kpi-card {
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1.5rem;
        box-shadow:var(--shadow);
        border-left:4px solid var(--primary);
        transition:all 0.3s ease;
        position:relative;
        overflow:hidden;
    }

    .kpi-card::before {
        content:'';
        position:absolute;
        top:-50%;
        right:-50%;
        width:200px;
        height:200px;
        background:radial-gradient(circle, rgba(0,85,170,0.05) 0%, transparent 70%);
        border-radius:50%;
    }

    .kpi-card:hover {
        transform:translateY(-4px);
        box-shadow:var(--shadow-lg);
    }

    .kpi-card.alt { border-left-color:var(--success); }
    .kpi-card.warning { border-left-color:var(--warning); }

    .kpi-title { 
        font-size:0.85rem; 
        color:var(--text-secondary); 
        margin-bottom:0.5rem;
        font-weight:600;
        text-transform:uppercase;
        letter-spacing:0.5px;
        position:relative;
        z-index:1;
    }

    .kpi-value { 
        font-size:2.2rem; 
        font-weight:700;
        margin:0.5rem 0;
        position:relative;
        z-index:1;
        background:linear-gradient(135deg, var(--text) 0%, var(--text-secondary) 100%);
        -webkit-background-clip:text;
        -webkit-text-fill-color:transparent;
        background-clip:text;
    }

    .kpi-sub { 
        font-size:0.8rem; 
        color:var(--text-secondary); 
        position:relative;
        z-index:1;
    }

    /* Loading state */
    .kpi-value.loading::after,
    .chart-wrapper.loading::after {
        content:'';
        position:absolute;
        width:100%;
        height:100%;
        background:linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        animation:shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% { transform:translateX(-100%); }
        100% { transform:translateX(100%); }
    }

    /* Charts Grid mejorado */
    .charts-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(450px, 1fr));
        gap:2rem;
        margin-bottom:2rem;
    }

    .chart-card {
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1.5rem;
        box-shadow:var(--shadow);
        transition:all 0.3s ease;
    }

    .chart-card:hover {
        box-shadow:var(--shadow-lg);
    }

    .chart-card h2 {
        margin:0 0 1rem;
        font-size:1.1rem;
        color:var(--text);
        display:flex;
        align-items:center;
        gap:0.5rem;
    }

    .chart-card h2::before {
        content:'📊';
        opacity:0.6;
    }

    .chart-wrapper {
        position:relative;
        height:300px;
        width:100%;
    }

    /* Calendar Card mejorado */
    .calendar-card {
        background:var(--card-bg);
        border-radius:0.75rem;
        padding:1.5rem;
        box-shadow:var(--shadow);
    }

    .calendar-card h2 { 
        margin:0 0 1rem; 
        font-size:1.1rem;
        display:flex;
        align-items:center;
        gap:0.5rem;
    }

    .calendar-card h2::before {
        content:'📅';
        opacity:0.6;
    }

    .calendar-table {
        width:100%;
        border-collapse:collapse;
        font-size:0.9rem;
    }

    .calendar-table th, .calendar-table td {
        padding:0.75rem;
        border-bottom:1px solid var(--border);
        text-align:left;
    }

    .calendar-table th {
        background:var(--primary-light);
        font-weight:600;
        color:var(--primary);
        text-transform:uppercase;
        font-size:0.8rem;
        letter-spacing:0.5px;
    }

    .calendar-table tbody tr {
        transition:background 0.2s ease;
    }

    .calendar-table tbody tr:hover {
        background:var(--primary-light);
    }

    .calendar-table tr:last-child td { border-bottom:none; }

    .calendar-table td:last-child {
        text-align:right;
        font-weight:600;
        color:var(--primary);
    }

    /* Loading skeleton */
    .skeleton {
        background:linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size:200% 100%;
        animation:loading 1.5s infinite;
    }

    @keyframes loading {
        0% { background-position:200% 0; }
        100% { background-position:-200% 0; }
    }

    /* Error state */
    .error-state {
        background:var(--danger);
        color:white;
        padding:1rem;
        border-radius:0.5rem;
        text-align:center;
        margin:1rem 0;
    }

    /* Empty state */
    .empty-state {
        text-align:center;
        padding:2rem;
        color:var(--text-secondary);
    }

    .empty-state svg {
        width:80px;
        height:80px;
        margin-bottom:1rem;
        opacity:0.5;
    }

    /* Responsive Design mejorado */
    @media (max-width:1024px) {
        .charts-grid {
            grid-template-columns:1fr;
        }
    }

    @media (max-width:768px) {
        header { 
            flex-direction:column; 
            align-items:flex-start; 
            gap:1rem;
            padding:1rem;
        }

        header h1 {
            font-size:1.3rem;
        }

        header .links {
            width:100%;
            gap:0.3rem;
        }

        header .links a {
            font-size:0.8rem;
            padding:0.4rem 0.8rem;
            flex:1;
            min-width:fit-content;
            justify-content:center;
        }

        main { 
            padding:0 0.5rem; 
            margin:1rem auto;
        }

        .kpi-grid {
            grid-template-columns:1fr;
            gap:1rem;
        }

        .kpi-value {
            font-size:1.8rem;
        }

        .chart-wrapper {
            height:250px;
        }

        .calendar-table {
            font-size:0.8rem;
        }

        .calendar-table th, .calendar-table td {
            padding:0.5rem;
        }
    }

    @media (max-width:480px) {
        header h1 {
            font-size:1.1rem;
        }

        .kpi-card {
            padding:1rem;
        }

        .kpi-value {
            font-size:1.5rem;
        }

        .chart-card {
            padding:1rem;
        }

        header .links a {
            font-size:0.75rem;
            padding:0.3rem 0.6rem;
        }
    }

    /* Animaciones sutiles */
    @keyframes fadeIn {
        from { opacity:0; transform:translateY(10px); }
        to { opacity:1; transform:translateY(0); }
    }

    .kpi-card, .chart-card, .calendar-card {
        animation:fadeIn 0.5s ease-out forwards;
    }

    .kpi-card:nth-child(1) { animation-delay:0.1s; }
    .kpi-card:nth-child(2) { animation-delay:0.2s; }
    .kpi-card:nth-child(3) { animation-delay:0.3s; }

    /* Tooltip mejora */
    .tooltip {
        position:relative;
        cursor:help;
    }

    .tooltip:hover::after {
        content:attr(data-tooltip);
        position:absolute;
        bottom:100%;
        left:50%;
        transform:translateX(-50%);
        background:var(--text);
        color:white;
        padding:0.5rem 0.75rem;
        border-radius:0.3rem;
        font-size:0.8rem;
        white-space:nowrap;
        margin-bottom:0.5rem;
        z-index:1000;
        box-shadow:var(--shadow-lg);
    }
</style>
</head>
<body>

<header>
    <div>
        <h1>📊 Dashboard - Permisos de Circulación</h1>
    </div>
    <div class="links">
        <a href="index.php" title="Volver al inicio">🏠 Inicio</a>
        <a href="dashboard_calendario.php" title="Ver informe diario" class="btn">📋 Informe diario</a>
        <a href="display.php" target="_blank" title="Ver pantalla pública">🖥️ Pantalla pública</a>
        <a href="admin_usuarios.php" target="_blank" title="Gestionar usuarios">👥 Usuarios</a>
    </div>
</header>

<main>
    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Tickets totales</div>
            <div class="kpi-value" id="kpi-total">-</div>
            <div class="kpi-sub">📚 Histórico completo</div>
        </div>
        <div class="kpi-card alt">
            <div class="kpi-title">Tickets hoy</div>
            <div class="kpi-value" id="kpi-hoy">-</div>
            <div class="kpi-sub">🕐 Zona America/Santiago</div>
        </div>
        <div class="kpi-card warning">
            <div class="kpi-title">Últimos 7 días</div>
            <div class="kpi-value" id="kpi-7">-</div>
            <div class="kpi-sub">📈 Tendencia actual</div>
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
                <tr><td colspan="2" class="skeleton" style="height: 40px;"></td></tr>
            </tbody>
        </table>
    </section>
</main>

<script>
function formatoFechaCL(iso) {
    // iso: YYYY-MM-DD -> dd-mm-yyyy
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    const date = new Date(iso + 'T00:00:00');
    const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const dayName = dayNames[date.getDay()];
    return `${d}-${m}-${y} (${dayName})`;
}

async function cargarDashboard() {
    try {
        const resp = await fetch('dashboard_data.php', {cache:'no-store'});
        
        if (!resp.ok) {
            throw new Error(`Error HTTP: ${resp.status}`);
        }

        const data = await resp.json();

        // Validar que tenemos datos
        if (!data || !data.kpi) {
            throw new Error('Datos inválidos del servidor');
        }

        // KPIs con animación
        const animarNumero = (elemento, valorFinal, duracion = 800) => {
            const paso = valorFinal / (duracion / 16);
            let actual = 0;
            const intervalo = setInterval(() => {
                actual += paso;
                if (actual >= valorFinal) {
                    elemento.textContent = valorFinal;
                    clearInterval(intervalo);
                } else {
                    elemento.textContent = Math.floor(actual);
                }
            }, 16);
        };

        animarNumero(document.getElementById('kpi-total'), data.kpi.total_tickets ?? 0);
        animarNumero(document.getElementById('kpi-hoy'), data.kpi.hoy ?? 0);
        animarNumero(document.getElementById('kpi-7'), data.kpi.ultimos_7 ?? 0);

        // -------- Tickets por día (línea, últimos 14) --------
        const labelsDia = (data.porDia || []).map(r => formatoFechaCL(r.fecha));
        const valoresDia = (data.porDia || []).map(r => Number(r.total));

        if (labelsDia.length === 0) {
            document.getElementById('chartPorDia').parentElement.innerHTML = '<div class="empty-state">Sin datos disponibles</div>';
        } else {
            const ctxDia = document.getElementById('chartPorDia').getContext('2d');
            new Chart(ctxDia, {
                type: 'line',
                data: {
                    labels: labelsDia,
                    datasets: [{
                        label: 'Tickets',
                        data: valoresDia,
                        borderColor: '#0055aa',
                        backgroundColor: 'rgba(0,85,170,0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#0055aa',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    interaction: { mode:'index', intersect:false },
                    scales: {
                        x: { 
                            ticks:{ color:'#6b7280', font:{ size:11 } },
                            grid:{ color:'rgba(0,0,0,0.05)' }
                        },
                        y: { 
                            beginAtZero:true, 
                            ticks:{ color:'#6b7280', precision:0, font:{ size:11 } },
                            grid:{ color:'rgba(0,0,0,0.05)' }
                        }
                    },
                    plugins: {
                        legend: { display:true, position:'top' },
                        tooltip: {
                            backgroundColor:'rgba(0,0,0,0.8)',
                            padding:12,
                            titleFont:{ size:12 },
                            bodyFont:{ size:11 },
                            callbacks: {
                                title: (items) => items[0].label,
                                label: (item) => `${item.dataset.label}: ${item.parsed.y}`
                            }
                        }
                    }
                }
            });
        }

        // -------- Tickets por módulo (barras, últimos 7) --------
        const modMap = {};
        (data.modDia || []).forEach(r => {
            const m = r.modulo || 'Sin módulo';
            modMap[m] = (modMap[m] || 0) + Number(r.total);
        });

        const labelsMod = Object.keys(modMap);
        const valoresMod = labelsMod.map(m => modMap[m]);

        if (labelsMod.length === 0) {
            document.getElementById('chartModulos').parentElement.innerHTML = '<div class="empty-state">Sin datos disponibles</div>';
        } else {
            const colores = [
                'rgba(16,185,129,0.7)',
                'rgba(0,85,170,0.7)',
                'rgba(245,158,11,0.7)',
                'rgba(239,68,68,0.7)',
                'rgba(139,92,246,0.7)',
                'rgba(14,165,233,0.7)'
            ];

            const ctxMod = document.getElementById('chartModulos').getContext('2d');
            new Chart(ctxMod, {
                type: 'bar',
                data: {
                    labels: labelsMod,
                    datasets: [{
                        label: 'Tickets',
                        data: valoresMod,
                        backgroundColor: valoresMod.map((_, i) => colores[i % colores.length]),
                        borderColor: 'rgba(0,0,0,0.05)',
                        borderWidth:1,
                        borderRadius:6
                    }]
                },
                options: {
                    responsive:true,
                    maintainAspectRatio:false,
                    interaction: { mode:'index', intersect:false },
                    scales: {
                        x: { 
                            ticks:{ color:'#6b7280', font:{ size:11 } },
                            grid:{ display:false }
                        },
                        y: { 
                            beginAtZero:true, 
                            ticks:{ color:'#6b7280', precision:0, font:{ size:11 } },
                            grid:{ color:'rgba(0,0,0,0.05)' }
                        }
                    },
                    plugins: {
                        legend:{ display:true, position:'top' },
                        tooltip: {
                            backgroundColor:'rgba(0,0,0,0.8)',
                            padding:12,
                            titleFont:{ size:12 },
                            bodyFont:{ size:11 },
                            callbacks: {
                                label: (item) => `${item.dataset.label}: ${item.parsed.y}`
                            }
                        }
                    }
                }
            });
        }

        // -------- Calendario (tabla últimos 30 días) --------
        const tbodyCal = document.getElementById('tbody-calendario');
        tbodyCal.innerHTML = '';

        if (!data.porDia || !data.porDia.length) {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="2" style="text-align:center; padding:2rem;">Sin datos en el periodo.</td>';
            tbodyCal.appendChild(tr);
        } else {
            data.porDia.forEach((r, index) => {
                const tr = document.createElement('tr');
                tr.style.animationDelay = `${index * 0.05}s`;
                tr.innerHTML = `
                    <td>${formatoFechaCL(r.fecha)}</td>
                    <td>${r.total}</td>
                `;
                tbodyCal.appendChild(tr);
            });
        }

    } catch (error) {
        console.error('Error cargando dashboard:', error);
        const errorHtml = `<div class="error-state">❌ Error al cargar los datos: ${error.message}</div>`;
        document.querySelector('main').insertAdjacentHTML('afterbegin', errorHtml);
    }
}

// Cargar datos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', cargarDashboard);

// Recargar datos cada 5 minutos
setInterval(cargarDashboard, 5 * 60 * 1000);
</script>
</body>
</html>