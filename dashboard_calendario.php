<?php
// dashboard_calendario.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN']);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

$pdo = getPDO();
$rol_actual = $_SESSION['rol'];
$esSuperAdmin = ($rol_actual === 'SUPER-ADMIN');
$mi_dept_id = $_SESSION['departamento_id'] ?? null;

// Fecha base Chile
$tz = new DateTimeZone('America/Santiago');
$hoy = new DateTime('now', $tz);

// Año / mes desde GET o actual
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)$hoy->format('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)$hoy->format('n');

// Normalizar mes/año por si vienen fuera de rango
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

// Primer y último día del mes
$firstDay = new DateTime("$year-$month-01", $tz);
$firstWeekday = (int)$firstDay->format('N'); // 1=Lun .. 7=Dom
$daysInMonth = (int)$firstDay->format('t');

// Rango de fechas del mes
$startDate = $firstDay->format('Y-m-d');
$endDate   = (clone $firstDay)->modify('last day of this month')->format('Y-m-d');

// --- LÓGICA DE MULTITENENCIA (FILTROS) ---
$filtro_dept = $_GET['dept'] ?? 'ALL';

if (!$esSuperAdmin) {
    $filtro_dept = $mi_dept_id; // Forzar a su propio departamento
    $stmt = $pdo->prepare("SELECT nombre FROM departamentos WHERE id = ?");
    $stmt->execute([$mi_dept_id]);
    $nombre_dept = $stmt->fetchColumn() ?: 'Mi Oficina';
} else {
    $departamentos = $pdo->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC")->fetchAll();
    $nombre_dept = ($filtro_dept === 'ALL') ? 'Toda la Municipalidad' : 'Oficina Específica';
}

$condicion_dept = "";
$params = [$startDate, $endDate];
if ($filtro_dept !== 'ALL') {
    $condicion_dept = " AND departamento_id = ? ";
    $params[] = (int)$filtro_dept;
}

// Obtener conteo de tickets_logs por día del mes
$stmt = $pdo->prepare("
    SELECT fecha, COUNT(*) AS total
    FROM tickets_logs
    WHERE fecha BETWEEN ? AND ? " . $condicion_dept . "
    GROUP BY fecha
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Map fecha => total
$counts = [];
foreach ($rows as $r) {
    $counts[$r['fecha']] = (int)$r['total'];
}

// Navegación meses
$prev = (clone $firstDay)->modify('-1 month');
$next = (clone $firstDay)->modify('+1 month');
$prevY = (int)$prev->format('Y');
$prevM = (int)$prev->format('n');
$nextY = (int)$next->format('Y');
$nextM = (int)$next->format('n');

// Mes en texto en español
$meses = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
    7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];
$mesTexto = $meses[$month] . ' ' . $year;

// Día actual para resaltar
$hoyY = (int)$hoy->format('Y');
$hoyM = (int)$hoy->format('n');
$hoyD = (int)$hoy->format('j');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Calendario - <?= htmlspecialchars($nombre_dept) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root { --primary:#059669; --primary-light:#d1fae5; --text:#111827; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; background:#f3f4f6; color:var(--text); }
    
    .container { max-width:1200px; margin:1.5rem auto 2rem; background:#fff; border-radius:0.75rem; box-shadow:0 4px 15px rgba(0,0,0,0.05); overflow:hidden; }
    
    /* Header Principal */
    .header { display:flex; justify-content:space-between; align-items:center; padding:1.2rem 1.5rem; background: #003366; color: white; }
    .header-title { display:flex; align-items:center; gap:0.5rem; font-size:1.2rem; font-weight:600; }
    .header-title span.icon { font-size: 1.5rem; }
    .header-right a { padding:0.5rem 1rem; border-radius:999px; font-size:0.9rem; font-weight: bold; text-decoration:none; color:#003366; background:#fff; transition: 0.2s; }
    .header-right a:hover { background:#e2e8f0; }

    /* Barra de Filtro */
    .filter-bar { background: #f8fafc; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
    .filter-bar select { padding: 8px 12px; font-size: 1rem; border-radius: 5px; border: 1px solid #cbd5e1; min-width: 250px; font-weight: 600; color: #334155; }

    /* Controles de Calendario */
    .calendar-header { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.5rem; background: white; }
    .calendar-header-title { font-weight:700; font-size:1.3rem; color: #1e293b; text-transform: uppercase;}
    .calendar-nav button { padding:0.5rem 1rem; font-size:0.9rem; font-weight: bold; border-radius:6px; border:1px solid #cbd5e1; background:#f1f5f9; cursor:pointer; color: #475569; transition: 0.2s;}
    .calendar-nav button:hover { background:#e2e8f0; color: #0f172a;}

    /* Tabla Calendario */
    table.calendar { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.calendar thead th { text-align:center; padding:0.8rem 0; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; background:#f8fafc; font-size:0.9rem; color: #64748b; text-transform: uppercase; }
    table.calendar tbody td { height:100px; border-bottom:1px solid #f1f5f9; border-right:1px solid #f1f5f9; padding:0.5rem; vertical-align:top; font-size:0.9rem; position:relative; background:#ffffff; transition: 0.2s; }
    table.calendar tbody td:hover { background: #f8fafc; }
    table.calendar tbody tr td:first-child { border-left:1px solid #f1f5f9; }
    
    .day-number { font-weight:700; color: #94a3b8; }
    .day-count { position:absolute; bottom:0.5rem; right:0.5rem; font-size:1rem; font-weight: bold; background:var(--primary-light); color:var(--primary); padding:0.2rem 0.6rem; border-radius:6px; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
    .day-current { background:#eff6ff !important; border:2px solid #3b82f6 !important; }
    .day-current .day-number { color: #3b82f6; }
    .day-empty { background:#f9fafb !important; }

    .legend { padding:1rem 1.5rem; font-size:0.85rem; color:#64748b; background: white; border-top: 1px solid #e2e8f0; }
    .legend span.badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:4px; font-size:0.8rem; font-weight: bold; margin-right:0.4rem; }
    .badge-actual { background:#eff6ff; color:#3b82f6; border: 1px solid #bfdbfe; }
    .badge-numero { background:var(--primary-light); color:var(--primary); }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-title">
            <span class="icon">📅</span>
            <span>Atenciones por Día - <?= $esSuperAdmin ? 'Global' : htmlspecialchars($nombre_dept) ?></span>
        </div>
        <div class="header-right">
            <a href="dashboard.php">← Volver al Dashboard</a>
        </div>
    </div>

    <?php if ($esSuperAdmin): ?>
    <div class="filter-bar">
        <strong>🏢 Seleccionar Oficina: </strong>
        <form method="get" style="margin:0; display:flex; gap:10px;">
            <input type="hidden" name="y" value="<?= $year ?>">
            <input type="hidden" name="m" value="<?= $month ?>">
            <select name="dept" onchange="this.form.submit()">
                <option value="ALL" <?= $filtro_dept === 'ALL' ? 'selected' : '' ?>>🌐 Todas las Oficinas (Municipalidad Completa)</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $filtro_dept == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <div class="calendar-header">
        <div class="calendar-nav">
            <form method="get" style="display:inline;">
                <input type="hidden" name="y" value="<?= $prevY ?>">
                <input type="hidden" name="m" value="<?= $prevM ?>">
                <input type="hidden" name="dept" value="<?= htmlspecialchars($filtro_dept) ?>">
                <button type="submit">← Mes Anterior</button>
            </form>
        </div>
        <div class="calendar-header-title"><?= htmlspecialchars($mesTexto, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="calendar-nav">
            <form method="get" style="display:inline;">
                <input type="hidden" name="y" value="<?= $nextY ?>">
                <input type="hidden" name="m" value="<?= $nextM ?>">
                <input type="hidden" name="dept" value="<?= htmlspecialchars($filtro_dept) ?>">
                <button type="submit">Mes Siguiente →</button>
            </form>
        </div>
    </div>

    <table class="calendar">
        <thead>
            <tr>
                <th>Lunes</th>
                <th>Martes</th>
                <th>Miércoles</th>
                <th>Jueves</th>
                <th>Viernes</th>
                <th>Sábado</th>
                <th>Domingo</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $day = 1;
        for ($week = 0; $week < 6; $week++) {
            echo '<tr>';
            for ($dow = 1; $dow <= 7; $dow++) {
                if ($week === 0 && $dow < $firstWeekday) {
                    echo '<td class="day-empty"></td>';
                } elseif ($day > $daysInMonth) {
                    echo '<td class="day-empty"></td>';
                } else {
                    $fechaStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $esHoy = ($year === $hoyY && $month === $hoyM && $day === $hoyD);
                    $classes = $esHoy ? 'day-current' : '';
                    echo '<td class="'. $classes .'">';
                    echo '<div class="day-number">'. $day .'</div>';
                    if (isset($counts[$fechaStr]) && $counts[$fechaStr] > 0) {
                        echo '<div class="day-count">'. $counts[$fechaStr] .'</div>';
                    }
                    echo '</td>';
                    $day++;
                }
            }
            echo '</tr>';
            // Si ya terminó el mes, no dibujar más semanas vacías
            if ($day > $daysInMonth) break; 
        }
        ?>
        </tbody>
    </table>

    <div class="legend">
        <span class="badge badge-actual">Día Actual</span> Resaltado en azul.&nbsp; &nbsp;
        <span class="badge badge-numero">Cantidad</span> Total de tickets (Normales y Preferenciales) atendidos ese día.
    </div>
</div>

</body>
</html>