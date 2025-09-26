<?php
require_once 'includes/header.php';

if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

$mensagem_status = "";
$unidade_para_editar = null;

// --- LÓGICA DE PROCESSAMENTO DO FORMULÁRIO (ADICIONAR/EDITAR) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_unidade'])) {
    $id_unidade = isset($_POST['id_unidade']) ? intval($_POST['id_unidade']) : 0;
    $forca_sigla = htmlspecialchars($_POST['forca_sigla']);
    $nome_unidade = htmlspecialchars(trim($_POST['nome_unidade']));
    $unidade_pai_id = !empty($_POST['unidade_pai_id']) ? intval($_POST['unidade_pai_id']) : NULL;

    if (!empty($forca_sigla) && !empty($nome_unidade)) {
        if ($id_unidade > 0) { // ATUALIZAR
            $stmt = $conn->prepare("UPDATE unidades SET forca_sigla = ?, nome_unidade = ?, unidade_pai_id = ? WHERE id = ?");
            $stmt->bind_param("ssii", $forca_sigla, $nome_unidade, $unidade_pai_id, $id_unidade);
            if ($stmt->execute()) {
                $mensagem_status = "<div class='success-message-box'>Unidade atualizada com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao atualizar a unidade.</div>";
            }
            $stmt->close();
        } else { // INSERIR
            $stmt = $conn->prepare("INSERT INTO unidades (forca_sigla, nome_unidade, unidade_pai_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $forca_sigla, $nome_unidade, $unidade_pai_id);
            if ($stmt->execute()) {
                $mensagem_status = "<div class='success-message-box'>Unidade cadastrada com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar a unidade.</div>";
            }
            $stmt->close();
        }
    } else {
        $mensagem_status = "<div class='error-message-box'>Força de Segurança e Nome da Unidade são obrigatórios.</div>";
    }
}

// --- LÓGICA DE EXCLUSÃO ---
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // 1. Verifica se a unidade tem sub-unidades (filhos)
    $stmt_check_filhos = $conn->prepare("SELECT COUNT(*) as total FROM unidades WHERE unidade_pai_id = ?");
    $stmt_check_filhos->bind_param("i", $delete_id);
    $stmt_check_filhos->execute();
    $filhos_count = $stmt_check_filhos->get_result()->fetch_assoc()['total'];
    $stmt_check_filhos->close();

    if ($filhos_count > 0) {
        $mensagem_status = "<div class='error-message-box'>Não é possível excluir esta unidade, pois ela possui subunidades vinculadas.</div>";
    } else {
        // 2. (Opcional, mas recomendado) Verifica se a unidade está em uso em aeronaves, pilotos, etc.
        // Esta verificação pode ser expandida conforme a necessidade.
        
        $stmt_delete = $conn->prepare("DELETE FROM unidades WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        if ($stmt_delete->execute()) {
            $mensagem_status = "<div class='success-message-box'>Unidade excluída com sucesso!</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao excluir a unidade.</div>";
        }
        $stmt_delete->close();
    }
}

// --- LÓGICA PARA CARREGAR DADOS PARA EDIÇÃO ---
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $stmt_edit = $conn->prepare("SELECT * FROM unidades WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($result_edit->num_rows > 0) {
        $unidade_para_editar = $result_edit->fetch_assoc();
    }
    $stmt_edit->close();
}


