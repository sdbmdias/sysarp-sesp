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

// ====================================================================================================
// *** INÍCIO DA SEÇÃO CORRIGIDA: Caminho do arquivo JSON ajustado ***
// ====================================================================================================
$forcas_config = [];
$config_error = '';
// A linha abaixo foi corrigida para incluir a pasta 'includes' no caminho.
$config_file_path = __DIR__ . '/includes/config_forcas.json'; 

if (file_exists($config_file_path)) {
    $json_content = file_get_contents($config_file_path);
    $decoded_json = json_decode($json_content, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $forcas_config = $decoded_json;
    } else {
        $config_error = 'Erro: O arquivo config_forcas.json está malformado e não pôde ser lido.';
    }
} else {
    $config_error = 'Aviso: O arquivo de configuração config_forcas.json não foi encontrado.';
}
// ====================================================================================================
// *** FIM DA SEÇÃO CORRIGIDA ***
// ====================================================================================================


// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_operacao'])) {
    $nome_operacao = htmlspecialchars(trim($_POST['nome_operacao']));
    $forcas_selecionadas = isset($_POST['forcas']) ? $_POST['forcas'] : [];

    if (!empty($nome_operacao)) {
        $stmt_check = $conn->prepare("SELECT id FROM tipos_operacao WHERE nome = ?");
        $stmt_check->bind_param("s", $nome_operacao);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $conn->begin_transaction();
            try {
                $stmt_insert = $conn->prepare("INSERT INTO tipos_operacao (nome) VALUES (?)");
                $stmt_insert->bind_param("s", $nome_operacao);
                $stmt_insert->execute();
                $operacao_id = $conn->insert_id;
                $stmt_insert->close();

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

// Lógica de exclusão (sem alterações)
if ($isSuperAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_get_nome = $conn->prepare("SELECT nome FROM tipos_operacao WHERE id = ?");
    $stmt_get_nome->bind_param("i", $delete_id);
    $stmt_get_nome->execute();
    $result_get_nome = $stmt_get_nome->get_result();
    
    if ($result_get_nome->num_rows > 0) {
        $operacao = $result_get_nome->fetch_assoc();
        $nome_operacao_para_verificar = $operacao['nome'];
        $stmt_check_uso = $conn->prepare("SELECT COUNT(*) as total FROM missoes WHERE descricao_operacao = ?");
        $stmt_check_uso->bind_param("s", $nome_operacao_para_verificar);
        $stmt_check_uso->execute();
        $uso_count = $stmt_check_uso->get_result()->fetch_assoc()['total'];
        $stmt_check_uso->close();

        if ($uso_count > 0) {
            $mensagem_status = "<div class='error-message-box'>Não é possível excluir esta operação, pois ela já está vinculada a " . $uso_count . " missão(ões).</div>";
        } else {
            $stmt_delete = $conn->prepare("DELETE FROM tipos_operacao WHERE id = ?");
            $stmt_delete->bind_param("i", $delete_id);
            if ($stmt_delete->execute()) {
                $mensagem_status = "<div class='success-message-box'>Tipo de operação excluído com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao excluir o tipo de operação.</div>";
            }
            $stmt_delete->close();
        }
    }
    $stmt_get_nome->close();
}

// Busca os tipos de operação para a lista (sem alterações)
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
<style>
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 700; color: #fff; white-space: nowrap; vertical-align: middle; }
.badge i { margin-right: 4px; }
/* Estilos para o novo layout de checkboxes */
.checkbox-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 10px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; }
.checkbox-item { display: flex; align-items: center; }
.checkbox-item input[type="checkbox"] { margin-right: 10px; width: 1.2em; height: 1.2em; }
.checkbox-item label { 
    display: block;
    width: 100%;
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #ced4da;
    background-color: #fff;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}
/* Feedback visual para item selecionado */
.checkbox-item input[type="checkbox"]:checked + label {
    background-color: #e0f7ff;
    color: #0056b3;
    font-weight: 600;
    border-color: #007bff;
}
</style>

<div class="main-content">
    <h1>Cadastro de Tipos de Operação</h1>
    <p>Os tipos aqui cadastrados aparecerão como opções na tela de registro de missão.</p>

    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 40px;">
        <h2>Adicionar Novo Tipo</h2>
        <form action="cadastro_operacoes.php" method="POST">
            <div class="form-grid" style="grid-template-columns: 1fr; gap: 20px;">
                <div class="form-group">
                    <label for="nome_operacao">Nome do Tipo de Operação:</label>
                    <input type="text" id="nome_operacao" name="nome_operacao" placeholder="Ex: Busca e Salvamento" required>
                </div>
                <div class="form-group">
                    <label>Atribuir a Forças Específicas:</label>
                    
                    <?php if (!empty($config_error)): ?>
                        <div class="error-message-box"><?php echo $config_error; ?></div>
                    <?php elseif (!empty($forcas_config)): ?>
                        <div class="checkbox-container">
                            <?php foreach ($forcas_config as $key => $config): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="forcas[]" value="<?php echo htmlspecialchars($key); ?>" id="forca_<?php echo htmlspecialchars($key); ?>">
                                    <label for="forca_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($config['nome']); ?> (<?php echo htmlspecialchars($key); ?>)</label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small>Selecione as forças desejadas. Se nenhuma for selecionada, será uma operação "Geral", disponível para todos.</small>
                    <?php else: ?>
                        <p>Nenhuma força de segurança encontrada na configuração.</p>
                    <?php endif; ?>

                </div>
            </div>
            <div class="form-actions" style="justify-content: flex-end; margin-top: 20px;">
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
                    <th style-="width: 40%;">Forças de Segurança Associadas</th>
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

<?php
require_once 'includes/footer.php';
?>