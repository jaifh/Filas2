<?php
// caja.php
require_once __DIR__ . '/auth.php';
requireLogin(['SUPER-ADMIN', 'ADMIN', 'CAJA']);

$pdo = getPDO();
$usuario_id = $_SESSION['id'];
$modulo_id = $_SESSION['modulo_id'] ?? null;
// El cajero solo puede operar en su departamento
$dept_id = $_SESSION['departamento_id']; 

if (!$modulo_id || !$dept_id) {
    die("Error: Seleccione un módulo y asegúrese de estar asignado a un Departamento.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $hoy = date('Y-m-d');
    $ahora = date('H:i:s');

    if ($accion === 'llamar_nuevo') {
        $tipo_llamado = $_POST['tipo'] ?? 'NORMAL';
        $pdo->beginTransaction();
        
        $stmtConf = $pdo->prepare('SELECT prefijo_cola, numero_inicial, prefijo_preferencial, numero_inicial_preferencial FROM departamentos WHERE id = ? FOR UPDATE');
        $stmtConf->execute([$dept_id]);
        $config = $stmtConf->fetch();
        
        $prefijo = ($tipo_llamado === 'PREFERENCIAL') ? $config['prefijo_preferencial'] : $config['prefijo_cola'];
        $inicio  = ($tipo_llamado === 'PREFERENCIAL') ? $config['numero_inicial_preferencial'] : $config['numero_inicial'];
        
        // Aislar la búsqueda al departamento actual
        $stmtMax = $pdo->prepare('SELECT MAX(numero) AS max_num FROM tickets WHERE fecha = ? AND prefijo = ? AND departamento_id = ?');
        $stmtMax->execute([$hoy, $prefijo, $dept_id]);
        $row = $stmtMax->fetch();
        
        $proximo_numero = ($row['max_num'] !== null) ? (int)$row['max_num'] + 1 : (int)$inicio;
        
        $stmtIns = $pdo->prepare('
            INSERT INTO tickets (numero, prefijo, tipo, estado, modulo_id, departamento_id, fecha, hora_creacion, hora_llamado) 
            VALUES (?, ?, ?, "LLAMADO", ?, ?, ?, ?, ?)
        ');
        $stmtIns->execute([$proximo_numero, $prefijo, $tipo_llamado, $modulo_id, $dept_id, $hoy, $ahora, $ahora]);
        
        $pdo->commit();
        header('Location: caja.php');
        exit;
    }

    // Acciones secundarias (Rellamar, Atender, Finalizar)
    if (in_array($accion, ['rellamar', 'atender', 'finalizar', 'ausente'])) {
        $ticket_id = $_POST['ticket_id'];
        
        if ($accion === 'rellamar') {
            $stmt = $pdo->prepare("UPDATE tickets SET hora_llamado = ? WHERE id = ? AND modulo_id = ?");
            $stmt->execute([$ahora, $ticket_id, $modulo_id]);
        } elseif ($accion === 'atender') {
            $stmt = $pdo->prepare("UPDATE tickets SET estado = 'ATENDIENDO' WHERE id = ? AND modulo_id = ?");
            $stmt->execute([$ticket_id, $modulo_id]);
        } else {
            $estado = ($accion === 'finalizar') ? 'FINALIZADO' : 'CANCELADO';
            $stmt = $pdo->prepare("UPDATE tickets SET estado = ?, hora_fin = ? WHERE id = ? AND modulo_id = ?");
            $stmt->execute([$estado, $ahora, $ticket_id, $modulo_id]);
        }
        header('Location: caja.php');
        exit;
    }
}

// OBTENER TICKET ACTUAL
$hoy = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM tickets WHERE fecha = ? AND modulo_id = ? AND estado IN ('LLAMADO', 'ATENDIENDO') LIMIT 1");
$stmt->execute([$hoy, $modulo_id]);
$ticket_actual = $stmt->fetch();

// INFO DEL MÓDULO Y DEPARTAMENTO
$stmtMod = $pdo->prepare("SELECT m.nombre as modulo, d.nombre as departamento FROM modulos m JOIN departamentos d ON m.departamento_id = d.id WHERE m.id = ?");
$stmtMod->execute([$modulo_id]);
$info = $stmtMod->fetch();

// ÚLTIMOS LLAMADOS DE ESTE DEPARTAMENTO
$stmtUlt = $pdo->prepare("SELECT MAX(numero) as max_num, tipo, prefijo FROM tickets WHERE fecha = ? AND departamento_id = ? GROUP BY tipo, prefijo");
$stmtUlt->execute([$hoy, $dept_id]);
$ultimos = $stmtUlt->fetchAll(PDO::FETCH_ASSOC);

$ultimo_normal = "Ninguno"; $ultimo_pref = "Ninguno";
foreach ($ultimos as $u) {
    if ($u['tipo'] === 'NORMAL') $ultimo_normal = $u['prefijo'] . '-' . str_pad($u['max_num'], 3, '0', STR_PAD_LEFT);
    if ($u['tipo'] === 'PREFERENCIAL') $ultimo_pref = $u['prefijo'] . '-' . str_pad($u['max_num'], 3, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel de Caja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: system-ui, sans-serif; background: #f0fdf4; margin: 0; padding: 20px; text-align: center; }
        .panel { background: white; max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .btn { padding: 15px; font-size: 1.1rem; border: none; border-radius: 8px; cursor: pointer; color: white; font-weight: bold; margin: 5px; flex: 1; transition: transform 0.1s; }
        .btn-normal { background: #2563eb; font-size: 1.3rem; padding: 25px 15px; }
        .btn-pref { background: #ca8a04; font-size: 1.3rem; padding: 25px 15px;}
        .btn-rellamar { background: #0ea5e9; }
        .btn-atender { background: #eab308; }
        .btn-finalizar { background: #16a34a; }
        .btn-ausente { background: #dc2626; }
        .ticket-box { border: 4px dashed #22c55e; padding: 20px; margin: 20px 0; border-radius: 10px; }
        .ticket-num { font-size: 5rem; font-weight: 900; color: #065f46; margin: 0; line-height: 1; }
        .stats { display: flex; justify-content: space-around; background: #e2e8f0; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem;}
        .botones-llamar { display: flex; gap: 15px; margin-top: 20px; }
    </style>
</head>
<body>
<div class="panel">
    <h4 style="color: #64748b; margin:0; text-transform: uppercase;"><?= htmlspecialchars($info['departamento']) ?></h4>
    <h2 style="color: #065f46; margin-top:0;">Mi Estación: <?= htmlspecialchars($info['modulo']) ?></h2>
    
    <div class="stats">
        <div>Último General: <br><strong style="font-size: 1.2rem; color: #1d4ed8;"><?= $ultimo_normal ?></strong></div>
        <div>Último Pref: <br><strong style="font-size: 1.2rem; color: #a16207;"><?= $ultimo_pref ?></strong></div>
    </div>

    <?php if (!$ticket_actual): ?>
        <p style="color: #64748b; font-size: 1.1rem;">Seleccione a quién llamar:</p>
        <div class="botones-llamar">
            <form method="post" style="flex: 1;"><input type="hidden" name="accion" value="llamar_nuevo"><input type="hidden" name="tipo" value="NORMAL"><button type="submit" class="btn btn-normal" style="width: 100%;">🧑‍💼 Siguiente<br>GENERAL</button></form>
            <form method="post" style="flex: 1;"><input type="hidden" name="accion" value="llamar_nuevo"><input type="hidden" name="tipo" value="PREFERENCIAL"><button type="submit" class="btn btn-pref" style="width: 100%;">🦽 Siguiente<br>PREFERENCIAL</button></form>
        </div>
    <?php else: ?>
        <?php 
            $codigo = $ticket_actual['prefijo'] . '-' . str_pad($ticket_actual['numero'], 3, '0', STR_PAD_LEFT);
            $es_pref = $ticket_actual['tipo'] === 'PREFERENCIAL';
        ?>
        <div class="ticket-box" style="<?= $es_pref ? 'border-color: #eab308;' : '' ?>">
            <span style="font-weight: bold; color: <?= $es_pref ? '#ca8a04' : '#64748b' ?>;">TICKET <?= $ticket_actual['tipo'] ?> ACTUAL</span>
            <p class="ticket-num"><?= $codigo ?></p>
            <p>Estado: <strong><?= $ticket_actual['estado'] ?></strong></p>
        </div>
        <form method="post" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px;">
            <input type="hidden" name="ticket_id" value="<?= $ticket_actual['id'] ?>">
            <?php if ($ticket_actual['estado'] === 'LLAMADO'): ?>
                <button type="submit" name="accion" value="rellamar" class="btn btn-rellamar" style="flex: 1 1 100%;">🔄 Re-llamar</button>
                <button type="submit" name="accion" value="atender" class="btn btn-atender">Empezar</button>
                <button type="submit" name="accion" value="ausente" class="btn btn-ausente">Ausente</button>
            <?php else: ?>
                <button type="submit" name="accion" value="finalizar" class="btn btn-finalizar" style="flex: 1 1 100%;">✅ Finalizar</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <div style="margin-top: 30px;"><a href="index.php" style="color: #64748b; text-decoration: none;">← Volver al Menú Principal</a></div>
</div>
</body>
</html>