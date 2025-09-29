<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";
$aeronave_data = null;
$user_forca_seguranca = $_SESSION['forca_seguranca'] ?? '';

$aeronave_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : null);
$usados_prefixos = [];
if ($aeronave_id) {
    $stmt_prefixes = $conn->prepare("SELECT prefixo FROM aeronaves WHERE id != ?");
    $stmt_prefixes->bind_param("i", $aeronave_id);
    $stmt_prefixes->execute();
    $result_used_prefixes = $stmt_prefixes->get_result();
    while ($row_prefix = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row_prefix['prefixo'];
    }
    $stmt_prefixes->close();
}

$fabricantes_e_modelos = [];
$sql_modelos = "SELECT id, fabricante, modelo, tipo_drone, pmd_kg FROM fabricantes_modelos WHERE tipo = 'Aeronave' ORDER BY fabricante, modelo";
$result_modelos = $conn->query($sql_modelos);
if ($result_modelos) {
    while ($row = $result_modelos->fetch_assoc()) {
        $fabricantes_e_modelos[$row['fabricante']][] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && $aeronave_id) {
    $forca_seguranca = htmlspecialchars($_POST['forca_seguranca']);
    $fabricante = htmlspecialchars($_POST['fabricante']);
    $modelo_id = htmlspecialchars($_POST['modelo']);
    $prefixo = htmlspecialchars($_POST['prefixo']);
    $numero_serie = htmlspecialchars($_POST['numero_serie']);
    $cadastro_sisant = htmlspecialchars($_POST['cadastro_sisant']);
    $validade_sisant = htmlspecialchars($_POST['validade_sisant']);
    $crbm = htmlspecialchars($_POST['crbm']);
    $obm = htmlspecialchars($_POST['obm']);
    $data_aquisicao = htmlspecialchars($_POST['data_aquisicao']);
    $status = htmlspecialchars($_POST['status']);
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']);
    $info_adicionais = htmlspecialchars($_POST['info_adicionais']);
    
    $stmt_modelo_info = $conn->prepare("SELECT fabricante, modelo, tipo_drone, pmd_kg FROM fabricantes_modelos WHERE id = ?");
    $stmt_modelo_info->bind_param("i", $modelo_id);
    $stmt_modelo_info->execute();
    $result_modelo_info = $stmt_modelo_info->get_result();
    $modelo_info = $result_modelo_info->fetch_assoc();
    $stmt_modelo_info->close();
    
    $modelo_nome = $modelo_info['modelo'];
    $tipo_drone = $modelo_info['tipo_drone'];
    $pmd_kg = $modelo_info['pmd_kg'];

    $stmt = $conn->prepare("UPDATE aeronaves SET fabricante=?, modelo=?, prefixo=?, numero_serie=?, cadastro_sisant=?, validade_sisant=?, crbm=?, obm=?, tipo_drone=?, pmd_kg=?, data_aquisicao=?, status=?, homologacao_anatel=?, info_adicionais=?, forca_seguranca=? WHERE id = ?");
    $stmt->bind_param("sssssssssdsssssi", $fabricante, $modelo_nome, $prefixo, $numero_serie, $cadastro_sisant, $validade_sisant, $crbm, $obm, $tipo_drone, $pmd_kg, $data_aquisicao, $status, $homologacao_anatel, $info_adicionais, $forca_seguranca, $aeronave_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Aeronave atualizada com sucesso! Redirecionando...</div>";
        echo "<script>setTimeout(function() { window.location.href = 'listar_aeronaves.php'; }, 2000);</script>";
    } else {
        if ($conn->errno == 1062) {
            $mensagem_status = "<div class='error-message-box'>Erro: O prefixo ou número de série já existe.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao atualizar aeronave: " . htmlspecialchars($stmt->error) . "</div>";
        }
    }
    $stmt->close();
}

if ($aeronave_id) {
    $stmt_load = $conn->prepare("SELECT a.*, fm.tipo_drone, fm.pmd_kg, fm.id as modelo_id FROM aeronaves a LEFT JOIN fabricantes_modelos fm ON a.fabricante = fm.fabricante AND a.modelo = fm.modelo WHERE a.id = ?");
    $stmt_load->bind_param("i", $aeronave_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $aeronave_data = $result->fetch_assoc();
        if ($isAdmin && !$isSuperAdmin && $user_forca_seguranca !== ($aeronave_data['forca_seguranca'] ?? '')) {
            $mensagem_status = "<div class='error-message-box'>Você não tem permissão para editar esta aeronave.</div>";
            $aeronave_data = null;
        }
    } else {
        $mensagem_status = "<div class='error-message-box'>Aeronave não encontrada.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID da aeronave não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Aeronave</h1>

    <?php echo $mensagem_status; ?>

    <?php if ($aeronave_data): ?>
    <div class="form-container">
        <form id="editAeronaveForm" action="editar_aeronaves.php?id=<?php echo htmlspecialchars($aeronave_id); ?>" method="POST">
            <input type="hidden" name="aeronave_id" value="<?php echo htmlspecialchars($aeronave_data['id']); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="forca_seguranca" id="forca_seguranca_label">Força de Segurança:</label>
                    <select id="forca_seguranca" name="forca_seguranca" required disabled>
                        <?php foreach ($config_forcas as $sigla => $config): ?>
                            <option value="<?php echo htmlspecialchars($sigla); ?>" <?php echo ($aeronave_data['forca_seguranca'] == $sigla) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($config['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="forca_seguranca" value="<?php echo htmlspecialchars($aeronave_data['forca_seguranca']); ?>">
                </div>
                <div class="form-group">
                    <label for="prefixo">Prefixo:</label>
                    <select id="prefixo" name="prefixo" required></select>
                </div>
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione o Fabricante</option>
                        <?php foreach (array_keys($fabricantes_e_modelos) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>" <?php echo ($aeronave_data['fabricante'] == $fab) ? 'selected' : ''; ?>><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required></select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" value="<?php echo htmlspecialchars($aeronave_data['numero_serie']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="cadastro_sisant">Cadastro SISANT:</label>
                    <input type="text" id="cadastro_sisant" name="cadastro_sisant" value="<?php echo htmlspecialchars($aeronave_data['cadastro_sisant']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="validade_sisant">Validade SISANT:</label>
                    <input type="date" id="validade_sisant" name="validade_sisant" value="<?php echo htmlspecialchars($aeronave_data['validade_sisant']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="crbm" id="crbm_label">CRBM:</label>
                    <select id="crbm" name="crbm" required></select>
                </div>
                <div class="form-group">
                    <label for="obm" id="obm_label">OBM/Seção:</label>
                    <select id="obm" name="obm" required></select>
                </div>
                <div class="form-group">
                    <label for="tipo_drone">Tipo de Drone:</label>
                    <input type="text" id="tipo_drone" name="tipo_drone" value="<?php echo htmlspecialchars($aeronave_data['tipo_drone'] ?? ''); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="pmd_kg">PMD (kg):</label>
                    <input type="number" id="pmd_kg" name="pmd_kg" step="0.01" value="<?php echo htmlspecialchars($aeronave_data['pmd_kg']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="data_aquisicao">Data de Aquisição:</label>
                    <input type="date" id="data_aquisicao" name="data_aquisicao" value="<?php echo htmlspecialchars($aeronave_data['data_aquisicao']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo" <?php echo ($aeronave_data['status'] == 'ativo') ? 'selected' : ''; ?>>Ativa</option>
                        <option value="em_manutencao" <?php echo ($aeronave_data['status'] == 'em_manutencao') ? 'selected' : ''; ?>>Em Manutenção</option>
                        <option value="baixada" <?php echo ($aeronave_data['status'] == 'baixada') ? 'selected' : ''; ?>>Baixada</option>
                        <option value="adida" <?php echo ($aeronave_data['status'] == 'adida') ? 'selected' : ''; ?>>Adida</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label> <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim" <?php echo (isset($aeronave_data['homologacao_anatel']) && $aeronave_data['homologacao_anatel'] == 'Sim') ? 'selected' : ''; ?>>Sim</option>
                        <option value="Não" <?php echo (isset($aeronave_data['homologacao_anatel']) && $aeronave_data['homologacao_anatel'] == 'Não') ? 'selected' : ''; ?>>Não</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4"><?php echo htmlspecialchars($aeronave_data['info_adicionais'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="updateButton" type="submit" style="background-color:#007bff;">Atualizar Aeronave</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados da aeronave. Verifique se o ID está correto ou <a href="listar_aeronaves.php">volte para a lista</a>.</p>
    <?php endif; ?>
</div>

<?php // ==================================================================================================== ?>
<?php // *** INÍCIO DA SEÇÃ: Bloco de JavaScript Completo *** ?>
<?php // ==================================================================================================== ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editAeronaveForm')) {
        const modelosPorFabricante = <?php echo json_encode($fabricantes_e_modelos); ?>;
        const configForcas = <?php echo json_encode($config_forcas); ?>;
        const usadosPrefixos = <?php echo json_encode($usados_prefixos); ?>;

        const forcaSegurancaSelect = document.getElementById('forca_seguranca');
        const fabricanteSelect = document.getElementById('fabricante');
        const modeloSelect = document.getElementById('modelo');
        const crbmSelect = document.getElementById('crbm');
        const obmSelect = document.getElementById('obm');
        const prefixoSelect = document.getElementById('prefixo');
        const tipoDroneInput = document.getElementById('tipo_drone');
        const pmdKgInput = document.getElementById('pmd_kg');
        const crbmLabel = document.getElementById('crbm_label');
        const obmLabel = document.getElementById('obm_label');
        
        const valorSalvo = {
            forca_seguranca: "<?php echo addslashes($aeronave_data['forca_seguranca'] ?? ''); ?>",
            fabricante: "<?php echo addslashes($aeronave_data['fabricante'] ?? ''); ?>",
            modelo_id: "<?php echo addslashes($aeronave_data['modelo_id'] ?? ''); ?>",
            crbm: "<?php echo addslashes($aeronave_data['crbm'] ?? ''); ?>",
            obm: "<?php echo addslashes($aeronave_data['obm'] ?? ''); ?>",
            prefixo: "<?php echo addslashes($aeronave_data['prefixo'] ?? ''); ?>",
        };
        
        function populateSelect(selectElement, optionsArray, selectedValue, placeholderText = 'Selecione...') {
            selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
            if (!optionsArray) return;
            optionsArray.forEach(optionText => {
                const option = document.createElement('option');
                option.value = optionText;
                option.textContent = formatLabel(optionText);
                if (optionText === selectedValue) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });
        }

        function formatLabel(text) {
             if (text.match(/^\d+CRBM$/i) || text.match(/^\d+CRPM$/i)) {
                return text.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
            }
            return text;
        }
        
        function updateModelos(isInitialLoad = false) {
            const fabricante = fabricanteSelect.value;
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            tipoDroneInput.value = '';
            pmdKgInput.value = '';

            if (fabricante && modelosPorFabricante[fabricante]) {
                modelosPorFabricante[fabricante].forEach(function(modelo) {
                    const option = document.createElement('option');
                    option.value = modelo.id;
                    option.textContent = modelo.modelo;
                    if (isInitialLoad && modelo.id == valorSalvo.modelo_id) {
                        option.selected = true;
                        tipoDroneInput.value = modelo.tipo_drone;
                        pmdKgInput.value = modelo.pmd_kg;
                    }
                    modeloSelect.appendChild(option);
                });
            }
        }
        
        function updateObmsFromCrbm(isInitialLoad = false) {
            const forca = forcaSegurancaSelect.value;
            const crbm = crbmSelect.value;
            const config = configForcas[forca];
            
            obmSelect.innerHTML = `<option value="">Selecione...</option>`;
            
            if (crbm && config) {
                let obms;
                if (config.unidades.opms_por_crpm) { // PMPR
                    obms = config.unidades.opms_por_crpm[crbm] || [];
                } else { // Outras forças
                    obms = config.unidades[crbm] || [];
                }
                
                populateSelect(obmSelect, obms, 'Selecione...', isInitialLoad ? valorSalvo.obm : null);
            }
        }

        function gerarPrefixos() {
            prefixoSelect.innerHTML = '';
            
            const optionAtual = document.createElement('option');
            optionAtual.value = valorSalvo.prefixo;
            optionAtual.textContent = valorSalvo.prefixo;
            optionAtual.selected = true;
            prefixoSelect.appendChild(optionAtual);

            const forca = forcaSegurancaSelect.value;
            if (forca && configForcas[forca] && configForcas[forca].prefixo_aeronave) {
                const prefixoBase = configForcas[forca].prefixo_aeronave;
                for (let i = 1; i <= 50; i++) {
                    const nomePrefixo = `${prefixoBase} ${i.toString().padStart(2, '0')}`;
                    if (nomePrefixo === valorSalvo.prefixo) continue;

                    if (!usadosPrefixos.includes(nomePrefixo)) {
                        const option = document.createElement('option');
                        option.value = nomePrefixo;
                        option.textContent = nomePrefixo;
                        prefixoSelect.appendChild(option);
                    }
                }
            }
        }
        
        function initializeForm() {
            const forca = valorSalvo.forca_seguranca;
            if (!forca || !configForcas[forca]) return;

            const config = configForcas[forca];

            crbmLabel.textContent = config.crbm_label + ':';
            obmLabel.textContent = config.obm_label + ':';

            let crbms;
            if (config.unidades.crpms) { // PMPR
                crbms = config.unidades.crpms;
            } else { // Outras forças
                crbms = Object.keys(config.unidades).sort();
            }
            populateSelect(crbmSelect, crbms, 'Selecione...', valorSalvo.crbm);
            updateObmsFromCrbm(true);
            updateModelos(true);
            gerarPrefixos();

            crbmSelect.addEventListener('change', () => updateObmsFromCrbm(false));
            fabricanteSelect.addEventListener('change', () => updateModelos(false));
        }
        
        initializeForm();
    }
});
</script>
<?php // ==================================================================================================== ?>
<?php // *** FIM DA SEÇÃO *** ?>
<?php // ==================================================================================================== ?>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>