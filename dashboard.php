<?php
// dashboard.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN']);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$pdo = getPDO();
$esSuperAdmin = ($_SESSION['rol'] === 'SUPER-ADMIN');
$mi_dept_id = $_SESSION['departamento_id'] ?? null;

// Obtener lista de departamentos para el filtro del SuperAdmin
$departamentos = [];
if ($esSuperAdmin) {
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll();
} else {
    // Para el Admin normal, buscamos el nombre de su departamento para el título
    $stmt = $pdo->prepare("SELECT nombre FROM departamentos WHERE id = ?");
    $stmt->execute([$mi_dept_id]);
    $nombre_dept = $stmt->fetchColumn() ?: 'Mi Oficina';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dashboard de filas</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    :root { --primary:#0055aa; --primary-light:#e6f2ff; --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --bg:#f9fafb; --card-bg:#ffffff; --text:#111827; --text-secondary:#6b7280; --border:#e5e7eb; --shadow:0 1px 3px rgba(0,0,0,0.1); --shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1); }
    * { box-sizing:border-box; } html { scroll-behavior:smooth; } body { margin:0; font-family:system-ui,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
    header { padding:1rem 1.8rem; background:linear-gradient(135deg,rgb(2, 71, 23) 0%,rgb(5, 170, 68) 100%); color:#fff; display:flex; justify-content:space-between; align-items:center; box-shadow:var(--shadow-lg); position:sticky; top:0; z-index:100; }
    header h1 { margin:0; font-size:1.6rem; font-weight:700; letter-spacing:-0.5px; }
    header .links { display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap; }
    header .links a { color:#fff; text-decoration:none; font-size:0.9rem; font-weight:500; padding:0.5rem 1rem; border-radius:0.5rem; border:1px solid rgba(255,255,255,0.3); transition:all 0.3s ease; display:inline-flex; align-items:center; gap:0.3rem; }
    header .links a:hover { background:rgba(255,255,255,0.15); border-color:rgba(255,255,255,0.5); transform:translateY(-2px); }
    
    main { max-width:1400px; margin:2rem auto; padding:0 1rem; }
    
    /* FILTRO SUPER-ADMIN */
    .filter-bar { background: var(--card-bg); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 15px; }
    .filter-bar select { padding: 8px; font-size: 1rem; border-radius: 5px; border: 1px solid var(--border); min-width: 250px; }
    
    /* KPI Grid */
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem; margin-bottom:2rem; }
    .kpi-card { background:var(--card-bg); border-radius:0.75rem; padding:1.5rem; box-shadow:var(--shadow); border-left:4px solid var(--primary); transition:all 0.3s ease; position:relative; overflow:hidden; }
    .kpi-card::before { content:''; position:absolute; top:-50%; right:-50%; width:200px; height:200px; background:radial-gradient(circle, rgba(0,85,170,0.05) 0%, transparent 70%); border-radius:50%; }
    .kpi-card:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); }
    .kpi-card.alt { border-left-color:var(--success); } .kpi-card.warning { border-left-color:var(--warning); }
    .kpi-title { font-size:0.85rem; color:var(--text-secondary); margin-bottom:0.5rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; position:relative; z-index:1; }
    .kpi-value { font-size:2.2rem; font-weight:700; margin:0.5rem 0; position:relative; z-index:1; background:linear-gradient(135deg, var(--text) 0%, var(--text-secondary) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .kpi-sub { font-size:0.8rem; color:var(--text-secondary); position:relative; z-index:1; }
    
    .charts-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(450px, 1fr)); gap:2rem; margin-bottom:2rem; }
    .chart-card { background:var(--card-bg); border-radius:0.75rem; padding:1.5rem; box-shadow:var(--shadow); transition:all 0.3s ease; }
    .chart-card:hover { box-shadow:var(--shadow-lg); }
    .chart-card h2 { margin:0 0 1rem; font-size:1.1rem; color:var(--text); display:flex; align-items:center; gap:0.5rem; }
    .chart-wrapper { position:relative; height:300px; width:100%; }
    
    .calendar-card { background:var(--card-bg); border-radius:0.75rem; padding:1.5rem; box-shadow:var(--shadow); }
    .calendar-card h2 { margin:0 0 1rem; font-size:1.1rem; display:flex; align-items:center; gap:0.5rem; }
    .calendar-table { width:100%; border-collapse:collapse; font-size:0.9rem; }
    .calendar-table th, .calendar-table td { padding:0.75rem; border-bottom:1px solid var(--border); text-align:left; }
    .calendar-table th { background:var(--primary-light); font-weight:600; color:var(--primary); text-transform:uppercase; font-size:0.8rem; letter-spacing:0.5px; }
    .calendar-table tbody tr:hover { background:var(--primary-light); }
    .calendar-table tr:last-child td { border-bottom:none; } .calendar-table td:last-child { text-align:right; font-weight:600; color:var(--primary); }
    
    .empty-state { text-align:center; padding:2rem; color:var(--text-secondary); }
</style>
</head>
<body>

<header>
    <div>
        <h1 id="titulo-panel">📊 Estadísticas - <?= $esSuperAdmin ? 'Toda la Municipalidad' : htmlspecialchars($nombre_dept) ?></h1>
    </div>
    <div class="links">
        <a href="index.php" title="Volver al inicio">🏠 Inicio</a>
        <a id="btn-calendario" href="dashboard_calendario.php" title="Ver informe diario">📋 Calendario Detallado</a>
    </div>
</header>

<main>
    <?php if ($esSuperAdmin): ?>
    <div class="filter-bar">
        <strong>🏢 Filtrar por Oficina: </strong>
        <select id="filtro-dept" onchange="cargarDashboard()">
            <option value="ALL">🌐 Todas las Oficinas (Global)</option>
            <?php foreach ($departamentos as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php else: ?>
        <input type="hidden" id="filtro-dept" value="<?= $mi_dept_id ?>">
    <?php endif; ?>

    <section class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Tickets totales</div>
            <div class="kpi-value" id="kpi-total">-</div>
            <div class="kpi-sub">📚 Histórico completo</div>
        </div>
        <div class="kpi-card alt">
            <div class="kpi-title">Tickets hoy</div>
            <div class="kpi-value" id="kpi-hoy">-</div>
            <div class="kpi-sub">🕐 Día actual</div>
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
        <h2>Listado de atenciones (últimos 30 días)</h2>
        <table class="calendar-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cantidad de tickets</th>
                </tr>
            </thead>
            <tbody id="tbody-calendario">
                <tr><td colspan="2" style="text-align: center;">Cargando datos...</td></tr>
            </tbody>
        </table>
    </section>
</main>

<script>
function formatoFechaCL(iso) {
    if (!iso) return '';
    const [y,m,d] = iso.split('-');
    const date = new Date(iso + 'T00:00:00');
    const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    return `${d}-${m}-${y} (${dayNames[date.getDay()]})`;
}

let chartGraficoDia = null;
let chartGraficoMod = null;

async function cargarDashboard() {
    try {
        const deptId = document.getElementById('filtro-dept').value;
        const url = `dashboard_data.php?dept=${deptId}`;
        const resp = await fetch(url, {cache:'no-store'});
        const data = await resp.json();

        // 1. Actualizar el link del Calendario para que reciba el filtro
        const btnCalendario = document.getElementById('btn-calendario');
        btnCalendario.href = `dashboard_calendario.php?dept=${deptId}`;

        // 2. Si es SuperAdmin, actualizar el título al seleccionar
        const selectDept = document.getElementById('filtro-dept');
        if (selectDept && selectDept.tagName === 'SELECT') {
            const textoDept = selectDept.options[selectDept.selectedIndex].text;
            document.getElementById('titulo-panel').innerText = `📊 Estadísticas - ${textoDept}`;
        }

        // 3. KPIs
        document.getElementById('kpi-total').textContent = data.kpi.total_tickets ?? 0;
        document.getElementById('kpi-hoy').textContent = data.kpi.hoy ?? 0;
        document.getElementById('kpi-7').textContent = data.kpi.ultimos_7 ?? 0;

        // 4. Gráfico Tickets por día (línea)
        const labelsDia = (data.porDia || []).map(r => formatoFechaCL(r.fecha));
        const valoresDia = (data.porDia || []).map(r => Number(r.total));
        
        if (chartGraficoDia) chartGraficoDia.destroy();
        
        if (labelsDia.length > 0) {
            const ctxDia = document.getElementById('chartPorDia').getContext('2d');
            chartGraficoDia = new Chart(ctxDia, {
                type: 'line',
                data: { labels: labelsDia, datasets: [{ label: 'Tickets', data: valoresDia, borderColor: '#0055aa', backgroundColor: 'rgba(0,85,170,0.1)', fill: true, tension: 0.3 }] },
                options: { responsive:true, maintainAspectRatio:false }
            });
        } else {
             // Limpiar si no hay datos
             document.getElementById('chartPorDia').getContext('2d').clearRect(0, 0, 400, 300);
        }

        // 5. Gráfico Tickets por módulo (barras)
        const modMap = {};
        (data.modDia || []).forEach(r => {
            const m = r.modulo || 'Global / Sin asignar';
            modMap[m] = (modMap[m] || 0) + Number(r.total);
        });
        const labelsMod = Object.keys(modMap);
        const valoresMod = labelsMod.map(m => modMap[m]);

        if (chartGraficoMod) chartGraficoMod.destroy();
        
        if (labelsMod.length > 0) {
            const ctxMod = document.getElementById('chartModulos').getContext('2d');
            chartGraficoMod = new Chart(ctxMod, {
                type: 'bar',
                data: { labels: labelsMod, datasets: [{ label: 'Tickets', data: valoresMod, backgroundColor: '#10b981' }] },
                options: { responsive:true, maintainAspectRatio:false }
            });
        } else {
             // Limpiar si no hay datos
             document.getElementById('chartModulos').getContext('2d').clearRect(0, 0, 400, 300);
        }

        // 6. Listado últimos 30 días (Tabla)
        const tbodyCal = document.getElementById('tbody-calendario');
        tbodyCal.innerHTML = '';
        if (!data.porDia || !data.porDia.length) {
            tbodyCal.innerHTML = '<tr><td colspan="2" class="empty-state">Sin atenciones registradas en esta oficina.</td></tr>';
        } else {
            // Mostrar los más recientes primero
            const invertidos = [...data.porDia].reverse();
            invertidos.forEach(r => {
                tbodyCal.innerHTML += `<tr><td>${formatoFechaCL(r.fecha)}</td><td>${r.total}</td></tr>`;
            });
        }
    } catch (error) {
        console.error('Error cargando dashboard:', error);
    }
}

document.addEventListener('DOMContentLoaded', cargarDashboard);
</script>
</body>
</html>