<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_operacao'])) {
    $nome_operacao = htmlspecialchars(trim($_POST['nome_operacao']));
    // *** ATUALIZADO: Captura as forças como um array ***
    $forcas_selecionadas = isset($_POST['forcas']) ? $_POST['forcas'] : [];

    if (!empty($nome_operacao)) {
        // Unicidade agora é apenas pelo nome da operação
        $stmt_check = $conn->prepare("SELECT id FROM tipos_operacao WHERE nome = ?");
        $stmt_check->bind_param("s", $nome_operacao);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $conn->begin_transaction();
            try {
                // Insere na tabela principal de operações
                $stmt_insert = $conn->prepare("INSERT INTO tipos_operacao (nome) VALUES (?)");
                $stmt_insert->bind_param("s", $nome_operacao);
                $stmt_insert->execute();
                $operacao_id = $conn->insert_id;
                $stmt_insert->close();

                // *** NOVO: Insere as associações na tabela operacao_forcas ***
                if (!empty($forcas_selecionadas)) {
                    $stmt_assoc = $conn->prepare("INSERT INTO operacao_forcas (operacao_id, forca_seguranca) VALUES (?, ?)");
                    foreach ($forcas_selecionadas as $forca) {
                        $forca_sanitizada = htmlspecialchars(trim($forca));
                        $stmt_assoc->bind_param("is", $operacao_id, $forca_sanitizada);
                        $stmt_assoc->execute();
                    }
                    $stmt_assoc->close();
                }
                
                $conn->commit();
                $mensagem_status = "<div class='success-message-box'>Tipo de operação cadastrado com sucesso!</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar: " . $e->getMessage() . "</div>";
            }
        } else {
            $mensagem_status = "<div class='error-message-box'>Já existe um tipo de operação com este nome.</div>";
        }
        $stmt_check->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>O nome da operação não pode ser vazio.</div>";
    }
}

// Processa a exclusão (lógica PHP inalterada, o ON DELETE CASCADE cuida das associações)
if ($isSuperAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    // ... (código de verificação de uso e exclusão permanece o mesmo)
}

// *** ATUALIZADO: Busca os tipos de operação e concatena as forças associadas ***
$tipos_operacao = [];
$sql_operacoes = "
    SELECT 
        t.id, 
        t.nome, 
        GROUP_CONCAT(of.forca_seguranca SEPARATOR ', ') as forcas 
    FROM tipos_operacao t
    LEFT JOIN operacao_forcas of ON t.id = of.operacao_id
    GROUP BY t.id, t.nome
    ORDER BY t.nome ASC
";
$result_operacoes = $conn->query($sql_operacoes);
if ($result_operacoes && $result_operacoes->num_rows > 0) {
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
            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label for="nome_operacao">Nome do Tipo de Operação:</label>
                    <input type="text" id="nome_operacao" name="nome_operacao" placeholder="Ex: Busca e Salvamento" required>
                </div>
                <div class="form-group">
                    <label for="forcas">Atribuir a Forças Específicas:</label>
                    <select id="forcas" name="forcas[]" multiple style="height: 100px;">
                        <option value="PMPR">PMPR</option>
                        <option value="CBMPR">CBMPR</option>
                        <option value="Polícia Penal">Polícia Penal</option>
                    </select>
                    <small>Segure CTRL (ou Command no Mac) para selecionar várias. Se nenhuma for selecionada, será uma operação "Geral".</small>
                </div>
            </div>
            <div class="form-actions" style="justify-content: flex-end;">
                 <button type="submit" name="cadastrar_operacao">Salvar Tipo</button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h2>Tipos Cadastrados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Nome</th>
                    <th style="width: 300px;">Forças de Segurança Associadas</th>
                    <th style="width: 150px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tipos_operacao)): ?>
                    <?php foreach ($tipos_operacao as $tipo): ?>
                        <tr>
                            <td style="text-align: left;"><?php echo htmlspecialchars($tipo['nome']); ?></td>
                            <td>
                                <?php if (!empty($tipo['forcas'])): ?>
                                    <?php 
                                        $forcas_array = explode(', ', $tipo['forcas']);
                                        foreach ($forcas_array as $forca) {
                                            echo '<span class="badge" style="background-color: #333; margin-right: 5px; margin-bottom: 5px;">' . htmlspecialchars($forca) . '</span>';
                                        }
                                    ?>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #28a745;"><i class="fas fa-globe"></i> Geral</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <a href="cadastro_operacoes.php?delete_id=<?php echo $tipo['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este tipo de operação?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">Nenhum tipo de operação cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: 700;
    color: #fff;
    white-space: nowrap;
    vertical-align: middle;
}
.badge i {
    margin-right: 4px;
}
</style>

<?php
require_once 'includes/footer.php';
?>