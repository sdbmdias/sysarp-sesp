<?php
require_once 'includes/header.php';
require_once 'gpx_parser.php'; 

$mensagem_status = "";

// --- Lógica de busca de dados para os selects ---
$aeronaves_disponiveis = [];
$tipos_operacao = [];

if ($isPiloto) {
    // Se for piloto, busca o CRBM para filtrar as aeronaves
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    $crbm_do_piloto = $result_crbm->fetch_assoc()['crbm_piloto'];
    $stmt_crbm->close();
    
    $sql_aeronaves = "SELECT id, prefixo, modelo, crbm FROM aeronaves WHERE status = 'ativo' AND crbm = ? ORDER BY prefixo ASC";
    $stmt_aeronaves = $conn->prepare($sql_aeronaves);
    $stmt_aeronaves->bind_param("s", $crbm_do_piloto);
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
} else { // Admin vê todas as aeronaves ativas
    $sql_aeronaves = "SELECT id, prefixo, modelo, crbm FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
}
if ($result_aeronaves) {
    while($row = $result_aeronaves->fetch_assoc()) { $aeronaves_disponiveis[] = $row; }
}

$result_operacoes = $conn->query("SELECT id, nome FROM tipos_operacao ORDER BY nome ASC");
if($result_operacoes) {
    while($row = $result_operacoes->fetch_assoc()) { $tipos_operacao[] = $row; }
}

