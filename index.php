<?php
session_start(); // Inicia a sessão para armazenar informações do usuário

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

$login_error = ""; // Variável para armazenar mensagens de erro de login

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_nome_usuario = htmlspecialchars($_POST['nome_usuario']);
    $input_password = $_POST['password'];

    // Simples validação para evitar processamento desnecessário
    if (empty($input_nome_usuario) || empty($input_password)) {
        $login_error = "Nome de Usuário e Senha são campos obrigatórios.";
    } else {
        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("Falha na conexão com o banco de dados: " . $conn->connect_error);
        }

        // Query para buscar o piloto pelo Nome de Usuário
        $stmt = $conn->prepare("SELECT id, nome_completo, senha, status_piloto, tipo_usuario, senha_redefinida, forca_seguranca FROM pilotos WHERE nome_usuario = ?");
        $stmt->bind_param("s", $input_nome_usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $piloto = $result->fetch_assoc();
            
            if (password_verify($input_password, $piloto['senha'])) {
                if ($piloto['status_piloto'] == 'ativo') {
                    
                    $update_login_stmt = $conn->prepare("UPDATE pilotos SET ultimo_acesso = NOW() WHERE id = ?");
                    $update_login_stmt->bind_param("i", $piloto['id']);
                    $update_login_stmt->execute();
                    $update_login_stmt->close();

                    $_SESSION['user_id'] = $piloto['id'];
                    $_SESSION['user_name'] = $piloto['nome_completo'];
                    $_SESSION['user_type'] = $piloto['tipo_usuario'];
                    $_SESSION['forca_seguranca'] = $piloto['forca_seguranca'];
                    
                    if ($piloto['senha_redefinida'] == 0) {
                        $_SESSION['force_password_reset'] = true;
                        header("Location: primeiro_acesso.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();

                } else {
                    $login_error = "Sua conta está " . htmlspecialchars($piloto['status_piloto']) . ". Entre em contato com o administrador.";
                }
            } else {
                $login_error = "Credenciais inválidas. Verifique seu nome de usuário e senha.";
            }
        } else {
            $login_error = "Credenciais inválidas. Verifique seu nome de usuário e senha.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYSARP - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        /* Estilos específicos para index.php que NÃO estão no main.css */
        .image-section {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .image-section .img-left {
            flex: 1;
            background-size: cover;
            background-position: center;
        }

        .login-section {
            flex: 2;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
            box-sizing: border-box;
            position: relative;
            background: url('img/direita-unica.jpg') center center/cover no-repeat;
        }

        .login-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--color-content-bg-translucent-light); /* Usando variável CSS */
            z-index: 1;
        }
        
        .login-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 400px;
            background-color: var(--color-content-bg-translucent-heavy); /* Usando variável CSS */
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 8px 30px var(--color-box-shadow-medium); /* Usando variável CSS */
            text-align: center;
        }

        .logo-container {
            margin-bottom: 25px;
        }

        .logo-container img {
            height: 140px;
            margin-bottom: 10px;
        }

        .logo-text {
            font-size: 2.8em;
            font-weight: bold;
            color: var(--color-primary); /* Usando variável CSS */
            letter-spacing: 2px;
        }

        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-medium); /* Usando variável CSS */
        }
        
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-with-icon .icon {
            position: absolute;
            left: 15px;
            color: var(--color-text-light); /* Usando variável CSS */
        }

        .input-group input {
            padding-left: 45px; /* Ajusta o padding para o ícone */
        }
        /* Remover:
        .input-group input:focus {
            outline: none;
            border-color: var(--color-primary);
        }
        Está no main.css de forma global
        */

        .btn-login {
            width: 100%;
            margin-top: 10px;
        }
        /* Remover:
        .btn-login:hover {
            background-color: var(--color-primary-hover);
        }
        Está no main.css de forma global
        */
        
        .forgot-password-link {
            display: block;
            margin-top: 20px;
            font-size: 0.9em;
            color: var(--color-text-medium); /* Usando variável CSS */
        }
        /* Remover:
        .forgot-password-link:hover {
            text-decoration: underline;
        }
        Está no main.css de forma global
        */

        .error-message {
            /* Mudar:
            background-color: #f8d7da;
            color: #721c24;
            Para usar variáveis CSS */
            background-color: var(--color-error-background);
            color: var(--color-error-text);
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .org-text {
            margin-top: 25px;
            font-size: 0.8em;
            color: var(--color-text-lighter); /* Usando variável CSS */
            line-height: 1.4;
        }
        
        /* ########## MEDIA QUERIES PARA RESPONSIVIDADE ########## */

        @media (max-width: 900px) {
            .image-section {
                display: none; 
            }
            .login-section {
                flex: 1; 
                padding: 20px; 
            }
        }
        
        @media (max-width: 480px) {
            .login-content {
                padding: 25px; 
            }
            .logo-container img {
                height: 100px; 
            }
            .logo-text {
                font-size: 2.2em; 
            }
            .btn-login {
                padding: 12px;
                font-size: 1em;
            }
        }
    </style>
</head>
<body>

    <div class="image-section">
        <div class="img-left" style="background-image: url('img/esquerda-superior.jpg');"></div>
        <div class="img-left" style="background-image: url('img/esquerda-inferior.jpg');"></div>
    </div>

    <div class="login-section">
        <div class="login-overlay"></div>
        <div class="login-content">
            <div class="logo-container">
                <img src="img/logo_soarp.png" alt="Logo SYSARP">
                <div class="logo-text">SYSARP</div>
            </div>

            <form action="index.php" method="POST">
                
                <?php if (!empty($login_error)): ?>
                    <div class="error-message"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <div class="input-group">
                    <label for="nome_usuario">Nome de Usuário</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user icon"></i>
                        <input type="text" id="nome_usuario" name="nome_usuario" placeholder="Seu nome de usuário" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Senha</label>
                     <div class="input-with-icon">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" id="password" name="password" placeholder="Sua senha" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">ENTRAR</button>
                
                <a href="esqueci_senha.php" class="forgot-password-link">Esqueceu sua senha?</a>

                <div class="org-text">
                    Seção Operacional de Aeronaves Remotamente Pilotadas - SOARP/CBMPR
                </div>
                
            </form>
        </div>
    </div>
</body>
</html>