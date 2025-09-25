<?php
// 1. INCLUI O CABEÇALHO E AS VERIFICAÇÕES PADRÃO
require_once 'includes/header.php';

// Apenas administradores podem ver esta página, então adicionamos uma verificação extra.
if (!$isSuperAdmin && !$isAdmin) {
    // Redireciona para o dashboard se não for admin
    header("Location: dashboard.php");
    exit();
}

// 2. LÓGICA ESPECÍFICA DA PÁGINA DE ALERTAS
$alertas_proximos = [];
$alertas_vencidos = [];

// Busca todas as aeronaves para verificar a validade do SISANT
$sql = "SELECT prefixo, modelo, validade_sisant FROM aeronaves ORDER BY validade_sisant ASC";
$resultado = $conn->query($sql);

$hoje = new DateTime();
$hoje->setTime(0, 0, 0); // Zera o tempo para comparar apenas as datas

if ($resultado && $resultado->num_rows > 0) {
    while($aeronave = $resultado->fetch_assoc()) {
        if (!empty($aeronave['validade_sisant'])) {
            $validade_data = new DateTime($aeronave['validade_sisant']);
            
            if ($validade_data < $hoje) {
                // Se a data de validade for anterior a hoje, está vencido
                $intervalo = $hoje->diff($validade_data);
                $aeronave['dias'] = $intervalo->days;
                $alertas_vencidos[] = $aeronave;
            } else {
                // Se a data de validade for hoje ou no futuro
                $intervalo = $hoje->diff($validade_data);
                $dias_para_vencer = $intervalo->days;

                // Adiciona à lista de próximos se estiver a 15 dias ou menos de vencer
                if ($dias_para_vencer <= 15) {
                    $aeronave['dias'] = $dias_para_vencer;
                    $alertas_proximos[] = $aeronave;
                }
            }
        }
    }
}
?>

<style>
/* Estilos replicados do dashboard.php para consistência */
.dashboard-header { margin-bottom: 30px; }
.welcome-message { font-size: 1.1em; color: #555; margin-top: -15px; }
.dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
.card { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,.05); display: flex; align-items: center; gap: 20px; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,.08); }
.card-icon { font-size: 28px; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.card-content h2 { margin: 0 0 5px 0; font-size: 1em; color: #555; font-weight: 600; }
.card-content p { margin: 0; font-size: 2.2em; font-weight: 700; color: #2c3e50; }
.recent-missions h2 { display: flex; align-items: center; gap: 10px; color: #2c3e50; margin-bottom: 15px; }
@media (max-width: 768px) {
    .dashboard-cards { grid-template-columns: 1fr; }
    .card { flex-direction: column; text-align: center; }
}

/* Estilos específicos para a página de Alertas, baseados na lógica de cards */
.alerts-section h2 { 
    font-size: 1.5em; 
    color: #2c3e50; 
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alerts-section h2 i {
    font-size: 1em;
}

.alert-card {
    display: flex;
    align-items: center;
    gap: 20px;
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    margin-bottom: 15px;
    border-left: 5px solid;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.alert-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,.08);
}
.alert-card.proximo { border-color: #ffc107; }
.alert-card.vencido { border-color: #dc3545; }
.alert-card-icon {
    font-size: 24px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.alert-card.proximo .alert-card-icon { background-color: #ffc107; }
.alert-card.vencido .alert-card-icon { background-color: #dc3545; }

.alert-details {
    flex-grow: 1;
    font-size: 1em;
    color: #555;
}
.alert-details strong {
    color: #2c3e50;
}

.no-alerts {
    text-align: center;
    color: #888;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
}
</style>

<div class="main-content">
    <div class="dashboard-header">
        <h1>Alertas de Vencimento do SISANT</h1>
    </div>

    <div class="alerts-section">
        <h2><i class="fas fa-exclamation-triangle"></i> Vencimentos Próximos (Próximos 15 dias)</h2>
        <?php if (!empty($alertas_proximos)): ?>
            <?php foreach ($alertas_proximos as $alerta): ?>
                <div class="alert-card proximo">
                    <div class="alert-card-icon"><i class="fas fa-clock"></i></div>
                    <div class="alert-details">
                        <strong>Aeronave:</strong> <?php echo htmlspecialchars($alerta['prefixo']) . " (" . htmlspecialchars($alerta['modelo']) . ")"; ?><br>
                        O SISANT vence em <strong><?php echo $alerta['dias']; ?> dia(s)</strong>, na data de <?php echo date("d/m/Y", strtotime($alerta['validade_sisant'])); ?>.
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-alerts">Nenhum SISANT com vencimento nos próximos 15 dias.</div>
        <?php endif; ?>
    </div>

    <div class="alerts-section" style="margin-top: 40px;">
        <h2><i class="fas fa-times-circle"></i> Documentos Vencidos</h2>
        <?php if (!empty($alertas_vencidos)): ?>
            <?php foreach ($alertas_vencidos as $alerta): ?>
                <div class="alert-card vencido">
                    <div class="alert-card-icon"><i class="fas fa-calendar-times"></i></div>
                    <div class="alert-details">
                        <strong>Aeronave:</strong> <?php echo htmlspecialchars($alerta['prefixo']) . " (" . htmlspecialchars($alerta['modelo']) . ")"; ?><br>
                        O SISANT está <strong>vencido há <?php echo $alerta['dias']; ?> dia(s)</strong>. A data de validade era <?php echo date("d/m/Y", strtotime($alerta['validade_sisant'])); ?>.
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-alerts">Nenhuma aeronave com SISANT vencido.</div>
        <?php endif; ?>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>