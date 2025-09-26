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

// --- Busca fabricantes e modelos de CONTROLES do banco de dados ---
$fabricantes_e_modelos_controles = [];
$sql_modelos_ctrl = "SELECT fabricante, modelo FROM fabricantes_modelos WHERE tipo = 'Controle' ORDER BY fabricante, modelo";
$result_modelos_ctrl = $conn->query($sql_modelos_ctrl);
if ($result_modelos_ctrl) {
    while ($row = $result_modelos_ctrl->fetch_assoc()) {
        $fabricantes_e_modelos_controles[$row['fabricante']][] = $row['modelo'];
    }
}

// --- Lógica para processar o formulário quando enviado ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fabricante = htmlspecialchars($_POST['fabricante'] ?? '');
    $modelo = htmlspecialchars($_POST['modelo'] ?? '');
    $numero_serie = htmlspecialchars($_POST['numero_serie'] ?? '');
    $forca_seguranca = htmlspecialchars($_POST['forca_seguranca'] ?? '');
    $homologacao_anatel = htmlspecialchars($_POST['homologacao_anatel'] ?? '');
    $aeronave_id = !empty($_POST['aeronave_id']) ? intval($_POST['aeronave_id']) : NULL;
    $status = htmlspecialchars($_POST['status'] ?? 'ativo');
    
    $crbm = htmlspecialchars($_POST['crbm'] ?? '');
    $obm = htmlspecialchars($_POST['obm'] ?? '');

    if ($aeronave_id) {
        $stmt_acft_data = $conn->prepare("SELECT crbm, obm, forca_seguranca FROM aeronaves WHERE id = ?");
        $stmt_acft_data->bind_param("i", $aeronave_id);
        $stmt_acft_data->execute();
        $result_acft_data = $stmt_acft_data->get_result();
        if ($result_acft_data->num_rows > 0) {
            $acft_data = $result_acft_data->fetch_assoc();
            $crbm = $acft_data['crbm'];
            $obm = $acft_data['obm'];
            $forca_seguranca = $acft_data['forca_seguranca'];
        }
        $stmt_acft_data->close();
    }

    $stmt = $conn->prepare("INSERT INTO controles (fabricante, modelo, numero_serie, crbm, obm, forca_seguranca, homologacao_anatel, aeronave_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssis", $fabricante, $modelo, $numero_serie, $crbm, $obm, $forca_seguranca, $homologacao_anatel, $aeronave_id, $status);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Controle cadastrado com sucesso! Redirecionando...</div>";
        echo "<script>setTimeout(function() { window.location.href = 'listar_controles.php'; }, 2000);</script>";
    } else {
        if ($conn->errno == 1062) {
            $mensagem_status = "<div class='error-message-box'>Erro: O número de série já existe. Por favor, insira um número de série único.</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar controle: " . htmlspecialchars($stmt->error) . "</div>";
        }
    }
    $stmt->close();
}

// ====================================================================================================
// *** INÍCIO DA SEÇÃO ALTERADA: Definição de Rótulos Iniciais Dinâmicos ***
// ====================================================================================================
$initial_crbm_label = 'Unidade de Lotação';
$initial_obm_label = 'Subunidade';

if ($isAdmin && !$isSuperAdmin && isset($unidades_config[$user_forca_seguranca])) {
    $initial_crbm_label = $unidades_config[$user_forca_seguranca]['crbm_label'];
    $initial_obm_label = $unidades_config[$user_forca_seguranca]['obm_label'];
}
// ====================================================================================================
// *** FIM DA SEÇÃO ALTERADA ***
// ====================================================================================================
?>

