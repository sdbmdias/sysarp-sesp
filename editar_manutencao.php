<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA DA PÁGINA
$mensagem_status = "";
$manutencao_details = null;
$aeronaves_selecao = [];
$controles_selecao = [];

$manutencao_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['manutencao_id']) ? intval($_POST['manutencao_id']) : 0);

// --- Lógica para buscar aeronaves e controles para os dropdowns (Admin vê todos) ---
$sql_aeronaves = "SELECT id, prefixo, modelo FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);
while($row = $result_aeronaves->fetch_assoc()) { $aeronaves_selecao[] = $row; }

$sql_controles = "SELECT c.id, c.numero_serie, c.modelo, a.prefixo AS aeronave_vinculada FROM controles c LEFT JOIN aeronaves a ON c.aeronave_id = a.id ORDER BY c.id ASC";
$result_controles = $conn->query($sql_controles);
while($row = $result_controles->fetch_assoc()) { $controles_selecao[] = $row; }

// --- Processar o formulário de ATUALIZAÇÃO ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && $manutencao_id) {
    $equipamento_tipo = htmlspecialchars($_POST['equipamento_tipo']);
    $equipamento_id = intval($_POST['equipamento_id']);
    $tipo_manutencao = htmlspecialchars($_POST['tipo_manutencao']);
    $data_manutencao = htmlspecialchars($_POST['data_manutencao']);
    $documento_servico = !empty($_POST['documento_servico']) ? htmlspecialchars($_POST['documento_servico']) : NULL;
    $responsavel = htmlspecialchars($_POST['responsavel']);
    $garantia_ate = !empty($_POST['garantia_ate']) ? htmlspecialchars($_POST['garantia_ate']) : NULL;
    $descricao = htmlspecialchars($_POST['descricao']);

    $stmt = $conn->prepare("UPDATE manutencoes SET equipamento_id=?, equipamento_tipo=?, tipo_manutencao=?, data_manutencao=?, documento_servico=?, responsavel=?, garantia_ate=?, descricao=? WHERE id = ?");
    $stmt->bind_param("isssssssi", $equipamento_id, $equipamento_tipo, $tipo_manutencao, $data_manutencao, $documento_servico, $responsavel, $garantia_ate, $descricao, $manutencao_id);

    if ($stmt->execute()) {
        $mensagem_status = "<div class='success-message-box'>Manutenção atualizada com sucesso! Redirecionando...</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao atualizar manutenção: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
}

// --- Carregar os dados da manutenção para preencher o formulário ---
if ($manutencao_id > 0) {
    $stmt_load = $conn->prepare("SELECT * FROM manutencoes WHERE id = ?");
    $stmt_load->bind_param("i", $manutencao_id);
    $stmt_load->execute();
    $result = $stmt_load->get_result();
    if ($result->num_rows === 1) {
        $manutencao_details = $result->fetch_assoc();
    } else {
        $mensagem_status = "<div class='error-message-box'>Registro de manutenção não encontrado.</div>";
    }
    $stmt_load->close();
} else {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        $mensagem_status = "<div class='error-message-box'>ID da manutenção não fornecido para edição.</div>";
    }
}
?>

