<?php
require_once 'includes/header.php';

$mensagem_status = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $relator_tipo = htmlspecialchars($_POST['relator_tipo']);
    $data_ocorrencia = htmlspecialchars($_POST['data_ocorrencia']);
    $hora_ocorrencia = htmlspecialchars($_POST['hora_ocorrencia']);
    $envolvidos = htmlspecialchars($_POST['envolvidos']);
    $situacao = htmlspecialchars($_POST['situacao']);
    $email_contato = htmlspecialchars($_POST['email_contato']);
    $telefone_contato = htmlspecialchars($_POST['telefone_contato']);

    if (!empty($relator_tipo) && !empty($data_ocorrencia) && !empty($hora_ocorrencia) && !empty($situacao)) {
        $stmt = $conn->prepare("INSERT INTO relprev (relator_tipo, data_ocorrencia, hora_ocorrencia, envolvidos, situacao, email_contato, telefone_contato) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $relator_tipo, $data_ocorrencia, $hora_ocorrencia, $envolvidos, $situacao, $email_contato, $telefone_contato);
        
        if ($stmt->execute()) {
            $mensagem_status = "<div class='success-message-box'>RELPREV enviado com sucesso. Agradecemos sua contribuição para a segurança operacional.</div>";
            // Adiciona o script de redirecionamento após a mensagem de sucesso
            echo "<script>setTimeout(function() { window.location.href = 'dashboard.php'; }, 2000);</script>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao enviar o registro: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>Por favor, preencha todos os campos obrigatórios.</div>";
    }
}
?>

<div class="main-content">
    <h1>Registro de Prevenção (RELPREV)</h1>
    <p style="margin-bottom: 20px;">Este canal é destinado ao relato de qualquer situação que represente um risco real ou potencial à segurança das operações com drones.</p>

    <?php echo $mensagem_status; ?>

    <div class="form-container">
        <form action="relprev.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="relator_tipo">O Relator é? *</label>
                    <select id="relator_tipo" name="relator_tipo" required>
                        <option value="">Selecione uma opção</option>
                        <option value="Piloto">Piloto</option>
                        <option value="Cidadao">Cidadão</option>
                        <option value="Agente de Seg. Publica">Agente de Seg. Pública</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data_ocorrencia">Data da Ocorrência *</label>
                    <input type="date" id="data_ocorrencia" name="data_ocorrencia" required>
                </div>
                <div class="form-group">
                    <label for="hora_ocorrencia">Hora da Ocorrência (aproximada) *</label>
                    <input type="time" id="hora_ocorrencia" name="hora_ocorrencia" required>
                </div>
                <div class="form-group">
                    <label for="envolvidos">Pessoal e/ou Aeronave Envolvida</label>
                    <input type="text" id="envolvidos" name="envolvidos" placeholder="Prefixo da aeronave, nome do piloto, etc.">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="situacao">Situação (descreva detalhadamente o ocorrido) *</label>
                    <textarea id="situacao" name="situacao" rows="6" placeholder="Descreva a situação, o local, as condições e o risco identificado." required></textarea>
                </div>
                <div class="form-group">
                    <label for="email_contato">Email para Contato</label>
                    <input type="email" id="email_contato" name="email_contato" placeholder="seu_email@exemplo.com">
                </div>
                <div class="form-group">
                    <label for="telefone_contato">Telefone para Contato</label>
                    <input type="tel" id="telefone_contato" name="telefone_contato" placeholder="(XX) XXXXX-XXXX">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Enviar RELPREV</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>