<div class="main-content">
    <h1>Cadastro de Controles (Rádios)</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form action="cadastro_controles.php" method="POST">
            <div class="form-grid">
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
                <div class="form-group">
                    <label for="fabricante">Fabricante:</label>
                    <select id="fabricante" name="fabricante" required>
                        <option value="">Selecione...</option>
                        <?php foreach (array_keys($fabricantes_e_modelos_controles) as $fab): ?>
                            <option value="<?php echo htmlspecialchars($fab); ?>"><?php echo htmlspecialchars($fab); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modelo">Modelo:</label>
                    <select id="modelo" name="modelo" required disabled>
                        <option value="">Selecione um fabricante primeiro...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="numero_serie">Número de Série:</label>
                    <input type="text" id="numero_serie" name="numero_serie" placeholder="Nº de Série do Controle" required>
                </div>
                <div class="form-group">
                    <?php // Rótulo agora é dinâmico ?>
                    <label for="crbm" id="crbm_label"><?php echo htmlspecialchars($initial_crbm_label); ?>:</label>
                    <select id="crbm" name="crbm" required disabled>
                        <option value="">Selecione a Força de Segurança primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <?php // Rótulo agora é dinâmico ?>
                    <label for="obm" id="obm_label"><?php echo htmlspecialchars($initial_obm_label); ?>:</label>
                    <select id="obm" name="obm" required disabled>
                        <option value="">Selecione a Unidade de Origem primeiro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="aeronave_id">Vincular à Aeronave (Opcional):</label>
                    <select id="aeronave_id" name="aeronave_id">
                        <option value="">Nenhuma (Controle de Reserva)</option>
                        <?php foreach ($aeronaves as $aeronave): ?>
                            <option value="<?php echo htmlspecialchars($aeronave['id']); ?>" 
                                data-forca="<?php echo htmlspecialchars($aeronave['forca_seguranca']); ?>"
                                data-crbm="<?php echo htmlspecialchars($aeronave['crbm']); ?>"
                                data-obm="<?php echo htmlspecialchars($aeronave['obm']); ?>">
                                <?php echo htmlspecialchars($aeronave['prefixo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="homologacao_anatel">Homologação ANATEL:</label>
                    <select id="homologacao_anatel" name="homologacao_anatel" required>
                        <option value="Sim">Sim</option>
                        <option value="Não">Não</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="ativo">Ativo</option>
                        <option value="em_manutencao">Em Manutenção</option>
                        <option value="baixado">Baixado</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Salvar Controle</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // PHP variables from the server-side
    const configForcas = <?php echo json_encode($config_forcas); ?>;
    const fabricantesEModelosControles = <?php echo json_encode($fabricantes_e_modelos_controles); ?>;
    const isAdmin = <?php echo json_encode($isAdmin); ?>;
    const isSuperAdmin = <?php echo json_encode($isSuperAdmin); ?>;
    const userForcaSeguranca = <?php echo json_encode($user_forca_seguranca); ?>;

    const forcaSegurancaSelect = document.getElementById('forca_seguranca');
    const fabricanteSelect = document.getElementById('fabricante');
    const modeloSelect = document.getElementById('modelo');
    const crbmLabel = document.getElementById('crbm_label');
    const obmLabel = document.getElementById('obm_label');
    const crbmSelect = document.getElementById('crbm');
    const obmSelect = document.getElementById('obm');
    const aeronaveSelect = document.getElementById('aeronave_id');

    // --- FUNÇÕES (permanecem inalteradas) ---
    
    function formatCrbm(crbm) {
        if (crbm && (crbm.match(/^\d+CRBM$/i) || crbm.match(/^\d+CRPM$/i))) {
            return crbm.replace(/(\d+)(CRBM|CRPM)/i, '$1º $2').toUpperCase();
        }
        return crbm;
    }

    function populateSelect(selectElement, optionsArray, placeholder, formatCallback = null) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        optionsArray.forEach(optionText => {
            const option = document.createElement('option');
            option.value = optionText;
            option.textContent = formatCallback ? formatCallback(optionText) : optionText;
            selectElement.appendChild(option);
        });
    }

    function atualizarModelos() {
        const fabricante = fabricanteSelect.value;
        modeloSelect.innerHTML = '<option value="">Selecione um modelo...</option>';
        modeloSelect.disabled = true;
        if (fabricante && fabricantesEModelosControles[fabricante]) {
            modeloSelect.disabled = false;
            populateSelect(modeloSelect, fabricantesEModelosControles[fabricante], 'Selecione um modelo...');
        }
    }
    
    function handleDynamicUnitsChange() {
        const forca = forcaSegurancaSelect.value;
        const crbm = this.value;
        const config = configForcas[forca];

        let placeholderText = `Selecione a ${config?.obm_label ?? 'Subunidade'}`;
        
        obmSelect.innerHTML = `<option value="">${placeholderText}</option>`;
        
        if (crbm && config.unidades[crbm]) {
            obmSelect.disabled = false;
            config.unidades[crbm].forEach(function(obm) {
                const option = document.createElement('option');
                option.value = obm;
                option.textContent = obm;
                obmSelect.appendChild(option);
            });
        }
    }
    
    function populateLotacao(forca, crbm, obm) {
        const config = configForcas[forca];
        
        if (config) {
            crbmLabel.textContent = config.crbm_label + ':';
            obmLabel.textContent = config.obm_label + ':';

            if (config.unidades && !config.unidades.crpms) { 
                const crbms = Object.keys(config.unidades).sort();
                populateSelect(crbmSelect, crbms, `Selecione a ${crbmLabel.textContent.replace(':', '')}`, forca === 'CBMPR' ? formatCrbm : null);
                crbmSelect.value = crbm;
                
                const obms = config.unidades[crbm] || [];
                populateSelect(obmSelect, obms, `Selecione a ${obmLabel.textContent.replace(':', '')}`);
                obmSelect.value = obm;

            } else if (config.unidades && config.unidades.crpms) { 
                const crpms = config.unidades.crpms;
                populateSelect(crbmSelect, crpms, `Selecione o ${crbmLabel.textContent.replace(':', '')}`);
                crbmSelect.value = crbm;

                const opms = config.unidades.opms_por_crpm[crbm] || [];
                populateSelect(obmSelect, opms, `Selecione a ${obmLabel.textContent.replace(':', '')}`);
                obmSelect.value = obm;
            }
        }
    }
    
    function handleForcaSegurancaChange(forcaPreSelecionada = null, isAeronaveLinked = false) {
        const forca = forcaPreSelecionada || forcaSegurancaSelect.value;
        const config = configForcas[forca];
        
        const defaultCrbmLabel = config?.crbm_label ?? 'Unidade de Lotação';
        const defaultObmLabel = config?.obm_label ?? 'Subunidade';

        crbmSelect.innerHTML = `<option value="">Selecione a ${defaultCrbmLabel}...</option>`;
        obmSelect.innerHTML = `<option value="">Selecione a ${defaultObmLabel}...</option>`;
        
        crbmSelect.disabled = true;
        obmSelect.disabled = true;

        if (config) {
            crbmLabel.textContent = config.crbm_label + ':';
            obmLabel.textContent = config.obm_label + ':';
            
            if (!isAeronaveLinked) {
                 if (config.unidades && !config.unidades.crpms) {
                    const crbms = Object.keys(config.unidades).sort();
                    populateSelect(crbmSelect, crbms, `Selecione a ${crbmLabel.textContent.replace(':', '')}`, forca === 'CBMPR' ? formatCrbm : null);
                    crbmSelect.disabled = false;
                    obmSelect.disabled = true;
                    crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                    crbmSelect.addEventListener('change', handleDynamicUnitsChange);
                } else if (config.unidades && config.unidades.crpms) {
                    populateSelect(crbmSelect, config.unidades.crpms, `Selecione o ${config.crbm_label}`);
                    crbmSelect.disabled = false;
                    obmSelect.disabled = true;
                    crbmSelect.removeEventListener('change', handleDynamicUnitsChange);
                    crbmSelect.addEventListener('change', () => {
                        const crpm = crbmSelect.value;
                        const opms = config.unidades.opms_por_crpm[crpm] || [];
                        populateSelect(obmSelect, opms, `Selecione a ${config.obm_label}`);
                        obmSelect.disabled = opms.length === 0;
                    });
                } else {
                    crbmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
                    obmSelect.innerHTML = '<option value="">Nenhuma unidade disponível</option>';
                }
            } else {
                 crbmSelect.disabled = true;
                 obmSelect.disabled = true;
            }
        }
    }

    aeronaveSelect.addEventListener('change', () => {
        const isReserva = aeronaveSelect.value === "";
        
        if (!isReserva) {
            const selectedOption = aeronaveSelect.options[aeronaveSelect.selectedIndex];
            const forca = selectedOption.getAttribute('data-forca');
            const crbm = selectedOption.getAttribute('data-crbm');
            const obm = selectedOption.getAttribute('data-obm');
            
            forcaSegurancaSelect.value = forca;
            populateLotacao(forca, crbm, obm);
            
            crbmSelect.disabled = true;
            obmSelect.disabled = true;
            forcaSegurancaSelect.disabled = true;
        } else {
            if (isAdmin && !isSuperAdmin) {
                forcaSegurancaSelect.value = userForcaSeguranca;
            } else {
                forcaSegurancaSelect.value = "";
            }
            handleForcaSegurancaChange();
            
            crbmSelect.disabled = false;
            obmSelect.disabled = false;
            forcaSegurancaSelect.disabled = (isAdmin && !isSuperAdmin);
        }
    });

    if (forcaSegurancaSelect) {
        forcaSegurancaSelect.addEventListener('change', () => handleForcaSegurancaChange());
        
        const selectedForca = forcaSegurancaSelect.value;
        if (selectedForca) {
            handleForcaSegurancaChange(selectedForca);
        }
    }

    fabricanteSelect.addEventListener('change', atualizarModelos);
    
    atualizarModelos();
});
</script>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>