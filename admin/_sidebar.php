<?php
/*
 * Sidebar única do painel administrativo.
 * Uso: defina $paginaAtiva ('dashboard', 'produtos', 'categorias',
 * 'configuracoes' ou 'pedidos') e faça require deste arquivo.
 */

// Este arquivo é só um trecho de página: não deve ser aberto diretamente
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    http_response_code(404);
    exit;
}

$paginaAtiva = $paginaAtiva ?? '';

$itensMenu = [
    'dashboard'     => ['index.php',         'ic-inicio',     'Dashboard'],
    'produtos'      => ['produtos.php',       'ic-caixa',      'Produtos'],
    'categorias'    => ['categorias.php',    'ic-grade',      'Categorias'],
    'configuracoes' => ['configuracoes.php', 'ic-engrenagem', 'Configurações'],
    'pedidos'       => ['pedidos.php',       'ic-recibo',     'Pedidos'],
];
?>
<aside class="sidebar">

    <div class="logo">
        <img src="../assets/logo-sabore.png" alt="Saborê" width="140" height="73">
    </div>

    <div class="logo-sub">
        Painel Administrativo
    </div>

    <nav class="menu">

        <?php foreach ($itensMenu as $chave => [$link, $icone, $rotulo]): ?>
            <a href="<?= $link ?>"<?= $chave === $paginaAtiva ? ' class="ativo" aria-current="page"' : '' ?>>
                <i class="ic <?= $icone ?>" aria-hidden="true"></i>
                <?= $rotulo ?>
            </a>
        <?php endforeach; ?>

        <a href="../index.php" target="_blank">
            <i class="ic ic-externo" aria-hidden="true"></i>
            Ver Cardápio
        </a>

    </nav>

    <div class="sair">

        <a href="logout.php">
            <i class="ic ic-sair" aria-hidden="true"></i>
            Sair
        </a>

    </div>

</aside>

<script>
/* No celular o menu rola na horizontal: mostra o item da página atual ao abrir */
(() => {
    const menu = document.querySelector('.sidebar .menu');
    const ativo = menu && menu.querySelector('.ativo');
    if (ativo && menu.scrollWidth > menu.clientWidth) {
        const deslocamento = ativo.getBoundingClientRect().left - menu.getBoundingClientRect().left + menu.scrollLeft;
        menu.scrollLeft = deslocamento - (menu.clientWidth - ativo.offsetWidth) / 2;
    }
})();
</script>
