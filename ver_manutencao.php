<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR OS DETALHES DA MANUTENÇÃO
$manutencao_details = null;

// Pega o ID da URL e garante que é um número
$manutencao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($manutencao_id > 0) {
    $sql_details = "SELECT 
                        m.*, 
                        a.prefixo AS aeronave_prefixo, 
                        a.modelo AS aeronave_modelo,
                        c.numero_serie AS controle_sn,
                        c.modelo AS controle_modelo
                    FROM manutencoes m 
                    LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
                    LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
                    WHERE m.id = ?";
    
    $stmt_details = $conn->prepare($sql_details);
    $stmt_details->bind_param("i", $manutencao_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();

    if ($result_details->num_rows === 1) {
        $manutencao_details = $result_details->fetch_assoc();
    }
    $stmt_details->close();
}
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h1>Detalhes da Manutenção</h1>
        <div>
            <a href="manutencao.php" style="text-decoration: none; color: #555; margin-right: 20px;"><i class="fas fa-arrow-left"></i> Voltar para o Histórico</a>
            <?php if ($isAdmin): // BOTÃO DE EDITAR SÓ APARECE PARA ADMIN ?>
                <a href="editar_manutencao.php?id=<?php echo $manutencao_id; ?>" class="action-buttons edit-btn" style="text-decoration: none;">
                    <i class="fas fa-edit"></i> Editar Manutenção
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-container">
        <?php if ($manutencao_details): ?>
            <div class="details-grid">
                <div class="detail-item">
                    <strong>Equipamento:</strong>
                    <p>
                        <?php 
                            if ($manutencao_details['equipamento_tipo'] == 'Aeronave') {
                                echo 'Aeronave: ' . htmlspecialchars($manutencao_details['aeronave_prefixo'] . ' - ' . $manutencao_details['aeronave_modelo']);
                            } else {
                                echo 'Controle: S/N ' . htmlspecialchars($manutencao_details['controle_sn'] . ' - ' . $manutencao_details['controle_modelo']);
                            }
                        ?>
                    </p>
                </div>
                <div class="detail-item">
                    <strong>Tipo de Manutenção:</strong>
                    <p><?php echo htmlspecialchars($manutencao_details['tipo_manutencao']); ?></p>
                </div>
                <div class="detail-item">
                    <strong>Data da Manutenção:</strong>
                    <p><?php echo date("d/m/Y", strtotime($manutencao_details['data_manutencao'])); ?></p>
                </div>
                <div class="detail-item">
                    <strong>Responsável:</strong>
                    <p><?php echo htmlspecialchars($manutencao_details['responsavel']); ?></p>
                </div>
                <div class="detail-item">
                    <strong>Nota Fiscal / OS:</strong>
                    <p><?php echo htmlspecialchars($manutencao_details['documento_servico'] ?? 'Não informado'); ?></p>
                </div>
                <div class="detail-item">
                    <strong>Garantia até:</strong>
                    <p><?php echo !empty($manutencao_details['garantia_ate']) ? date("d/m/Y", strtotime($manutencao_details['garantia_ate'])) : 'Não informado'; ?></p>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <strong>Descrição do Serviço:</strong>
                    <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($manutencao_details['descricao']); ?></p>
                </div>
                <div class="detail-item">
                    <strong>Data do Registro:</strong>
                    <p><?php echo date("d/m/Y H:i:s", strtotime($manutencao_details['data_registro'])); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="error-message-box">
                Registro de manutenção não encontrado.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    .detail-item {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        border-left: 4px solid #3498db;
    }
    .detail-item strong {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-size: 0.9em;
    }
    .detail-item p {
        margin: 0;
        font-size: 1.1em;
        color: #333;
    }
</style>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>