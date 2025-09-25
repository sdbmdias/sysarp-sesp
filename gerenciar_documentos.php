<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
// Apenas Super Administrador e Administrador têm acesso a esta página
if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA DA PÁGINA
$mensagem_status = "";
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

// Processa a exclusão do documento
if (($isSuperAdmin || $isAdmin) && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    $stmt_get = $conn->prepare("SELECT caminho_arquivo FROM documentos WHERE id = ?");
    $stmt_get->bind_param("i", $delete_id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if ($result_get->num_rows > 0) {
        $documento = $result_get->fetch_assoc();
        $caminho_arquivo = $documento['caminho_arquivo'];

        $conn->begin_transaction();
        $stmt_delete = $conn->prepare("DELETE FROM documentos WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);

        if ($stmt_delete->execute()) {
            if (file_exists($caminho_arquivo) && !is_dir($caminho_arquivo)) {
                if (unlink($caminho_arquivo)) {
                    $conn->commit();
                    $mensagem_status = "<div class='success-message-box'>Documento e arquivo físico excluídos com sucesso!</div>";
                } else {
                    $conn->rollback();
                    $mensagem_status = "<div class='error-message-box'>Erro: O registro do documento foi removido, mas falha ao excluir o arquivo físico. Verifique as permissões da pasta 'uploads'.</div>";
                }
            } else {
                $conn->commit();
                $mensagem_status = "<div class='success-message-box'>Registro do documento excluído com sucesso. O arquivo físico não foi encontrado no servidor.</div>";
            }
        } else {
            $conn->rollback();
            $mensagem_status = "<div class='error-message-box'>Erro ao excluir o registro do documento do banco de dados.</div>";
        }
        $stmt_delete->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>Documento não encontrado para exclusão.</div>";
    }
    $stmt_get->close();
}


// Busca todos os documentos para listagem, incluindo o prefixo da aeronave
$todos_documentos = [];
$sql_docs = "SELECT 
                d.id, d.nome_exibicao, d.caminho_arquivo, d.tipo_arquivo, d.data_upload, d.nome_arquivo_servidor,
                GROUP_CONCAT(DISTINCT ac.prefixo SEPARATOR ', ') AS aeronaves_associadas,
                GROUP_CONCAT(DISTINCT da.modelo_aeronave SEPARATOR '||') AS modelos_associados
             FROM documentos d
             LEFT JOIN documentos_associados da ON d.id = da.documento_id
             LEFT JOIN aeronaves ac ON da.aeronave_id = ac.id
             ";

if (!empty($search_term)) {
    $sql_docs .= " WHERE d.nome_exibicao LIKE ?";
}

$sql_docs .= " GROUP BY d.id ORDER BY d.nome_exibicao ASC";

$stmt_docs = $conn->prepare($sql_docs);

if (!empty($search_term)) {
    $like_search_term = "%" . $search_term . "%";
    $stmt_docs->bind_param("s", $like_search_term);
}

$stmt_docs->execute();
$result_docs = $stmt_docs->get_result();

if ($result_docs) {
    while($row = $result_docs->fetch_assoc()) {
        $todos_documentos[] = $row;
    }
}
$stmt_docs->close();

// Conta o número total de modelos de aeronave distintos
$total_modelos_aeronave = 0;
$result_total_modelos = $conn->query("SELECT COUNT(DISTINCT modelo) as total FROM fabricantes_modelos WHERE tipo = 'Aeronave'");
if ($result_total_modelos) {
    $total_modelos_aeronave = $result_total_modelos->fetch_assoc()['total'];
}


// Separa os documentos em categorias
$docs_globais = [];
$docs_de_modelo = [];
$docs_especificos = [];

foreach ($todos_documentos as $doc) {
    $num_modelos_associados = $doc['modelos_associados'] ? count(explode('||', $doc['modelos_associados'])) : 0;
    
    if ($doc['aeronaves_associadas']) {
        $docs_especificos[] = $doc;
    } elseif ($num_modelos_associados > 0 && $num_modelos_associados >= $total_modelos_aeronave && $total_modelos_aeronave > 0) {
        $docs_globais[] = $doc;
    } elseif ($num_modelos_associados > 0) {
        $docs_de_modelo[] = $doc;
    } else {
        $docs_de_modelo[] = $doc;
    }
}
?>

<div class="main-content">
    <h1>Gerenciar Documentos da Biblioteca</h1>
    <p style="margin-bottom: 20px;">Esta página lista todos os documentos do sistema. A exclusão de um item aqui é <strong>permanente</strong> e removerá o arquivo do servidor e todas as suas associações.</p>
    
    <div class="form-container" style="margin-bottom: 30px; padding: 20px;">
        <form action="gerenciar_documentos.php" method="GET">
            <div class="form-group" style="margin: 0;">
                <label for="q" style="font-size: 1.1em;">Buscar Documentos:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="q" name="q" placeholder="Digite o nome do documento..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow: 1;">
                    <button type="submit" class="action-btn" style="background-color: #007bff; color: white; border: none; cursor: pointer; padding: 10px 15px; border-radius: 5px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="gerenciar_documentos.php" class="action-btn" style="background-color: #6c757d; padding: 10px 15px; border-radius: 5px;">
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php echo $mensagem_status; ?>

    <div class="table-container" style="margin-bottom: 30px;">
        <h2><i class="fas fa-globe"></i> Documentos Globais (Associados a Todos os Modelos)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome do Documento</th>
                    <th class="actions-column">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($docs_globais)): ?>
                    <?php foreach ($docs_globais as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['nome_exibicao']); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="action-btn" style="background-color: #007bff;" download><i class="fas fa-download"></i> Baixar</a>
                                <a href="gerenciar_documentos.php?delete_id=<?php echo $doc['id']; ?>" class="action-btn" style="background-color:#dc3545;" onclick="return confirm('ATENÇÃO: Ação irreversível! Deseja excluir permanentemente este documento e todas as suas associações?');"><i class="fas fa-trash-alt"></i> Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">Nenhum documento global encontrado<?php echo !empty($search_term) ? ' para a busca "' . htmlspecialchars($search_term) . '"' : ''; ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="table-container" style="margin-bottom: 30px;">
        <h2><i class="fas fa-copy"></i> Documentos de Modelos Específicos</h2>
        <table class="data-table">
             <thead>
                <tr>
                    <th>Nome do Documento</th>
                    <th>Modelos Associados</th>
                    <th class="actions-column">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($docs_de_modelo)): ?>
                    <?php foreach ($docs_de_modelo as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['nome_exibicao']); ?></td>
                            <td><?php echo htmlspecialchars(str_replace('||', ', ', $doc['modelos_associados'])); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="action-btn" style="background-color: #007bff;" download><i class="fas fa-download"></i> Baixar</a>
                                <a href="gerenciar_documentos.php?delete_id=<?php echo $doc['id']; ?>" class="action-btn" style="background-color:#dc3545;" onclick="return confirm('ATENÇÃO: Ação irreversível! Deseja excluir permanentemente este documento e todas as suas associações?');"><i class="fas fa-trash-alt"></i> Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Nenhum documento de modelo específico encontrado<?php echo !empty($search_term) ? ' para a busca "' . htmlspecialchars($search_term) . '"' : ''; ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
        <h2><i class="fas fa-plane"></i> Documentos de Aeronaves Específicas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome do Documento</th>
                    <th>Vinculado ao</th>
                    <th class="actions-column">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($docs_especificos)): ?>
                    <?php foreach ($docs_especificos as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['nome_exibicao']); ?></td>
                            <td><?php echo htmlspecialchars($doc['aeronaves_associadas']); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo htmlspecialchars($doc['caminho_arquivo']); ?>" class="action-btn" style="background-color: #007bff;" download><i class="fas fa-download"></i> Baixar</a>
                                <a href="gerenciar_documentos.php?delete_id=<?php echo $doc['id']; ?>" class="action-btn" style="background-color:#dc3545;" onclick="return confirm('ATENÇÃO: Ação irreversível! Deseja excluir permanentemente este documento e todas as suas associações?');"><i class="fas fa-trash-alt"></i> Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Nenhum documento específico de aeronave encontrado<?php echo !empty($search_term) ? ' para a busca "' . htmlspecialchars($search_term) . '"' : ''; ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Estilo para alinhamento das tabelas */
.data-table th, .data-table td {
    padding: 12px 15px;
    vertical-align: middle;
    text-align: center;
}

.data-table th:first-child, .data-table td:first-child {
    text-align: left; /* Mantém a primeira coluna à esquerda */
}

.data-table th.actions-column,
.data-table td.action-buttons {
    width: 220px;
}

/* Estilo unificado para botões de ação */
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    margin: 2px;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    font-size: .85em;
    transition: opacity 0.2s;
    border: none;
    cursor: pointer;
}
.action-btn:hover {
    opacity: 0.85;
}
</style>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>