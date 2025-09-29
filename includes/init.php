<?php
// Inicia a sessão em todas as páginas se ainda não foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. INCLUI A CONEXÃO COM O BANCO DE DADOS
require_once 'database.php';

// 2. DEFINIÇÃO DE PERFIS DE USUÁRIO
$isSuperAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'super_administrador';
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
$isPiloto = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'piloto';

// 3. OBTÉM A FORÇA DE SEGURANÇA DO USUÁRIO LOGADO
$user_forca_seguranca = $_SESSION['forca_seguranca'] ?? '';