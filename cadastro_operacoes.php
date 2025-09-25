<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
// Apenas Super Administrador tem acesso a esta página
if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_operacao'])) {
    $nome_operacao = htmlspecialchars(trim($_POST['nome_operacao']));

    if (!empty($nome_operacao)) {
        $stmt_check = $conn->prepare("SELECT id FROM tipos_operacao WHERE nome = ?");
        $stmt_check->bind_param("s", $nome_operacao);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO tipos_operacao (nome) VALUES (?)");
            $stmt_insert->bind_param("s", $nome_operacao);
            if ($stmt_insert->execute()) {
                $mensagem_status = "<div class='success-message-box'>Tipo de operação cadastrado com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar: " . htmlspecialchars($stmt_insert->error) . "</div>";
            }
            $stmt_insert->close();
        } else {
            $mensagem_status = "<div class='error-message-box'>Este tipo de operação já existe.</div>";
        }
        $stmt_check->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>O nome da operação não pode ser vazio.</div>";
    }
}

// Processa a exclusão
if ($isSuperAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Verifica se a operação está sendo usada em alguma missão antes de excluir
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM missoes WHERE operacao_id = ?");
    $stmt_check->bind_param("i", $delete_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        $mensagem_status = "<div class='error-message-box'>Não é possível excluir esta operação, pois ela está vinculada a missões.</div>";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM tipos_operacao WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);

        if ($stmt_delete->execute()) {
            $mensagem_status = "<div class='success-message-box'>Operação excluída com sucesso!</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao excluir operação: " . htmlspecialchars($stmt_delete->error) . "</div>";
        }
        $stmt_delete->close();
    }
}

// Busca os tipos de operação
$tipos_operacao = [];
$sql_operacoes = "SELECT id, nome FROM tipos_operacao ORDER BY nome ASC";
$result_operacoes = $conn->query($sql_operacoes);
if ($result_operacoes->num_rows > 0) {
    while($row = $result_operacoes->fetch_assoc()) {
        $tipos_operacao[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Cadastro de Tipos de Operação</h1>
    <p>Os tipos aqui cadastrados aparecerão como opções na tela de registro de missão.</p>

    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 40px;">
        <h2>Adicionar Novo Tipo</h2>
        <form action="cadastro_operacoes.php" method="POST">
            <div class="form-grid" style="grid-template-columns: 1fr auto;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="nome_operacao">Nome do Tipo de Operação:</label>
                    <input type="text" id="nome_operacao" name="nome_operacao" placeholder="Ex: Busca e Salvamento, Incêndio Florestal" required>
                </div>
                <div class="form-actions" style="padding-top: 15px;">
                     <button type="submit" name="cadastrar_operacao">Salvar Tipo</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h2>Tipos Cadastrados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Nome</th>
                    <th style="width: 150px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tipos_operacao)): ?>
                    <?php foreach ($tipos_operacao as $tipo): ?>
                        <tr>
                            <td style="text-align: left;"><?php echo htmlspecialchars($tipo['nome']); ?></td>
                            <td class="action-buttons">
                                <a href="cadastro_operacoes.php?delete_id=<?php echo $tipo['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este tipo de operação?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">Nenhum tipo de operação cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>