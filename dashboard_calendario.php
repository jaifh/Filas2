<?php
// dashboard_calendario.php
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php'; // asegúrate que aquí tengas timezone America/Santiago

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Rango de fechas del mes (para consultar tickets_logs)
$startDate = $firstDay->format('Y-m-d');
$endDate   = (clone $firstDay)->modify('last day of this month')->format('Y-m-d');

// Obtener conteo de tickets_logs por día del mes
$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT fecha, COUNT(*) AS total
    FROM tickets_logs
    WHERE fecha BETWEEN ? AND ?
    GROUP BY fecha
");
$stmt->execute([$startDate, $endDate]);
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

// Día actual para resaltar (si coincide con este mes)
$hoyY = (int)$hoy->format('Y');
$hoyM = (int)$hoy->format('n');
$hoyD = (int)$hoy->format('j');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informe de atenciones por día</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    * { box-sizing:border-box; }
    body {
        margin:0;
        font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
        background:#f3f4f6;
        color:#111827;
    }
    .container {
        max-width:1200px;
        margin:1.5rem auto 2rem;
        padding:0 1rem;
        background:#fff;
        border-radius:0.75rem;
        box-shadow:0 2px 8px rgba(0,0,0,0.06);
    }
    .header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:0.9rem 1rem;
        border-bottom:1px solid #e5e7eb;
    }
    .header-title {
        display:flex;
        align-items:center;
        gap:0.4rem;
        font-size:1.1rem;
        font-weight:600;
    }
    .header-title span.icon {
        width:1.6rem;
        height:1.6rem;
        border-radius:999px;
        background:#e5f3ff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:0.9rem;
        color:#1d4ed8;
    }
    .header-right a {
        padding:0.35rem 0.75rem;
        border-radius:999px;
        border:1px solid #d1d5db;
        font-size:0.85rem;
        text-decoration:none;
        color:#374151;
        background:#f9fafb;
    }
    .header-right a:hover {
        background:#e5e7eb;
    }

    .calendar-header {
        display:flex;
        justify-content:space-between;
        align-items:center;
        padding:0.8rem 1rem;
    }
    .calendar-header-title {
        font-weight:600;
        font-size:1.1rem;
    }
    .calendar-nav button {
        padding:0.35rem 0.8rem;
        font-size:0.85rem;
        border-radius:999px;
        border:1px solid #d1d5db;
        background:#f9fafb;
        cursor:pointer;
        margin-left:0.3rem;
    }
    .calendar-nav button:hover {
        background:#e5e7eb;
    }

    table.calendar {
        width:100%;
        border-collapse:collapse;
        table-layout:fixed;
    }
    table.calendar thead th {
        text-align:center;
        padding:0.6rem 0;
        border-top:1px solid #e5e7eb;
        border-bottom:1px solid #e5e7eb;
        background:#f9fafb;
        font-size:0.9rem;
    }
    table.calendar tbody td {
        height:90px;
        border-bottom:1px solid #f3f4f6;
        border-right:1px solid #f3f4f6;
        padding:0.3rem 0.4rem;
        vertical-align:top;
        font-size:0.85rem;
        position:relative;
        background:#ffffff;
    }
    table.calendar tbody tr td:first-child {
        border-left:1px solid #f3f4f6;
    }
    .day-number {
        font-weight:600;
        margin-bottom:0.3rem;
    }
    .day-count {
        position:absolute;
        bottom:0.3rem;
        right:0.3rem;
        font-size:0.75rem;
        background:#e5e7eb;
        color:#111827;
        padding:0.1rem 0.35rem;
        border-radius:999px;
    }
    .day-current {
        background:#e0f2f1;
        border:2px solid #26a69a;
    }
    .day-empty {
        background:#f9fafb;
    }

    .legend {
        padding:0.6rem 1rem 1rem;
        font-size:0.8rem;
        color:#4b5563;
    }
    .legend span.badge {
        display:inline-block;
        padding:0.1rem 0.45rem;
        border-radius:999px;
        font-size:0.75rem;
        margin-right:0.35rem;
    }
    .badge-actual {
        background:#bbf7d0;
        color:#166534;
    }
    .badge-numero {
        background:#e5e7eb;
        color:#111827;
    }
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-title">
            <span class="icon">📅</span>
            <span>Informe de atenciones por día</span>
        </div>
        <div class="header-right">
            <a href="dashboard.php">Volver</a>
        </div>
    </div>

    <div class="calendar-header">
        <div class="calendar-nav">
            <form method="get" style="display:inline;">
                <input type="hidden" name="y" value="<?= $prevY ?>">
                <input type="hidden" name="m" value="<?= $prevM ?>">
                <button type="submit">← Mes Ant.</button>
            </form>
        </div>
        <div class="calendar-header-title"><?= htmlspecialchars($mesTexto, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="calendar-nav">
            <form method="get" style="display:inline;">
                <input type="hidden" name="y" value="<?= $nextY ?>">
                <input type="hidden" name="m" value="<?= $nextM ?>">
                <button type="submit">Mes Sig. →</button>
            </form>
        </div>
    </div>

    <table class="calendar">
        <thead>
            <tr>
                <th>Lun</th>
                <th>Mar</th>
                <th>Mié</th>
                <th>Jue</th>
                <th>Vie</th>
                <th>Sáb</th>
                <th>Dom</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $day = 1;
        // 6 filas de semanas como en tu ejemplo
        for ($week = 0; $week < 6; $week++) {
            echo '<tr>';
            for ($dow = 1; $dow <= 7; $dow++) {
                if ($week === 0 && $dow < $firstWeekday) {
                    // antes del 1 del mes
                    echo '<td class="day-empty"></td>';
                } elseif ($day > $daysInMonth) {
                    // después del último día
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
        }
        ?>
        </tbody>
    </table>

    <div class="legend">
        <span class="badge badge-actual">Verde</span> Día actual.&nbsp;
        <span class="badge badge-numero">número</span> Total de atenciones del día (tickets en tickets_logs).
    </div>
</div>

</body>
</html>
