<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php';

// Apenas administradores podem aceder a esta página
if (!$isAdmin) {
    header("Location: listar_missoes.php");
    exit();
}

$mensagem_status = "";
$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$missao_data = null;
$pilotos_associados_ids = [];
$tipos_operacao = [];


if ($missao_id <= 0) {
    header("Location: listar_missoes.php");
    exit();
}

// LÓGICA DE ATUALIZAÇÃO DA MISSÃO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pilotos']) && !empty($_POST['pilotos'])) {
    $conn->begin_transaction();
    try {
        // 1. Busca os dados antigos da missão para reverter o logbook
        $stmt_old_data = $conn->prepare("SELECT aeronave_id, total_distancia_percorrida, total_tempo_voo FROM missoes WHERE id = ?");
        $stmt_old_data->bind_param("i", $missao_id);
        $stmt_old_data->execute();
        $old_data_result = $stmt_old_data->get_result();
        $old_data = $old_data_result->fetch_assoc();
        $stmt_old_data->close();

        if (!$old_data) {
            throw new Exception("Missão original não encontrada para atualização.");
        }
        
        $old_aeronave_id = $old_data['aeronave_id'];
        $old_distancia = $old_data['total_distancia_percorrida'];
        $old_tempo = $old_data['total_tempo_voo'];

        // 2. Subtrai os valores antigos do logbook da aeronave antiga
        $stmt_subtract = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = GREATEST(0, distancia_total_acumulada - ?), tempo_voo_total_acumulado = GREATEST(0, tempo_voo_total_acumulado - ?) WHERE aeronave_id = ?");
        $stmt_subtract->bind_param("ddi", $old_distancia, $old_tempo, $old_aeronave_id);
        $stmt_subtract->execute();
        $stmt_subtract->close();
        
        // Coleta dos dados do formulário
        $aeronave_id = intval($_POST['aeronave_id']);
        $pilotos_selecionados = array_unique(array_filter($_POST['pilotos']));
        $data = htmlspecialchars($_POST['data']); // CORREÇÃO
        $descricao_operacao = htmlspecialchars($_POST['descricao_operacao']);
        $protocolo_sarpas = htmlspecialchars($_POST['protocolo_sarpas']);
        $rgo_ocorrencia = htmlspecialchars($_POST['rgo_ocorrencia']);
        $dados_vitima = htmlspecialchars($_POST['dados_vitima']);
        $link_fotos_videos = htmlspecialchars($_POST['link_fotos_videos']);
        $descricao_ocorrido = htmlspecialchars($_POST['descricao_ocorrido']);
        $contato_ats = htmlspecialchars($_POST['contato_ats']);
        $contato_ats_outro = ($contato_ats == 'Outro') ? htmlspecialchars($_POST['contato_ats_outro']) : NULL;
        $forma_acionamento = htmlspecialchars($_POST['forma_acionamento']);
        $forma_acionamento_outro = ($forma_acionamento == 'Outro') ? htmlspecialchars($_POST['forma_acionamento_outro']) : NULL;
        
        // Carrega os dados antigos para o caso de não haver upload de novos GPX
        $stmt_current_gpx_data = $conn->prepare("SELECT altitude_maxima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso FROM missoes WHERE id = ?");
        $stmt_current_gpx_data->bind_param("i", $missao_id);
        $stmt_current_gpx_data->execute();
        $current_gpx_data = $stmt_current_gpx_data->get_result()->fetch_assoc();
        $stmt_current_gpx_data->close();
        
        $new_altitude = $current_gpx_data['altitude_maxima'];
        $new_distancia = $current_gpx_data['total_distancia_percorrida'];
        $new_tempo = $current_gpx_data['total_tempo_voo'];
        $new_decolagem = $current_gpx_data['data_primeira_decolagem'];
        $new_pouso = $current_gpx_data['data_ultimo_pouso'];

        // Se novos arquivos GPX foram enviados, processa-os e sobrescreve os dados
        if (isset($_FILES['gpx_files']) && count(array_filter($_FILES['gpx_files']['name'])) > 0) {
            
            $gpxProcessor = new GPXProcessor();
            foreach ($_FILES['gpx_files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $gpxProcessor->load($tmp_name);
                }
            }
            $logData = $gpxProcessor->getAggregatedData();
            if ($logData === null) throw new Exception("Não foi possível processar os novos ficheiros GPX.");
            
            // Atualiza as variáveis com os novos dados do GPX
            $new_altitude = $logData['altitude_maxima'];
            $new_distancia = $logData['total_distancia_percorrida'];
            $new_tempo = $logData['total_tempo_voo'];
            $new_decolagem = $logData['data_primeira_decolagem'];
            $new_pouso = $logData['data_ultimo_pouso'];
        }

        // 5. Atualiza a missão com todos os dados 
        $stmt_update = $conn->prepare("UPDATE missoes SET 
            aeronave_id=?, data=?, descricao_operacao=?, protocolo_sarpas=?, rgo_ocorrencia=?, dados_vitima=?, 
            link_fotos_videos=?, descricao_ocorrido=?, contato_ats=?, contato_ats_outro=?, forma_acionamento=?, forma_acionamento_outro=?,
            altitude_maxima=?, total_distancia_percorrida=?, total_tempo_voo=?, data_primeira_decolagem=?, data_ultimo_pouso=?
            WHERE id=?");
        $stmt_update->bind_param("isssssssssssdssssi", 
            $aeronave_id, $data, $descricao_operacao, $protocolo_sarpas, $rgo_ocorrencia, $dados_vitima, 
            $link_fotos_videos, $descricao_ocorrido, $contato_ats, $contato_ats_outro, $forma_acionamento, $forma_acionamento_outro,
            $new_altitude, $new_distancia, $new_tempo, $new_decolagem, $new_pouso,
            $missao_id
        );
        $stmt_update->execute();
        $stmt_update->close();
        
        // 6. Atualiza os pilotos associados
        $stmt_delete_pilots = $conn->prepare("DELETE FROM missoes_pilotos WHERE missao_id = ?");
        $stmt_delete_pilots->bind_param("i", $missao_id);
        $stmt_delete_pilots->execute();
        $stmt_delete_pilots->close();
        
        $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
        foreach ($pilotos_selecionados as $piloto_id) {
            $pid = intval($piloto_id);
            $stmt_pilotos_assoc->bind_param("ii", $missao_id, $pid);
            $stmt_pilotos_assoc->execute();
        }
        $stmt_pilotos_assoc->close();

        // 7. Adiciona os novos valores ao logbook da nova aeronave
        $stmt_add_back = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
        $stmt_add_back->bind_param("idi", $aeronave_id, $new_distancia, $new_tempo);
        $stmt_add_back->execute();
        $stmt_add_back->close();

        $conn->commit();
        $mensagem_status = "<div class='success-message-box'>Missão atualizada com sucesso! Redirecionando...</div>";
        echo "<script>setTimeout(function() { window.location.href = 'ver_missao.php?id=$missao_id'; }, 2000);</script>";


    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar a missão: " . $e->getMessage() . "</div>";
    }
}

