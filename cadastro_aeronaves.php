<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';
require_once 'includes/config_forcas.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isSuperAdmin && !$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";
$aeronaves = [];
$unidades_config = $config_forcas;

// --- Lógica para buscar aeronaves para o dropdown de vínculo ---
if ($isSuperAdmin) {
    $sql_aeronaves = "SELECT id, prefixo, forca_seguranca, crbm, obm FROM aeronaves WHERE status = 'ativo' ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
    if ($result_aeronaves) {
        while ($row = $result_aeronaves->fetch_assoc()) {
            $aeronaves[] = $row;
        }
    }
} elseif ($isAdmin) {
    $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, forca_seguranca, crbm, obm FROM aeronaves WHERE forca_seguranca = ? AND status = 'ativo' ORDER BY prefixo ASC");
    $stmt_aeronaves->bind_param("s", $user_forca_seguranca);
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
    if ($result_aeronaves) {
        while ($row = $result_aeronaves->fetch_assoc()) {
            $aeronaves[] = $row;
        }
    }
    $stmt_aeronaves->close();
}

// --- Busca fabricantes e modelos de AERONAVES do banco de dados ---
$fabricantes_e_modelos = [];
$sql_modelos = "SELECT id, fabricante, modelo, tipo_drone, pmd_kg FROM fabricantes_modelos WHERE tipo = 'Aeronave' ORDER BY fabricante, modelo";
$result_modelos = $conn->query($sql_modelos);
if ($result_modelos) {
    while ($row = $result_modelos->fetch_assoc()) {
        $fabricantes_e_modelos[$row['fabricante']][] = $row;
    }
}

// --- Busca prefixos já em uso ---
$usados_prefixos = [];
$sql_used_prefixes = "SELECT prefixo FROM aeronaves";
$result_used_prefixes = $conn->query($sql_used_prefixes);
if ($result_used_prefixes) {
    while ($row = $result_used_prefixes->fetch_assoc()) {
        $usados_prefixos[] = $row['prefixo'];
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // A lógica de salvamento do formulário vai aqui.
    // Como o foco é a correção do formulário em si, esta parte é omitida,
    // mas ela deve usar prepared statements e validação de dados.
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
                <div class="form-group">
                    <label for="crbm" id="crbm_label">CRBM:</label>
                    <select id="crbm" name="crbm" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="obm" id="obm_label">OBM/Seção:</label>
                    <select id="obm" name="obm" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro</option>
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
    // --- VARIÁVEIS E CONSTANTES ---
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

    // --- FUNÇÕES ---

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
        
        let placeholderText = 'Selecione a OBM/Seção';
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

        crbmLabel.textContent = 'CRBM:';
        obmLabel.textContent = 'OBM/Seção:';
        populateSelect(crbmSelect, [], 'Selecione a Força primeiro');
        crbmSelect.disabled = true;
        populateSelect(obmSelect, [], 'Selecione o CRBM/CRPM primeiro');
        obmSelect.disabled = true;
        
        crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
        crbmSelect.removeEventListener('change', handlePmUnitsChange);

        if (config) {
            crbmLabel.textContent = config.crbm_label + ':';
            obmLabel.textContent = config.obm_label + ':';

            let crbmOptions = [];
            if (config.unidades && !config.unidades.crpms) { // CBMPR, PPPR, etc.
                crbmOptions = Object.keys(config.unidades).sort();
                populateSelect(crbmSelect, crbmOptions, `Selecione a ${config.crbm_label}`, forca === 'CBMPR' ? formatCrbm : null);
                crbmSelect.addEventListener('change', handleDynamicUnitsChange);
            } else if (config.unidades && config.unidades.crpms) { // PMPR
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

    // --- EVENT LISTENERS ---

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

    // --- INICIALIZAÇÃO ---

    if (forcaSegurancaSelect && forcaSegurancaSelect.value) {
        forcaSegurancaSelect.dispatchEvent(new Event('change'));
    }

    atualizarModelos();
    checkFormValidity();
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>