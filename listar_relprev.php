<?php
require_once 'includes/header.php';

if (!$isAdmin && !$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

$mensagem_status = "";

// Lógica de exclusão
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM relprev WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Registro RELPREV excluído com sucesso.</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir o registro.</div>";
    }
    $stmt->close();
}

// Busca todos os registros
$relprevs = [];
$sql = "SELECT id, data_envio, relator_tipo, data_ocorrencia, situacao FROM relprev ORDER BY data_envio DESC";
$result = $conn->query($sql);
if ($result) {
    while($row = $result->fetch_assoc()) {
        $relprevs[] = $row;
    }
}
?>

<style>
@media (max-width: 768px) {
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
}
</style>

<div class="main-content">
    <h1>Registros de Prevenção Recebidos</h1>

    <?php echo $mensagem_status; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data de Envio</th>
                    <th>Tipo de Relator</th>
                    <th>Data da Ocorrência</th>
                    <th>Situação (Resumo)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($relprevs)): ?>
                    <?php foreach ($relprevs as $relprev): ?>
                        <tr>
                            <td><?php echo date("d/m/Y H:i", strtotime($relprev['data_envio'])); ?></td>
                            <td><?php echo htmlspecialchars($relprev['relator_tipo']); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($relprev['data_ocorrencia'])); ?></td>
                            <td style="text-align: left;" title="<?php echo htmlspecialchars($relprev['situacao']); ?>">
                                <?php echo htmlspecialchars(substr($relprev['situacao'], 0, 100)) . (strlen($relprev['situacao']) > 100 ? '...' : ''); ?>
                            </td>
                            <td class="action-buttons">
                                <a href="ver_relprev.php?id=<?php echo $relprev['id']; ?>" class="edit-btn">Ver Detalhes</a>
                                <a href="listar_relprev.php?delete_id=<?php echo $relprev['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este registro?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Nenhum Registro de Prevenção encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>