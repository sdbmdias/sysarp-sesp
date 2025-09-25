<?php
// Inicia a sessão para poder manipulá-la.
session_start();

// 1. Limpa todas as variáveis da sessão.
$_SESSION = array();

// 2. Destrói a sessão.
session_destroy();

// 3. Redireciona o usuário para a página de login (index.php).
header("Location: index.php");
exit();
?>