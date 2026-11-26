<?php

session_start();

require_once __DIR__ . '/../config/db.php';

// Se já estiver logado, vai direto para o painel
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {

        $erro = 'Preencha o e-mail e a senha.';

    } else {

        try {

            // Procura o administrador pelo e-mail
            $stmt = $pdo->prepare(
                "SELECT id, nome, email, senha
                 FROM usuarios
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->execute([$email]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica a senha criptografada
            if ($usuario && password_verify($senha, $usuario['senha'])) {

                // Regenera o ID da sessão por segurança
                session_regenerate_id(true);

                $_SESSION['usuario'] = $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['email'] = $usuario['email'];

                header('Location: index.php');
                exit;

            } else {

                $erro = 'E-mail ou senha inválidos.';

            }

        } catch (PDOException $e) {

            $erro = 'Erro ao acessar o banco de dados.';

        }
    }
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login - Painel Administrativo</title>

    <!-- ?v= muda quando o arquivo muda: evita que o navegador use um CSS antigo do cache -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

</head>

<body class="pagina-login">

<main class="login-pagina">

    <section class="login-card">

        <img
            src="../assets/logo-sabore.png"
            alt="Saborê"
            class="login-logo"
            width="250"
            height="131"
        >

        <h1>Bem-vindo de volta</h1>

        <p class="login-subtitulo">
            Acesse o painel administrativo para gerenciar seu cardápio.
        </p>

        <?php if ($erro !== ''): ?>

            <div class="erro login-erro" role="alert">

                <?= htmlspecialchars($erro) ?>

            </div>

        <?php endif; ?>

        <form method="POST" class="login-form">

            <label for="email">
                E-mail
            </label>

            <div class="login-campo">
                <svg class="login-campo-icone" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="2.5"/>
                    <path d="m4 7 8 6 8-6"/>
                </svg>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Digite seu e-mail"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <label for="senha">
                Senha
            </label>

            <div class="login-campo login-campo-senha">
                <svg class="login-campo-icone" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/>
                    <path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>
                </svg>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="Digite sua senha"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="login-olho"
                    id="mostrarSenha"
                    aria-controls="senha"
                    aria-pressed="false"
                    aria-label="Mostrar senha"
                >
                    <svg class="olho-aberto" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="olho-fechado" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3l18 18"/>
                        <path d="M10.6 5.6A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.4 16.4 0 0 1-3.1 3.9M6.3 6.9C3.9 8.6 2.5 12 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4.4-1.1"/>
                        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                    </svg>
                </button>
            </div>

            <button type="submit" class="login-entrar">
                Entrar
                <svg class="login-entrar-seta" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6"/>
                </svg>
            </button>

        </form>

        <div class="login-divisor" aria-hidden="true">ou</div>

        <a href="../index.php" class="login-voltar">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M19 12H5M11 6l-6 6 6 6"/>
            </svg>
            Voltar para o cardápio
        </a>

    </section>

</main>

<script>
/* Mostrar / ocultar senha (apenas visual; não altera o envio do formulário) */
const campoSenha = document.getElementById('senha');
const botaoOlho = document.getElementById('mostrarSenha');

botaoOlho.addEventListener('click', () => {
    const mostrar = campoSenha.type === 'password';
    campoSenha.type = mostrar ? 'text' : 'password';
    botaoOlho.setAttribute('aria-pressed', String(mostrar));
    botaoOlho.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
    campoSenha.focus();
});
</script>

</body>

</html>