<div class="main-content">
    <h1>Editar Manutenção</h1>
    <?php echo $mensagem_status; ?>

    <?php if ($manutencao_details): ?>
    <div class="form-container">
        <form id="editManutencaoForm" action="editar_manutencao.php?id=<?php echo $manutencao_id; ?>" method="POST">
            <input type="hidden" name="manutencao_id" value="<?php echo $manutencao_id; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="equipamento_tipo">Tipo de Equipamento:</label>
                    <select id="equipamento_tipo" name="equipamento_tipo" required>
                        <option value="Aeronave" <?php echo ($manutencao_details['equipamento_tipo'] == 'Aeronave') ? 'selected' : ''; ?>>Aeronave</option>
                        <option value="Controle" <?php echo ($manutencao_details['equipamento_tipo'] == 'Controle') ? 'selected' : ''; ?>>Controle</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="equipamento_id">Equipamento Específico:</label>
                    <select id="equipamento_id" name="equipamento_id" required></select>
                </div>
                <div class="form-group">
                    <label for="tipo_manutencao">Tipo de Manutenção:</label>
                    <select id="tipo_manutencao" name="tipo_manutencao" required>
                        <option value="Preventiva" <?php echo ($manutencao_details['tipo_manutencao'] == 'Preventiva') ? 'selected' : ''; ?>>Preventiva</option>
                        <option value="Reparadora" <?php echo ($manutencao_details['tipo_manutencao'] == 'Reparadora') ? 'selected' : ''; ?>>Reparadora</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="responsavel">Empresa ou Militar Responsável:</label>
                    <input type="text" id="responsavel" name="responsavel" value="<?php echo htmlspecialchars($manutencao_details['responsavel']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="data_manutencao">Data da Manutenção:</label>
                    <input type="date" id="data_manutencao" name="data_manutencao" value="<?php echo htmlspecialchars($manutencao_details['data_manutencao']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="garantia_ate">Garantia do Serviço até (Opcional):</label>
                    <input type="date" id="garantia_ate" name="garantia_ate" value="<?php echo htmlspecialchars($manutencao_details['garantia_ate'] ?? ''); ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="documento_servico">Nota Fiscal / Ordem de Serviço (Opcional):</label>
                    <input type="text" id="documento_servico" name="documento_servico" value="<?php echo htmlspecialchars($manutencao_details['documento_servico'] ?? ''); ?>">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="descricao">Descrição do Serviço Realizado:</label>
                    <textarea id="descricao" name="descricao" rows="5" required><?php echo htmlspecialchars($manutencao_details['descricao']); ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" style="background-color:#007bff;">Atualizar Manutenção</button>
            </div>
        </form>
    </div>
    <?php else: ?>
        <p style="text-align: center; color: #dc3545;">Não foi possível carregar os dados da manutenção.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('editManutencaoForm')) {
        const equipamentos = {
            Aeronave: <?php echo json_encode($aeronaves_selecao); ?>,
            Controle: <?php echo json_encode($controles_selecao); ?>
        };
        
        const valorSalvo = {
            tipo: "<?php echo addslashes($manutencao_details['equipamento_tipo'] ?? ''); ?>",
            id: "<?php echo addslashes($manutencao_details['equipamento_id'] ?? ''); ?>"
        };

        const tipoEquipamentoSelect = document.getElementById('equipamento_tipo');
        const equipamentoIdSelect = document.getElementById('equipamento_id');

        function popularEquipamentos() {
            const tipoSelecionado = tipoEquipamentoSelect.value;
            equipamentoIdSelect.innerHTML = ''; // Limpa o select

            if (tipoSelecionado && equipamentos[tipoSelecionado]) {
                equipamentos[tipoSelecionado].forEach(function(item) {
                    const option = document.createElement('option');
                    option.value = item.id;
                    
                    let textoExibicao = '';
                    if (tipoSelecionado === 'Aeronave') {
                        textoExibicao = `${item.prefixo} - ${item.modelo}`;
                    } else {
                        const aeronaveVinculada = item.aeronave_vinculada || 'Reserva';
                        textoExibicao = `${aeronaveVinculada} - ${item.modelo} - S/N: ${item.numero_serie}`;
                    }
                    option.textContent = textoExibicao;
                    equipamentoIdSelect.appendChild(option);
                });
                
                // Se o tipo selecionado for o mesmo que o salvo, seleciona o ID correto
                if (tipoSelecionado === valorSalvo.tipo) {
                    equipamentoIdSelect.value = valorSalvo.id;
                }
            }
        }

        tipoEquipamentoSelect.addEventListener('change', popularEquipamentos);
        
        // Carga inicial do formulário
        popularEquipamentos();
    }

    const successMessage = document.querySelector('.success-message-box');
    if (successMessage) {
        setTimeout(function() {
            // Redireciona de volta para a página de detalhes da manutenção que acabou de ser editada
            window.location.href = 'ver_manutencao.php?id=<?php echo $manutencao_id; ?>';
        }, 2000); // 2 segundos
    }
});
</script>

<?php
// 6. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>