// CARREGAR DADOS DA MISSÃO E DEMAIS SELECTS PARA PREENCHER O FORMULÁRIO
$stmt_load = $conn->prepare("SELECT * FROM missoes WHERE id = ?");
$stmt_load->bind_param("i", $missao_id);
$stmt_load->execute();
$result_load = $stmt_load->get_result();
if ($result_load->num_rows === 1) {
    $missao_data = $result_load->fetch_assoc();
    
    $stmt_pilots = $conn->prepare("SELECT piloto_id FROM missoes_pilotos WHERE missao_id = ?");
    $stmt_pilots->bind_param("i", $missao_id);
    $stmt_pilots->execute();
    $result_pilots = $stmt_pilots->get_result();
    while($row = $result_pilots->fetch_assoc()) {
        $pilotos_associados_ids[] = $row['piloto_id'];
    }
    $stmt_pilots->close();
} else {
    $mensagem_status = "<div class='error-message-box'>Missão não encontrada.</div>";
}
$stmt_load->close();

$aeronaves_disponiveis = $conn->query("SELECT id, prefixo, modelo, crbm FROM aeronaves ORDER BY prefixo ASC")->fetch_all(MYSQLI_ASSOC);
$pilotos_disponiveis = $conn->query("SELECT id, posto_graduacao, nome_completo FROM pilotos ORDER BY nome_completo ASC")->fetch_all(MYSQLI_ASSOC);
$result_operacoes = $conn->query("SELECT id, nome FROM tipos_operacao ORDER BY nome ASC");
if($result_operacoes) {
    while($row = $result_operacoes->fetch_assoc()) { $tipos_operacao[] = $row; }
}

