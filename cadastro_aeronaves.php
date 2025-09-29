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
$unidades_config = $config_forcas;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn->begin_transaction();
    try {
        $forca_seguranca = htmlspecialchars($_POST['forca_seguranca']);
        $fabricante = htmlspecialchars($_POST['fabricante']);
        $modelo_id = intval($_POST['modelo']);
        $prefixo = htmlspecialchars($_POST['prefixo']);
        $numero_serie = htmlspecialchars($_POST['numero_serie']);
        $crbm = htmlspecialchars($_POST['crbm']);
        $obm = htmlspecialchars($_POST['obm']);
        $status = htmlspecialchars($_POST['status']);
        $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel']);
        $info_adicionais = htmlspecialchars($_POST['info_adicionais']);
        $cadastro_sisant = htmlspecialchars($_POST['cadastro_sisant']);
        $validade_sisant = !empty($_POST['validade_sisant']) ? htmlspecialchars($_POST['validade_sisant']) : NULL;
        $data_aquisicao = !empty($_POST['data_aquisicao']) ? htmlspecialchars($_POST['data_aquisicao']) : NULL;

        $stmt_modelo = $conn->prepare("SELECT modelo, tipo_drone, pmd_kg FROM fabricantes_modelos WHERE id = ?");
        $stmt_modelo->bind_param("i", $modelo_id);
        $stmt_modelo->execute();
        $result_modelo = $stmt_modelo->get_result();
        if ($result_modelo->num_rows === 0) {
            throw new Exception("Modelo de aeronave selecionado é inválido.");
        }
        $modelo_data = $result_modelo->fetch_assoc();
        $modelo_nome = $modelo_data['modelo'];
        $tipo_drone = $modelo_data['tipo_drone'];
        $pmd_kg = $modelo_data['pmd_kg'];
        $stmt_modelo->close();

        $stmt_insert = $conn->prepare(
            "INSERT INTO aeronaves (forca_seguranca, prefixo, fabricante, modelo, numero_serie, crbm, obm, status, homologacao_anatel, info_adicionais, cadastro_sisant, validade_sisant, data_aquisicao, tipo_drone, pmd_kg) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert->bind_param("ssssssssssssssd", $forca_seguranca, $prefixo, $fabricante, $modelo_nome, $numero_serie, $crbm, $obm, $status, $homologacao_anatel, $info_adicionais, $cadastro_sisant, $validade_sisant, $data_aquisicao, $tipo_drone, $pmd_kg);
        
        if (!$stmt_insert->execute()) {
             throw new Exception($conn->error);
        }
        $nova_aeronave_id = $conn->insert_id;
        $stmt_insert->close();

        $stmt_logbook = $conn->prepare("INSERT INTO aeronaves_logbook (aeronave_id, distancia_total_acumulada, tempo_voo_total_acumulado) VALUES (?, 0, 0)");
        $stmt_logbook->bind_param("i", $nova_aeronave_id);
        $stmt_logbook->execute();
        $stmt_logbook->close();

        $conn->commit();
        $mensagem_status = "<div class='success-message-box'>Aeronave cadastrada com sucesso! Redirecionando...</div>";
        echo "<script>setTimeout(function() { window.location.href = 'listar_aeronaves.php'; }, 2000);</script>";

    } catch (Exception $e) {
        $conn->rollback();
        if ($conn->errno == 1062) {
            $mensagem_status = "<div class='error-message-box'>Erro: O Prefixo ou Número de Série informado já existe no sistema.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar a aeronave: " . $e->getMessage() . "</div>";
        }
    }
}

$fabricantes_e_modelos = [];
$sql_modelos = "SELECT id, fabricante, modelo, tipo_drone, pmd_kg FROM fabricantes_modelos WHERE tipo = 'Aeronave' ORDER BY fabricante, modelo";
$result_modelos = $conn->query($sql_modelos);
if ($result_modelos) {
    while ($row = $result_modelos->fetch_assoc()) {
        $fabricantes_e_modelos[$row['fabricante']][] = $row;
    }
}

$usados_prefixos = [];
$sql_used_prefixes = "SELECT prefixo FROM aeronaves";
$result_used_prefixes = $conn->query($sql_used_prefixes);
if ($result_used_prefixes) {
    while ($row = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row['prefixo'];
    }
}

$initial_crbm_label = 'Unidade de Lotação';
$initial_obm_label = 'Subunidade';

if ($isAdmin && !$isSuperAdmin && isset($unidades_config[$user_forca_seguranca])) {
    $initial_crbm_label = $unidades_config[$user_forca_seguranca]['crbm_label'];
    $initial_obm_label = $unidades_config[$user_forca_seguranca]['obm_label'];
}
?>

