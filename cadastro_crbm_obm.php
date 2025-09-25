<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
// Apenas Super Administrador tem acesso a esta página
if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

$mensagem_status = "";

// Lógica de cadastro ou exclusão (se necessário)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['crbm']) && isset($_POST['obm'])) {
        $crbm = htmlspecialchars($_POST['crbm']);
        $obm = htmlspecialchars($_POST['obm']);

        // Verifica se a combinação CRBM/OBM já existe
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM crbm_obm WHERE crbm = ? AND obm = ?");
        $stmt_check->bind_param("ss", $crbm, $obm);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $mensagem_status = "<div class='error-message-box'>Erro: A combinação CRBM e OBM já existe.</div>";
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO crbm_obm (crbm, obm) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $crbm, $obm);

            if ($stmt_insert->execute()) {
                $mensagem_status = "<div class='success-message-box'>CRBM/OBM cadastrado com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar CRBM/OBM.</div>";
            }
            $stmt_insert->close();
        }
    }
} elseif (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Verifica se existem pilotos ou aeronaves associados antes de excluir
    $stmt_check_pilotos = $conn->prepare("SELECT COUNT(*) FROM pilotos WHERE crbm_obm_id = ?");
    $stmt_check_pilotos->bind_param("i", $delete_id);
    $stmt_check_pilotos->execute();
    $stmt_check_pilotos->bind_result($piloto_count);
    $stmt_check_pilotos->fetch();
    $stmt_check_pilotos->close();

    $stmt_check_aeronaves = $conn->prepare("SELECT COUNT(*) FROM aeronaves WHERE crbm_obm_id = ?");
    $stmt_check_aeronaves->bind_param("i", $delete_id);
    $stmt_check_aeronaves->execute();
    $stmt_check_aeronaves->bind_result($aeronave_count);
    $stmt_check_aeronaves->fetch();
    $stmt_check_aeronaves->close();

    if ($piloto_count > 0 || $aeronave_count > 0) {
        $mensagem_status = "<div class='error-message-box'>Não é possível excluir, pois há pilotos ou aeronaves vinculados a esta lotação.</div>";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM crbm_obm WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        if ($stmt_delete->execute()) {
            $mensagem_status = "<div class='success-message-box'>CRBM/OBM excluído com sucesso!</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao excluir CRBM/OBM.</div>";
        }
        $stmt_delete->close();
    }
}

// Lógica para listar os CRBMs/OBMs existentes
$crbm_obm_list = [];
$sql = "SELECT id, crbm, obm FROM crbm_obm ORDER BY crbm, obm";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $crbm_obm_list[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Cadastro de CRBM / OBM</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <h2>Adicionar Novo CRBM/OBM</h2>
        <form action="cadastro_crbm_obm.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="crbm">CRBM:</label>
                    <input type="text" id="crbm" name="crbm" placeholder="Ex: 1º CRBM" required>
                </div>
                <div class="form-group">
                    <label for="obm">OBM:</label>
                    <input type="text" id="obm" name="obm" placeholder="Ex: 1º SGB" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Adicionar</button>
            </div>
        </form>
    </div>

    <div class="table-container" style="margin-top: 40px;">
        <h2>Lotações Cadastradas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>CRBM</th>
                    <th>OBM</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($crbm_obm_list)): ?>
                    <?php foreach ($crbm_obm_list as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(preg_replace('/^(\d+)\s*CRBM$/i', '$1º CRBM', $item['crbm'])); ?></td>
                            <td><?php echo htmlspecialchars($item['obm']); ?></td>
                            <td class="action-buttons">
                                <a href="cadastro_crbm_obm.php?delete_id=<?php echo $item['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta lotação?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">Nenhuma lotação cadastrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 3. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>