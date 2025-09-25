<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA DA PÁGINA
$mensagem_status = "";
$aeronave_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$aeronave_details = null;
$documentos_encontrados = [];

if ($aeronave_id <= 0) {
    header("Location: checklist.php");
    exit();
}

// --- Busca os detalhes da aeronave ---
$stmt_aeronave = $conn->prepare("SELECT prefixo, modelo, obm FROM aeronaves WHERE id = ?");
$stmt_aeronave->bind_param("i", $aeronave_id);
$stmt_aeronave->execute();
$result_aeronave = $stmt_aeronave->get_result();
if ($result_aeronave->num_rows > 0) {
    $aeronave_details = $result_aeronave->fetch_assoc();
} else {
    header("Location: checklist.php"); // Aeronave não existe
    exit();
}
$stmt_aeronave->close();
$modelo_aeronave_atual = $aeronave_details['modelo'];

// --- VERIFICAÇÃO DE PERMISSÃO PARA PILOTO ---
if ($isPiloto) {
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();
    if ($aeronave_details['obm'] !== $obm_do_piloto) {
        header("Location: checklist.php");
        exit();
    }
}

// --- LÓGICA DE MODIFICAÇÃO (APENAS ADMIN) ---
if ($isAdmin) {
    // Lógica de UPLOAD de novo arquivo e associação
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_novo'])) {
        $nome_documento = htmlspecialchars(trim($_POST['nome_documento']));
        $associar_a = $_POST['associar_a'];

        if (empty($nome_documento) || !isset($_FILES['documento']) || $_FILES['documento']['error'] != UPLOAD_ERR_OK) {
            $mensagem_status = "<div class='error-message-box'>Erro: Nome do documento e arquivo são obrigatórios.</div>";
        } else {
            $target_dir = "uploads/";
            $nome_original = basename($_FILES["documento"]["name"]);
            $tipo_arquivo = $_FILES["documento"]["type"];
            $nome_arquivo_servidor = uniqid() . '-' . preg_replace('/[^A-Za-z0-9\.\-]/', '_', $nome_original);
            $caminho_arquivo = $target_dir . $nome_arquivo_servidor;
            $conn->begin_transaction();
            $stmt_doc = $conn->prepare("INSERT INTO documentos (nome_exibicao, nome_arquivo_servidor, caminho_arquivo, tipo_arquivo) VALUES (?, ?, ?, ?)");
            $stmt_doc->bind_param("ssss", $nome_documento, $nome_arquivo_servidor, $caminho_arquivo, $tipo_arquivo);

            if (move_uploaded_file($_FILES["documento"]["tmp_name"], $caminho_arquivo) && $stmt_doc->execute()) {
                $novo_documento_id = $stmt_doc->insert_id;
                $associacao_ok = false;
                if ($associar_a == 'todos_modelos') {
                    $sql_todos_modelos = "SELECT DISTINCT modelo FROM fabricantes_modelos WHERE tipo = 'Aeronave'";
                    $result_todos_modelos = $conn->query($sql_todos_modelos);
                    if ($result_todos_modelos && $result_todos_modelos->num_rows > 0) {
                        $stmt_assoc_all = $conn->prepare("INSERT INTO documentos_associados (documento_id, modelo_aeronave) VALUES (?, ?)");
                        while ($row = $result_todos_modelos->fetch_assoc()) {
                            $stmt_assoc_all->bind_param("is", $novo_documento_id, $row['modelo']);
                            $stmt_assoc_all->execute();
                        }
                        $stmt_assoc_all->close();
                        $associacao_ok = true;
                    }
                } elseif ($associar_a == 'modelo') {
                    $stmt_assoc = $conn->prepare("INSERT INTO documentos_associados (documento_id, modelo_aeronave) VALUES (?, ?)");
                    $stmt_assoc->bind_param("is", $novo_documento_id, $modelo_aeronave_atual);
                    if ($stmt_assoc->execute()) $associacao_ok = true;
                    $stmt_assoc->close();
                } else {
                    $stmt_assoc = $conn->prepare("INSERT INTO documentos_associados (documento_id, aeronave_id) VALUES (?, ?)");
                    $stmt_assoc->bind_param("ii", $novo_documento_id, $aeronave_id);
                    if ($stmt_assoc->execute()) $associacao_ok = true;
                    $stmt_assoc->close();
                }
                if ($associacao_ok) {
                    $conn->commit();
                    $mensagem_status = "<div class='success-message-box'>Novo documento enviado e associado com sucesso!</div>";
                } else {
                    $conn->rollback();
                    unlink($caminho_arquivo); // Remove o arquivo se a associação falhar
                    $mensagem_status = "<div class='error-message-box'>Documento salvo, mas falha ao associar.</div>";
                }
            } else {
                $conn->rollback();
                $mensagem_status = "<div class='error-message-box'>Erro ao salvar o arquivo ou registrar no banco de dados.</div>";
            }
            $stmt_doc->close();
        }
    }
    // Lógica para DESASSOCIAR um documento
    if (isset($_GET['disassociate_id'])) {
        $disassociate_doc_id = intval($_GET['disassociate_id']);
        // Desassocia de um ID de aeronave OU de um modelo específico, mas não de todos os modelos de uma vez
        $stmt_dis = $conn->prepare("DELETE FROM documentos_associados WHERE documento_id = ? AND (aeronave_id = ? OR modelo_aeronave = ?)");
        $stmt_dis->bind_param("iis", $disassociate_doc_id, $aeronave_id, $modelo_aeronave_atual);
        if ($stmt_dis->execute()) {
            $mensagem_status = "<div class='success-message-box'>Associação removida com sucesso. O documento permanece na biblioteca.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao remover associação.</div>";
        }
        $stmt_dis->close();
    }
}