<div class="main-content">
    <h1>Cadastro de Aeronaves</h1>

    <?php echo $mensagem_status; ?>
    
    <div class="form-container">
        <form id="aeronaveForm" action="cadastro_aeronaves.php" method="POST">
            <div class="form-grid">
                <?php if ($isSuperAdmin || $isAdmin): ?>
                <div class="form-group">
                    <label for="forca_seguranca">Força de Segurança:</label>
                    <select id="forca_seguranca" name="forca_seguranca" required <?php echo ($isAdmin && !$isSuperAdmin) ? 'disabled' : ''; ?>>
                        <option value="">Selecione</option>
                        <?php foreach (array_keys($unidades_config) as $sigla_forca): ?>
                            <option value="<?php echo htmlspecialchars($sigla_forca); ?>" <?php echo ($isAdmin && $user_forca_seguranca === $sigla_forca) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($unidades_config[$sigla_forca]['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isAdmin && !$isSuperAdmin): ?>
                    <input type="hidden" name="forca_seguranca" value="<?php echo htmlspecialchars($user_forca_seguranca); ?>">
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="prefixo">Prefixo:</label>
                    <select id="prefixo" name="prefixo" required disabled>
                        <option value="">Selecione a Força de Segurança Primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione o Fabricante</option>
                        <?php foreach (array_keys($fabricantes_e_modelos) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>"><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required disabled>
                        <option value="">Selecione o Fabricante Primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" placeholder="Nº de Série do Drone" required>
                </div>

                <?php // ======================================================================================= ?>
                <?php // *** INÍCIO DA SEÇÃO: Campos SISANT e Data de Aquisição *** ?>
                <?php // ======================================================================================= ?>
                <div class="form-group">
                    <label for="cadastro_sisant">Cadastro SISANT:</label>
                    <input type="text" id="cadastro_sisant" name="cadastro_sisant" placeholder="Ex: PP-123456789" required>
                </div>
                <div class="form-group">
                    <label for="validade_sisant">Validade SISANT:</label>
                    <input type="date" id="validade_sisant" name="validade_sisant" required>
                </div>
                <div class="form-group">
                    <label for="data_aquisicao">Data de Aquisição:</label>
                    <input type="date" id="data_aquisicao" name="data_aquisicao" required>
                </div>
                <?php // ======================================================================================= ?>
                <?php // *** FIM DA SEÇÃO *** ?>
                <?php // ======================================================================================= ?>
                
                <div class="form-group">
                    <label for="crbm" id="crbm_label"><?php echo htmlspecialchars($initial_crbm_label); ?>:</label>
                    <select id="crbm" name="crbm" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm" id="obm_label"><?php echo htmlspecialchars($initial_obm_label); ?>:</label>
                    <select id="obm" name="obm" required disabled>
                        <option value="">Selecione a Unidade Superior primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_drone">Tipo de Drone:</label>
                    <input type="text" id="tipo_drone" name="tipo_drone" readonly>
                </div>
                <div class="form-group">
                    <label for="pmd_kg">PMD (kg):</label>
                    <input type="number" id="pmd_kg" name="pmd_kg" step="0.01" readonly>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo">Ativa</option>
                        <option value="em_manutencao">Em Manutenção</option>
                        <option value="baixada">Baixada</option>
                        <option value="adida">Adida</option>
                    </select>
                </div>
                 <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label>
                    <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim">Sim</option>
                        <option value="Não">Não</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="info_adicionais">Informações Adicionais (opcional):</label>
                    <textarea id="info_adicionais" name="info_adicionais" rows="4" placeholder="Adicione qualquer informação relevante sobre a aeronave."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button id="saveButton" type="submit" disabled>Salvar Aeronave</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const configForcas = <?php echo json_encode($unidades_config); ?>;
    const modelosPorFabricante = <?php echo json_encode($fabricantes_e_modelos); ?>;
    const usadosPrefixos = <?php echo json_encode($usados_prefixos); ?>;

    const form = document.getElementById('aeronaveForm');
    const saveButton = document.getElementById('saveButton');
    const forcaSegurancaSelect = document.getElementById('forca_seguranca');
    const prefixoSelect = document.getElementById('prefixo');
    const fabricanteSelect = document.getElementById('fabricante');
    const modeloSelect = document.getElementById('modelo');
    const crbmLabel = document.getElementById('crbm_label');
    const obmLabel = document.getElementById('obm_label');
    const crbmSelect = document.getElementById('crbm');
    const obmSelect = document.getElementById('obm');
    const tipoDroneInput = document.getElementById('tipo_drone');
    const pmdKgInput = document.getElementById('pmd_kg');
    const requiredFields = Array.from(form.querySelectorAll('[required]'));

    function checkFormValidity() {
        const isFormValid = requiredFields.every(field => {
            if (field.disabled) return true;
            return field.value.trim() !== '';
        });
        saveButton.disabled = !isFormValid;
    }

    function populateSelect(selectElement, optionsArray, placeholder, formatCallback = null) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        if (!optionsArray) return;
        optionsArray.forEach(optionText => {
            const option = document.createElement('option');
            option.value = optionText;
            option.textContent = formatCallback ? formatCallback(optionText) : optionText;
            selectElement.appendChild(option);
        });
    }

    function formatCrbm(crbm) {
        if (crbm && (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i))) {
            return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
        }
        return crbm;
    }

    function atualizarModelos() {
        const fabricante = fabricanteSelect.value;
        modeloSelect.innerHTML = '<option value="">Selecione o Fabricante Primeiro</option>';
        modeloSelect.disabled = true;
        tipoDroneInput.value = '';
        pmdKgInput.value = '';

        if (fabricante && modelosPorFabricante[fabricante]) {
            modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
            modeloSelect.disabled = false;
            modelosPorFabricante[fabricante].forEach(function(modelo) {
                const option = new Option(modelo.modelo, modelo.id);
                modeloSelect.add(option);
            });
        }
        checkFormValidity();
    }

    function atualizarPrefixos(forca) {
        prefixoSelect.innerHTML = '<option value="">Selecione a Força Primeiro</option>';
        prefixoSelect.disabled = true;
        
        if (forca && configForcas[forca] && configForcas[forca].prefixo_aeronave) {
            const prefixoBase = configForcas[forca].prefixo_aeronave;
            const prefixOptions = [];
            for (let i = 1; i <= 50; i++) {
                const nomePrefixo = `${prefixoBase} ${i.toString().padStart(2, '0')}`;
                if (!usadosPrefixos.includes(nomePrefixo)) {
                    prefixOptions.push(nomePrefixo);
                }
            }

            if (prefixOptions.length > 0) {
                populateSelect(prefixoSelect, prefixOptions, 'Selecione o Prefixo');
                prefixoSelect.disabled = false;
            } else {
                prefixoSelect.innerHTML = '<option value="">Nenhum prefixo disponível</option>';
            }
        }
    }

    function handleDynamicUnitsChange() {
        const forca = forcaSegurancaSelect.value;
        const crbm = this.value; 
        const config = configForcas[forca];
        
        let placeholderText = 'Selecione a Subunidade';
        if (config && config.obm_label) {
             placeholderText = `Selecione a ${config.obm_label}`;
        }

        obmSelect.innerHTML = `<option value="">${placeholderText}</option>`;
        obmSelect.disabled = true;
        
        if (crbm && config && config.unidades && config.unidades[crbm]) {
            populateSelect(obmSelect, config.unidades[crbm], placeholderText);
            obmSelect.disabled = false;
        }
        checkFormValidity();
    }
    
    const handlePmUnitsChange = () => {
        const forca = forcaSegurancaSelect.value;
        const config = configForcas[forca];
        const crpm = crbmSelect.value;
        const opms = (config && config.unidades.opms_por_crpm[crpm]) || [];
        populateSelect(obmSelect, opms, `Selecione a ${config.obm_label}`);
        obmSelect.disabled = opms.length === 0;
        checkFormValidity();
    };

    function handleForcaSegurancaChange() {
        const forca = forcaSegurancaSelect.value;
        const config = configForcas[forca];

        atualizarPrefixos(forca);

        crbmLabel.textContent = 'Unidade de Lotação:';
        obmLabel.textContent = 'Subunidade:';
        populateSelect(crbmSelect, [], 'Selecione a Força primeiro');
        crbmSelect.disabled = true;
        populateSelect(obmSelect, [], 'Selecione a Unidade de Lotação primeiro');
        obmSelect.disabled = true;
        
        crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
        crbmSelect.removeEventListener('change', handlePmUnitsChange);

        if (config) {
            crbmLabel.textContent = config.crbm_label + ':';
            obmLabel.textContent = config.obm_label + ':';

            let crbmOptions = [];
            if (config.unidades && !config.unidades.crpms) {
                crbmOptions = Object.keys(config.unidades).sort();
                populateSelect(crbmSelect, crbmOptions, `Selecione a ${config.crbm_label}`, forca === 'CBMPR' ? formatCrbm : null);
                crbmSelect.addEventListener('change', handleDynamicUnitsChange);
            } else if (config.unidades && config.unidades.crpms) {
                crbmOptions = config.unidades.crpms;
                populateSelect(crbmSelect, crbmOptions, `Selecione o ${config.crbm_label}`);
                crbmSelect.addEventListener('change', handlePmUnitsChange);
            }
            
            if (crbmOptions.length > 0) {
                crbmSelect.disabled = false;
            }
        }
        
        checkFormValidity();
    }

    requiredFields.forEach(field => {
        field.addEventListener('input', checkFormValidity);
        field.addEventListener('change', checkFormValidity);
    });

    forcaSegurancaSelect.addEventListener('change', handleForcaSegurancaChange);
    fabricanteSelect.addEventListener("change", atualizarModelos);

    modeloSelect.addEventListener("change", function() {
        const modeloId = this.value;
        tipoDroneInput.value = '';
        pmdKgInput.value = '';
        
        if (modeloId) {
            const fabricante = fabricanteSelect.value;
            const modeloSelecionado = modelosPorFabricante[fabricante]?.find(mod => mod.id == modeloId);
            if (modeloSelecionado) {
                tipoDroneInput.value = modeloSelecionado.tipo_drone || '';
                pmdKgInput.value = modeloSelecionado.pmd_kg || '';
            }
        }
        checkFormValidity();
    });

    handleForcaSegurancaChange();
    atualizarModelos();
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>  