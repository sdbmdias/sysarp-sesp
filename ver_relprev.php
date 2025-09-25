<?php
require_once 'includes/header.php';

if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

$relprev_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($relprev_id <= 0) {
    header("Location: listar_relprev.php");
    exit();
}

// Busca os detalhes do registro
$stmt = $conn->prepare("SELECT * FROM relprev WHERE id = ?");
$stmt->bind_param("i", $relprev_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $relprev = $result->fetch_assoc();
} else {
    // Se não encontrar, volta para a lista
    header("Location: listar_relprev.php");
    exit();
}
$stmt->close();
?>

<style>
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .detail-item { background-color: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #3498db; }
    .detail-item strong { display: block; margin-bottom: 8px; color: #555; font-size: 0.9em; }
    .detail-item p { margin: 0; font-size: 1.1em; color: #333; white-space: pre-wrap; word-wrap: break-word; }
    @media (max-width: 768px) { .details-grid { grid-template-columns: 1fr; } }
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Detalhes do RELPREV #<?php echo $relprev['id']; ?></h1>
        <a href="listar_relprev.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Lista</a>
    </div>

    <div class="form-container">
        <div class="details-grid">
            <div class="detail-item"><strong>Data de Envio:</strong><p><?php echo date("d/m/Y H:i:s", strtotime($relprev['data_envio'])); ?></p></div>
            <div class="detail-item"><strong>Tipo de Relator:</strong><p><?php echo htmlspecialchars($relprev['relator_tipo']); ?></p></div>
            <div class="detail-item"><strong>Data da Ocorrência:</strong><p><?php echo date("d/m/Y", strtotime($relprev['data_ocorrencia'])); ?></p></div>
            <div class="detail-item"><strong>Hora da Ocorrência:</strong><p><?php echo htmlspecialchars($relprev['hora_ocorrencia']); ?></p></div>
            <div class="detail-item"><strong>Email para Contato:</strong><p><?php echo !empty($relprev['email_contato']) ? htmlspecialchars($relprev['email_contato']) : 'Não informado'; ?></p></div>
            <div class="detail-item"><strong>Telefone para Contato:</strong><p><?php echo !empty($relprev['telefone_contato']) ? htmlspecialchars($relprev['telefone_contato']) : 'Não informado'; ?></p></div>
        </div>
        
        <div class="detail-item" style="margin-top: 20px;">
            <strong>Pessoal e/ou Aeronave Envolvida:</strong>
            <p><?php echo !empty($relprev['envolvidos']) ? htmlspecialchars($relprev['envolvidos']) : 'Não informado'; ?></p>
        </div>

        <div class="detail-item" style="margin-top: 20px;">
            <strong>Descrição da Situação:</strong>
            <p><?php echo htmlspecialchars($relprev['situacao']); ?></p>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>