// --- LÓGICA DE BUSCA DE DOCUMENTOS (OTIMIZADA) ---
// Utiliza uma única query para buscar todos os documentos relevantes
// Prioriza a associação específica ('especifico') sobre a de modelo ('modelo')
$sql_documentos = "
    SELECT 
        d.id, 
        d.nome_exibicao, 
        d.caminho_arquivo, 
        d.nome_arquivo_servidor,
        -- Define o tipo de associação, dando prioridade para 'especifico'
        MIN(CASE 
            WHEN da.aeronave_id = ? THEN 'especifico' 
            ELSE 'modelo' 
        END) AS tipo_associacao
    FROM documentos d
    JOIN documentos_associados da ON d.id = da.documento_id
    WHERE da.aeronave_id = ? OR da.modelo_aeronave = ?
    GROUP BY d.id, d.nome_exibicao, d.caminho_arquivo, d.nome_arquivo_servidor
    ORDER BY d.nome_exibicao ASC
";

$stmt_documentos = $conn->prepare($sql_documentos);
$stmt_documentos->bind_param("iis", $aeronave_id, $aeronave_id, $modelo_aeronave_atual);
$stmt_documentos->execute();
$result_documentos = $stmt_documentos->get_result();

while($row = $result_documentos->fetch_assoc()) {
    $documentos_encontrados[] = $row;
}
$stmt_documentos->close();