?>

<div class="main-content">
    <h1>Editar Missão <?php echo htmlspecialchars(!empty($missao_data['rgo_ocorrencia']) ? 'RGO ' . $missao_data['rgo_ocorrencia'] : '#' . $missao_id); ?></h1>
    <?php echo $mensagem_status; ?>

    <?php if ($missao_data): ?>
    <div class="form-container">
        <form id="editMissaoForm" action="editar_missao.php?id=<?php echo $missao_id; ?>" method="POST" enctype="multipart/form-data">
            
            <fieldset>
                <legend>1. Dados da Operação</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data:</label>
                        <input type="date" id="data" name="data" value="<?php echo htmlspecialchars($missao_data['data']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descricao_operacao">Descrição da Operação:</label>
                        <select id="descricao_operacao" name="descricao_operacao" required>
                             <?php foreach($tipos_operacao as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nome']); ?>" <?php echo ($missao_data['descricao_operacao'] == $tipo['nome']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO:</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" value="<?php echo htmlspecialchars($missao_data['rgo_ocorrencia']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="protocolo_sarpas">Protocolo SARPAS:</label>
                        <input type="text" id="protocolo_sarpas" name="protocolo_sarpas" value="<?php echo htmlspecialchars($missao_data['protocolo_sarpas']); ?>" required>
                    </div>
                </div>
                 <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descricao_ocorrido">Descreva o Ocorrido:</label>
                    <textarea id="descricao_ocorrido" name="descricao_ocorrido" rows="4" required><?php echo htmlspecialchars($missao_data['descricao_ocorrido']); ?></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Equipamentos e Pessoal</legend>
                 <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>" <?php echo ($missao_data['aeronave_id'] == $aeronave['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <select id="pilotos" name="pilotos[]" required multiple size="5">
                             <?php foreach ($pilotos_disponiveis as $piloto): ?>
                                <option value="<?php echo $piloto['id']; ?>" <?php echo in_array($piloto['id'], $pilotos_associados_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($piloto['posto_graduacao'] . ' ' . $piloto['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Para selecionar múltiplos, segure Ctrl (ou Cmd no Mac).</small>
                    </div>
                </div>
            </fieldset>
            
            <fieldset>
                <legend>3. Dados Complementares</legend>
                 <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="link_fotos_videos">Link das Fotos/Vídeos:</label>
                        <input type="url" id="link_fotos_videos" name="link_fotos_videos" value="<?php echo htmlspecialchars($missao_data['link_fotos_videos']); ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima:</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3"><?php echo htmlspecialchars($missao_data['dados_vitima']); ?></textarea>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>4. Ficheiros de Log de Voo (GPX)</legend>
                <div class="form-group">
                    <label for="gpx_files">Substituir Ficheiros GPX (opcional):</label>
                    <input type="file" id="gpx_files" name="gpx_files[]" accept=".gpx" multiple>
                    <small>Selecione novos ficheiros apenas se desejar substituir os existentes. Isto irá recalcular todos os dados de voo.</small>
                    <div id="file-list" style="margin-top: 10px;"></div>
                </div>
            </fieldset>

            <div class="form-actions">
                <a href="listar_missoes.php" class="button-secondary" style="background-color: #6c757d; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;">Cancelar</a>
                <button type="submit" id="submitButton" style="background-color: #007bff;">Atualizar Missão</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p>Missão não encontrada.</p>
    <?php endif; ?>
</div>

<style>
    fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    legend { font-weight: 700; color: #34495e; padding: 0 10px; }
    #file-list { font-size: 0.9em; color: #555; }
    select[multiple] { height: auto; min-height: 120px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gpxInput = document.getElementById('gpx_files');
    const fileListDiv = document.getElementById('file-list');
    gpxInput.addEventListener('change', function() {
        fileListDiv.innerHTML = '';
        if (this.files.length > 0) {
            const list = document.createElement('ul');
            for (const file of this.files) {
                const item = document.createElement('li');
                item.textContent = `Novo ficheiro: ${file.name}`;
                list.appendChild(item);
            }
            fileListDiv.appendChild(list);
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>