// --- Lógica de submissão do formulário ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validações essenciais
    if (isset($_FILES['gpx_files']) && count(array_filter($_FILES['gpx_files']['name'])) > 0 && isset($_POST['pilotos']) && !empty(array_filter($_POST['pilotos']))) {
        
        $conn->begin_transaction();
        try {
            $gpxProcessor = new GPXProcessor();
            $uploaded_files_data = [];
            foreach ($_FILES['gpx_files']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['gpx_files']['error'][$key] == UPLOAD_ERR_OK) {
                    $gpxProcessor->load($tmp_name);
                    $uploaded_files_data[] = [
                        'name' => $_FILES['gpx_files']['name'][$key],
                        'tmp_name' => $tmp_name
                    ];
                }
            }
            
            $logData = $gpxProcessor->getAggregatedData();
            if ($logData === null) {
                throw new Exception("Não foi possível processar os ficheiros GPX. Verifique o formato.");
            }
            
            // Coleta e sanitização dos dados do formulário
            $aeronave_id = intval($_POST['aeronave_id']);
            $pilotos_selecionados = array_unique(array_filter($_POST['pilotos']));
            // CORREÇÃO: Usando 'data' como o nome do campo
            $data = htmlspecialchars($_POST['data']);
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

            // Inserção na tabela de missões
            // CORREÇÃO: Usando a coluna 'data'
            $stmt_missao = $conn->prepare(
                "INSERT INTO missoes (aeronave_id, data, descricao_operacao, protocolo_sarpas, rgo_ocorrencia, dados_vitima, link_fotos_videos, descricao_ocorrido, contato_ats, contato_ats_outro, forma_acionamento, forma_acionamento_outro, altitude_maxima, total_distancia_percorrida, total_tempo_voo, data_primeira_decolagem, data_ultimo_pouso) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt_missao->bind_param("isssssssssssddiss", 
                $aeronave_id, $data, $descricao_operacao, $protocolo_sarpas, $rgo_ocorrencia, $dados_vitima, $link_fotos_videos, $descricao_ocorrido, $contato_ats, $contato_ats_outro, $forma_acionamento, $forma_acionamento_outro,
                $logData['altitude_maxima'], $logData['total_distancia_percorrida'], $logData['total_tempo_voo'], $logData['data_primeira_decolagem'], $logData['data_ultimo_pouso']
            );
            
            if (!$stmt_missao->execute()) {
                 throw new Exception("Falha ao criar a missão principal. Erro: " . $conn->error);
            }
            $missao_id = $conn->insert_id;
            $stmt_missao->close();

            // Associações de pilotos
            $stmt_pilotos_assoc = $conn->prepare("INSERT INTO missoes_pilotos (missao_id, piloto_id) VALUES (?, ?)");
            foreach ($pilotos_selecionados as $piloto_id) {
                $pid = intval($piloto_id);
                $stmt_pilotos_assoc->bind_param("ii", $missao_id, $pid);
                $stmt_pilotos_assoc->execute();
            }
            $stmt_pilotos_assoc->close();
            
            // Salvar logs GPX e coordenadas
            $stmt_gpx = $conn->prepare("INSERT INTO missoes_gpx_files (missao_id, file_name, tempo_voo, distancia_percorrida, altura_maxima, data_decolagem, data_pouso) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_coords = $conn->prepare("INSERT INTO missao_coordenadas (gpx_file_id, latitude, longitude, altitude, timestamp_ponto) VALUES (?, ?, ?, ?, ?)");

            $individualFileData = $gpxProcessor->getIndividualFileData();
            foreach ($individualFileData as $key => $file_log_data) {
                
                $decolagem_str = $file_log_data['data_decolagem']->format('Y-m-d H:i:s');
                $pouso_str = $file_log_data['data_pouso']->format('Y-m-d H:i:s');
                
                $stmt_gpx->bind_param("isiddss", $missao_id, $uploaded_files_data[$key]['name'], $file_log_data['tempo_voo'], $file_log_data['distancia_percorrida'], $file_log_data['altura_maxima'], $decolagem_str, $pouso_str);
                $stmt_gpx->execute();
                $gpx_file_id = $conn->insert_id;
                
                foreach ($file_log_data['trackPoints'] as $point) {
                    $time = $point['time']->format('Y-m-d H:i:s');
                    $stmt_coords->bind_param("iddds", $gpx_file_id, $point['lat'], $point['lon'], $point['ele'], $time);
                    $stmt_coords->execute();
                }
            }
            $stmt_gpx->close();
            $stmt_coords->close();
            
            // Atualizar logbook da aeronave
            $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE distancia_total_acumulada = distancia_total_acumulada + VALUES(distancia_total_acumulada), tempo_voo_total_acumulado = tempo_voo_total_acumulado + VALUES(tempo_voo_total_acumulado)");
            $stmt_logbook->bind_param("idi", $aeronave_id, $logData['total_distancia_percorrida'], $logData['total_tempo_voo']);
            $stmt_logbook->execute();
            $stmt_logbook->close();

            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão registrada com sucesso! Redirecionando...</div>";
            echo "<script>setTimeout(function() { window.location.href = 'listar_missoes.php'; }, 2000);</script>";

        } catch (Exception $e) {
            $conn->rollback();
            $mensagem_status = "<div class='error-message-box'>Erro ao registrar a missão: " . $e->getMessage() . "</div>";
        }

    } else {
        $mensagem_status = "<div class='error-message-box'>Erro: Preencha todos os campos obrigatórios, incluindo a seleção de pelo menos um piloto e um ficheiro GPX.</div>";
    }
}
?>