?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>Documentos de: <?php echo htmlspecialchars($aeronave_details['prefixo'] . ' - ' . $aeronave_details['modelo']); ?></h1>
        <a href="checklist.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Seleção</a>
    </div>

    <?php echo $mensagem_status; ?>

    <?php if ($isAdmin): ?>
    <div class="form-container">
        <h2 style="margin-top:0; font-size: 1.3em;">Adicionar Novo Documento</h2>
        <form action="documentos_aeronave.php?id=<?php echo $aeronave_id; ?>" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label for="nome_documento">Nome do Documento:</label>
                    <input type="text" id="nome_documento" name="nome_documento" placeholder="Ex: Manual de Voo, Apólice de Seguro" required>
                </div>
                <div class="form-group">
                    <label for="documento">Arquivo (PDF, Excel, etc.):</label>
                    <input type="file" id="documento" name="documento" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Como associar este documento?</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="associar_a" value="todos_modelos">
                            <div class="radio-text">
                                <span>Associar a <strong>TODOS OS MODELOS</strong> de aeronaves</span>
                                <small>Ideal para documentos universais, como regulamentos e normas gerais.</small>
                            </div>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="associar_a" value="modelo" checked> 
                            <div class="radio-text">
                                <span>Associar a <strong>TODAS</strong> as aeronaves do modelo "<?php echo htmlspecialchars($modelo_aeronave_atual); ?>"</span>
                                <small>Ideal para manuais e documentos gerais deste modelo específico.</small>
                            </div>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="associar_a" value="aeronave"> 
                            <div class="radio-text">
                                <span>Associar <strong>SOMENTE</strong> a esta aeronave específica ("<?php echo htmlspecialchars($aeronave_details['prefixo']); ?>")</span>
                                <small>Ideal para apólices de seguro, registros específicos, etc.</small>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="upload_novo"><i class="fas fa-upload"></i> Enviar e Associar</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="table-container" style="margin-top: 30px;">
        <h2>Documentos Vinculados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Nome do Documento</th>
                    <?php if ($isAdmin): ?>
                        <th style="width: 200px;">Vinculado a</th>
                    <?php endif; ?>
                    <th style="width: 280px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($documentos_encontrados)): ?>
                    <?php foreach ($documentos_encontrados as $doc): ?>
                        <?php
                            // *** INÍCIO DA CORREÇÃO ***
                            // Garante que o caminho do diretório e o nome do arquivo sejam seguros para URL.
                            $directory_path = 'uploads/';
                            $filename = $doc['nome_arquivo_servidor'];
                            $safe_url = $directory_path . rawurlencode($filename);
                            // *** FIM DA CORREÇÃO ***
                        ?>
                        <tr>
                            <td style="text-align: left;"><?php echo htmlspecialchars($doc['nome_exibicao']); ?></td>
                            <?php if ($isAdmin): ?>
                                <td>
                                    <?php if ($doc['tipo_associacao'] == 'modelo'): ?>
                                        <span class="badge modelo"><i class="fas fa-copy"></i> Modelo</span>
                                    <?php else: ?>
                                        <span class="badge especifico"><i class="fas fa-plane"></i> Específico</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="action-buttons">
                                <a href="<?php echo htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8'); ?>" class="action-btn view-btn" target="_blank">
                                    <i class="fas fa-eye"></i> Visualizar
                                </a>
                                <a href="<?php echo htmlspecialchars($safe_url, ENT_QUOTES, 'UTF-8'); ?>" class="action-btn download-btn" download>
                                    <i class="fas fa-download"></i> Baixar
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="documentos_aeronave.php?id=<?php echo $aeronave_id; ?>&disassociate_id=<?php echo $doc['id']; ?>" class="action-btn disassociate-btn" onclick="return confirm('Atenção: A associação será removida, mas o arquivo permanecerá na biblioteca. Continuar?');">
                                        <i class="fas fa-unlink"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '3' : '2'; ?>">Nenhum documento encontrado para esta aeronave ou seu modelo.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.radio-group .radio-label { display: flex; align-items: center; padding: 12px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px; cursor: pointer; transition: background-color 0.2s ease-in-out; }
.radio-group .radio-label:hover { background-color: #f7f7f7; }
.radio-group input[type="radio"] { margin-right: 12px; flex-shrink: 0; width: 1.2em; height: 1.2em; }
.radio-group .radio-text span { line-height: 1.2; }
.radio-group .radio-text small { color: #6c757d; font-size: 0.85em; margin-top: 4px; line-height: 1; display: block; }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 0.8em; font-weight: 700; color: #fff; white-space: nowrap; vertical-align: middle; }
.badge i { margin-right: 4px; }
.badge.modelo { background-color: #17a2b8; }
.badge.especifico { background-color: #6c757d; }
.action-buttons { white-space: nowrap; }
.action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; margin-right: 5px; border-radius: 4px; text-decoration: none; color: #fff; font-size: .85em; transition: opacity 0.2s; }
.action-btn:hover { opacity: 0.85; }
.action-btn.view-btn { background-color: #6c757d; }
.action-btn.download-btn { background-color: #007bff; }
.action-btn.disassociate-btn { background-color: #ffc107; color: #212529; padding: 5px 8px; }
</style>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>