// --- LÓGICA PARA LISTAGEM ---
$unidades_list = $conn->query("
    SELECT u1.*, u2.nome_unidade AS nome_pai 
    FROM unidades u1 
    LEFT JOIN unidades u2 ON u1.unidade_pai_id = u2.id 
    ORDER BY u1.forca_sigla, u1.unidade_pai_id, u1.nome_unidade ASC
")->fetch_all(MYSQLI_ASSOC);

$unidades_pai_list = $conn->query("SELECT id, nome_unidade, forca_sigla FROM unidades WHERE unidade_pai_id IS NULL ORDER BY forca_sigla, nome_unidade")->fetch_all(MYSQLI_ASSOC);

?>

<div class="main-content">
    <h1>Gerenciar Unidades das Forças de Segurança</h1>
    <p>Aqui você pode adicionar, editar ou remover as unidades (CRBMs, OPMs, etc.) que são usadas nos formulários de cadastro.</p>
    
    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 30px;">
        <h2><?php echo $unidade_para_editar ? 'Editar Unidade' : 'Adicionar Nova Unidade'; ?></h2>
        <form action="gerenciar_unidades.php" method="POST">
            <input type="hidden" name="id_unidade" value="<?php echo $unidade_para_editar['id'] ?? '0'; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="forca_sigla">Força de Segurança:</label>
                    <select id="forca_sigla" name="forca_sigla" required>
                        <option value="">Selecione...</option>
                        <?php foreach($config_forcas as $sigla => $config): ?>
                            <option value="<?php echo htmlspecialchars($sigla); ?>" <?php echo (isset($unidade_para_editar) && $unidade_para_editar['forca_sigla'] == $sigla) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($config['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="unidade_pai_id">Unidade Superior (Pai):</label>
                    <select id="unidade_pai_id" name="unidade_pai_id">
                        <option value="">Nenhuma (Unidade de Nível Superior)</option>
                        <?php // Este campo será populado dinamicamente via JavaScript ?>
                    </select>
                    <small>Selecione uma unidade superior para criar uma subunidade (ex: OBM dentro de um CRBM).</small>
                </div>
                 <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="nome_unidade">Nome da Unidade:</label>
                    <input type="text" id="nome_unidade" name="nome_unidade" value="<?php echo htmlspecialchars($unidade_para_editar['nome_unidade'] ?? ''); ?>" placeholder="Ex: 1º BBM ou Seção de Operações" required>
                </div>
            </div>
            <div class="form-actions">
                <a href="gerenciar_unidades.php" class="button-secondary" style="text-decoration: none; padding: 12px 25px;">Cancelar</a>
                <button type="submit" name="salvar_unidade"><?php echo $unidade_para_editar ? 'Atualizar Unidade' : 'Salvar Unidade'; ?></button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h2>Unidades Cadastradas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Força</th>
                    <th>Unidade Superior (Pai)</th>
                    <th>Nome da Unidade</th>
                    <th style="width: 200px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($unidades_list)): ?>
                    <?php foreach ($unidades_list as $unidade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($unidade['forca_sigla']); ?></td>
                            <td><?php echo htmlspecialchars($unidade['nome_pai'] ?? 'N/A (Nível Superior)'); ?></td>
                            <td><?php echo htmlspecialchars($unidade['nome_unidade']); ?></td>
                            <td class="action-buttons">
                                <a href="gerenciar_unidades.php?edit_id=<?php echo $unidade['id']; ?>" class="edit-btn">Editar</a>
                                <a href="gerenciar_unidades.php?delete_id=<?php echo $unidade['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta unidade? A ação não será permitida se houver subunidades ou outros registros vinculados a ela.');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Nenhuma unidade cadastrada. Execute o script de migração.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const forcaSelect = document.getElementById('forca_sigla');
    const paiSelect = document.getElementById('unidade_pai_id');
    const todasUnidadesPai = <?php echo json_encode($unidades_pai_list); ?>;
    const unidadeEditando = <?php echo json_encode($unidade_para_editar); ?>;

    function atualizarUnidadesPai() {
        const forcaSelecionada = forcaSelect.value;
        const valorSalvo = (unidadeEditando && unidadeEditando.forca_sigla === forcaSelecionada) ? unidadeEditando.unidade_pai_id : null;
        
        paiSelect.innerHTML = '<option value="">Nenhuma (Unidade de Nível Superior)</option>'; // Reseta

        if (forcaSelecionada) {
            const unidadesFiltradas = todasUnidadesPai.filter(unidade => unidade.forca_sigla === forcaSelecionada);
            unidadesFiltradas.forEach(unidade => {
                const option = document.createElement('option');
                option.value = unidade.id;
                option.textContent = unidade.nome_unidade;
                if(unidade.id == valorSalvo) {
                    option.selected = true;
                }
                paiSelect.appendChild(option);
            });
        }
    }

    forcaSelect.addEventListener('change', atualizarUnidadesPai);

    // Se estiver editando, chama a função para popular e selecionar o pai correto na carga inicial
    if (unidadeEditando) {
        atualizarUnidadesPai();
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>