<div class="main-content">
    <h1>Registrar Nova Missão</h1>
    <?php echo $mensagem_status; ?>
    <div class="form-container">
        <form id="missaoForm" action="cadastro_missao.php" method="POST" enctype="multipart/form-data">
            
            <fieldset>
                <legend>1. Dados da Operação</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="data">Data:</label>
                        <input type="date" id="data" name="data" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="descricao_operacao">Descrição da Operação:</label>
                        <select id="descricao_operacao" name="descricao_operacao" required>
                            <option value="">Selecione o Tipo</option>
                            <?php foreach($tipos_operacao as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['nome']); ?>"><?php echo htmlspecialchars($tipo['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="forma_acionamento">Forma de Acionamento:</label>
                        <select id="forma_acionamento" name="forma_acionamento" required onchange="toggleOtherInput(this, 'outro_acionamento_wrapper')">
                            <option value="">Selecione...</option>
                            <option value="Oficial de dia">Oficial de dia</option>
                            <option value="COBOM">COBOM</option>
                            <option value="Chefe de Socorro">Chefe de Socorro</option>
                            <option value="Camara Técnica RPAS">Câmara Técnica RPAS</option>
                            <option value="Comandante da BBM/CRBM">Comandante da BBM/CRBM</option>
                            <option value="BOA/BPMOA/GOA">BOA/BPMOA/GOA</option>
                            <option value="SOARP">SOARP</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div id="outro_acionamento_wrapper" class="form-group" style="display: none;">
                        <label for="forma_acionamento_outro">Descreva qual:</label>
                        <input type="text" id="forma_acionamento_outro" name="forma_acionamento_outro">
                    </div>
                    <div class="form-group">
                        <label for="rgo_ocorrencia">Nº do RGO:</label>
                        <input type="text" id="rgo_ocorrencia" name="rgo_ocorrencia" placeholder="Ex: 123456/2025" required>
                    </div>
                     <div class="form-group">
                        <label for="protocolo_sarpas">Protocolo SARPAS:</label>
                        <input type="text" id="protocolo_sarpas" name="protocolo_sarpas" placeholder="Ex: AS202407-1234" required>
                    </div>
                    <div class="form-group">
                        <label for="contato_ats">Contato com o Orgão ATS:</label>
                         <select id="contato_ats" name="contato_ats" required onchange="toggleOtherInput(this, 'outro_ats_wrapper')">
                            <option value="Espaço Aéreo Golf (G)">Espaço Aéreo Golf (G)</option>
                            <option value="Telefonia">Telefonia</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Rádio">Rádio</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div id="outro_ats_wrapper" class="form-group" style="display: none;">
                        <label for="contato_ats_outro">Descreva qual:</label>
                        <input type="text" id="contato_ats_outro" name="contato_ats_outro">
                    </div>
                </div>
                 <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descricao_ocorrido">Descreva o Ocorrido:</label>
                    <textarea id="descricao_ocorrido" name="descricao_ocorrido" rows="4" placeholder="Relato sucinto dos fatos e ações." required></textarea>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Equipamentos e Pessoal</legend>
                 <div class="form-grid">
                    <div class="form-group">
                        <label for="aeronave_id">Aeronave (HAWK):</label>
                        <select id="aeronave_id" name="aeronave_id" required>
                            <option value="">Selecione a Aeronave</option>
                            <?php foreach ($aeronaves_disponiveis as $aeronave): ?>
                                <option value="<?php echo $aeronave['id']; ?>" data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>">
                                    <?php echo htmlspecialchars($aeronave['prefixo'] . ' - ' . $aeronave['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pilotos">Piloto(s):</label>
                        <div id="pilots-container">
                            <small>Selecione uma aeronave para carregar a lista de pilotos.</small>
                        </div>
                        <button type="button" id="add-pilot-btn" class="button-secondary" style="margin-top: 10px; display: none; background-color: #5a6268; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">Adicionar outro Piloto</button>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>3. Dados Complementares</legend>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="link_fotos_videos">Link do Upload das Fotos/Vídeos (Opcional):</label>
                        <input type="url" id="link_fotos_videos" name="link_fotos_videos" placeholder="https://exemplo.com/fotos_da_missao">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="dados_vitima">Dados da Vítima (Opcional):</label>
                        <textarea id="dados_vitima" name="dados_vitima" rows="3" placeholder="Informações relevantes sobre a vítima ou alvo da busca."></textarea>
                    </div>
                </div>
            </fieldset>
            
            <fieldset>
                <legend>4. Ficheiros de Log de Voo (GPX)</legend>
                <div class="form-group">
                    <label for="gpx_files">Selecione os ficheiros GPX da missão:</label>
                    <input type="file" id="gpx_files" name="gpx_files[]" accept=".gpx" multiple required>
                    <small>Pode selecionar vários ficheiros. Eles serão unificados.</small>
                    <div id="file-list" style="margin-top: 10px;"></div>
                </div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" id="submitButton">Registrar Missão</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleOtherInput(selectElement, wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    const otherInput = wrapper.querySelector('input');
    if (selectElement.value === 'Outro') {
        wrapper.style.display = 'block';
        otherInput.required = true;
    } else {
        wrapper.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const aeronaveSelect = document.getElementById('aeronave_id');
    const pilotsContainer = document.getElementById('pilots-container');
    const addPilotBtn = document.getElementById('add-pilot-btn');
    let pilotList = [];

    aeronaveSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const crbm = selectedOption.getAttribute('data-crbm');
        
        pilotsContainer.innerHTML = '<small>Carregando pilotos...</small>';
        addPilotBtn.style.display = 'none';

        if (crbm) {
            fetch(`get_pilotos.php?crbm=${crbm}`)
                .then(response => {
                    if (!response.ok) { throw new Error('Erro na rede ou no servidor.'); }
                    return response.json();
                })
                .then(data => {
                    pilotList = data;
                    pilotsContainer.innerHTML = '';
                    if (pilotList.length > 0) {
                        addPilotSelect();
                        addPilotBtn.style.display = 'inline-block';
                    } else {
                        pilotsContainer.innerHTML = '<small>Nenhum piloto ativo encontrado para este CRBM.</small>';
                    }
                })
                .catch(error => {
                    pilotsContainer.innerHTML = `<small style="color: red;">${error.message}</small>`;
                });
        } else {
            pilotsContainer.innerHTML = '<small>Selecione uma aeronave para carregar a lista de pilotos.</small>';
        }
    });

    function addPilotSelect() {
        const selectWrapper = document.createElement('div');
        selectWrapper.style.display = 'flex';
        selectWrapper.style.alignItems = 'center';
        selectWrapper.style.marginBottom = '5px';

        const newSelect = document.createElement('select');
        newSelect.name = 'pilotos[]';
        newSelect.required = true;
        
        let optionsHtml = '<option value="">Selecione um piloto</option>';
        pilotList.forEach(piloto => {
            optionsHtml += `<option value="${piloto.id}">${piloto.nome_completo}</option>`;
        });
        newSelect.innerHTML = optionsHtml;

        selectWrapper.appendChild(newSelect);

        if (pilotsContainer.children.length > 0) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = 'Remover';
            removeBtn.style.marginLeft = '10px';
            removeBtn.style.backgroundColor = '#dc3545';
            removeBtn.style.color = 'white';
            removeBtn.style.border = 'none';
            removeBtn.style.padding = '5px 10px';
            removeBtn.style.borderRadius = '4px';
            removeBtn.style.cursor = 'pointer';
            removeBtn.onclick = function() {
                pilotsContainer.removeChild(selectWrapper);
            };
            selectWrapper.appendChild(removeBtn);
        }
        
        pilotsContainer.appendChild(selectWrapper);
    }

    addPilotBtn.addEventListener('click', addPilotSelect);

    const form = document.getElementById('missaoForm');
    form.addEventListener('submit', function(e) {
        const selectedPilots = Array.from(document.querySelectorAll('select[name="pilotos[]"]')).map(s => s.value);
        
        if (selectedPilots.some(p => p === '')) {
             e.preventDefault();
             alert('Erro: Por favor, selecione um piloto em todos os campos ou remova os campos desnecessários.');
             return;
        }

        const uniquePilots = new Set(selectedPilots);
        if (selectedPilots.length > uniquePilots.size) {
            e.preventDefault();
            alert('Erro: Por favor, não selecione o mesmo piloto mais de uma vez.');
        }
    });

    const gpxInput = document.getElementById('gpx_files');
    const fileListDiv = document.getElementById('file-list');
    gpxInput.addEventListener('change', function() {
        fileListDiv.innerHTML = '';
        if (this.files.length > 0) {
            const list = document.createElement('ul');
            list.style.paddingLeft = '20px';
            for (const file of this.files) {
                const item = document.createElement('li');
                item.textContent = file.name;
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