<?php
require_once 'includes/header.php';

if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit();
}

// Lógica para listar as unidades cadastradas
$unidades = $conn->query("
    SELECT u1.*, u2.nome_unidade AS nome_pai 
    FROM unidades u1 
    LEFT JOIN unidades u2 ON u1.unidade_pai_id = u2.id 
    ORDER BY u1.forca_sigla, u1.unidade_pai_id, u1.nome_unidade ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="main-content">
    <h1>Gerenciar Unidades das Forças de Segurança</h1>
    <p>Aqui você pode adicionar, editar ou remover as unidades (CRBMs, OPMs, etc.) que são usadas nos formulários de cadastro.</p>
    
    <div class="form-container" style="margin-bottom: 30px;">
        <h2>Adicionar/Editar Unidade</h2>
        <p><i>(A funcionalidade de adicionar/editar unidades no banco de dados será implementada aqui.)</i></p>
    </div>

    <div class="table-container">
        <h2>Unidades Cadastradas</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Força</th>
                    <th>Unidade Superior (Pai)</th>
                    <th>Nome da Unidade</th>
                    <th style="width: 150px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($unidades)): ?>
                    <?php foreach ($unidades as $unidade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($unidade['forca_sigla']); ?></td>
                            <td><?php echo htmlspecialchars($unidade['nome_pai'] ?? 'N/A (Nível Superior)'); ?></td>
                            <td><?php echo htmlspecialchars($unidade['nome_unidade']); ?></td>
                            <td class="action-buttons">
                                <a href="#" class="edit-btn">Editar</a>
                                <a href="#" class="edit-btn" style="background-color:#dc3545;">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Nenhuma unidade cadastrada. Execute o script de migração.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>