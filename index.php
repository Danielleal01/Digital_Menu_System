<?php

require_once 'config/db.php';

/* =========================
   CONFIGURAÇÕES DO ESTABELECIMENTO
========================= */

$config = $pdo->query(
    "SELECT * FROM configuracoes ORDER BY id ASC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    $config = [
        'nome_estabelecimento' => 'Cardápio Digital',
        'descricao' => 'Escolha seus produtos favoritos',
        'whatsapp' => '',
        'endereco' => '',
        'horario' => '',
        'logo' => ''
    ];
}

/* =========================
   FILTROS
========================= */

$q = trim($_GET['q'] ?? '');

$cat = isset($_GET['cat'])
    ? (int) $_GET['cat']
    : 0;

/* =========================
   CATEGORIAS
========================= */

$categorias = $pdo->query(
    "SELECT * FROM categorias ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   PRODUTOS
========================= */

$sql = "
    SELECT
        p.*,
        c.nome AS categoria
    FROM produtos p
    INNER JOIN categorias c
        ON c.id = p.categoria_id
    WHERE p.ativo = 1
";

$params = [];

/* FILTRO CATEGORIA */

if ($cat > 0) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $cat;
}

/* PESQUISA */

if ($q !== '') {

    $sql .= "
        AND (
            p.nome LIKE ?
            OR p.descricao LIKE ?
            OR c.nome LIKE ?
        )
    ";

    $busca = '%' . $q . '%';

    $params[] = $busca;
    $params[] = $busca;
    $params[] = $busca;
}

$sql .= "
    ORDER BY
        c.nome,
        p.nome
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Saborê | Cardápio</title>

<link rel="preload" href="assets/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>

<!-- ?v= muda quando o arquivo muda: evita que o navegador use um CSS antigo do cache -->
<link rel="stylesheet" href="assets/css/storefront.css?v=<?= filemtime(__DIR__ . '/assets/css/storefront.css') ?>">

</head>

<body class="pagina-cardapio">

<?php
/* ---- apenas apresentação: agrupamento, sabores e dados exibíveis ---- */

/* sabores (mesma regra de antes, agora definida uma vez para a página) */
function saboresDoProduto(array $produto): array
{
    $categoria = mb_strtolower($produto['categoria'], 'UTF-8');
    $nome = mb_strtolower($produto['nome'], 'UTF-8');

    if ($categoria === 'refrigerantes' || str_contains($nome, 'refrigerante')) {
        return ['Coca-Cola', 'Fanta', 'Sprite'];
    }

    if ($categoria === 'sucos' || str_contains($nome, 'suco')) {
        return ['Laranja', 'Maracujá', 'Uva', 'Limão'];
    }

    return [];
}

/* seções por categoria só quando não há busca nem filtro */
$agrupar = $q === '' && $cat === 0;
$secoes = [];

foreach ($produtos as $produto) {
    $chave = (int) $produto['categoria_id'];
    $secoes[$chave]['nome'] = $produto['categoria'];
    $secoes[$chave]['produtos'][] = $produto;
}

uasort($secoes, fn ($a, $b) => count($b['produtos']) <=> count($a['produtos']) ?: strcmp($a['nome'], $b['nome']));

$nomeCategoriaFiltro = '';
foreach ($categorias as $categoria) {
    if ((int) $categoria['id'] === $cat) {
        $nomeCategoriaFiltro = $categoria['nome'];
    }
}

/* WhatsApp: o número de exemplo do cadastro não é exibido como contato */
$whatsappDigitos = preg_replace('/\D+/', '', $config['whatsapp'] ?? '');
$whatsappValido = strlen($whatsappDigitos) >= 10 && $whatsappDigitos !== '11999999999';

$fotosAcimaDaDobra = 4;
$indiceFoto = 0;

function cardProduto(array $produto, bool $mostrarCategoria, bool $carregarCedo, string $variante = 'horizontal'): void
{
    $sabores = saboresDoProduto($produto);
    $nome = htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8');
    $preco = number_format((float) $produto['preco'], 2, ',', '.');
    ?>
    <article
        class="produto-card card-<?= $variante === 'vertical' ? 'vertical' : 'horizontal' ?>"
        data-imagem="<?= htmlspecialchars($produto['imagem'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        data-categoria="<?= htmlspecialchars($produto['categoria'], ENT_QUOTES, 'UTF-8') ?>"
        data-descricao="<?= htmlspecialchars($produto['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        data-sabores="<?= htmlspecialchars(json_encode($sabores), ENT_QUOTES, 'UTF-8') ?>"
    >
        <div
            class="card-foto abrir-detalhes"
            role="button"
            tabindex="0"
            aria-label="Ver detalhes de <?= $nome ?>"
        >
            <?php if (!empty($produto['imagem'])): ?>
                <img
                    src="<?= htmlspecialchars($produto['imagem']) ?>"
                    alt="<?= $nome ?>"
                    width="640"
                    height="480"
                    <?= $carregarCedo ? '' : 'loading="lazy"' ?>
                    decoding="async"
                >
            <?php else: ?>
                <span class="card-sem-foto">Foto em breve</span>
            <?php endif; ?>
        </div>

        <div class="card-corpo">
            <?php if ($mostrarCategoria): ?>
                <span class="card-categoria"><?= htmlspecialchars($produto['categoria']) ?></span>
            <?php endif; ?>

            <h3 class="card-nome">
                <span class="abrir-detalhes" role="button" tabindex="0"><?= $nome ?></span>
            </h3>

            <?php if (!empty($produto['descricao'])): ?>
                <p class="card-descricao"><?= htmlspecialchars($produto['descricao']) ?></p>
            <?php endif; ?>

            <?php if (count($sabores) > 0): ?>
                <span class="card-nota">Escolha o sabor ao adicionar</span>
            <?php endif; ?>

            <div class="card-rodape">
                <strong class="card-preco">R$ <?= $preco ?></strong>

                <button
                    type="button"
                    class="btn-carrinho"
                    data-id="<?= (int) $produto['id'] ?>"
                    data-nome="<?= $nome ?>"
                    data-preco="<?= (float) $produto['preco'] ?>"
                    data-exige-sabor="<?= count($sabores) > 0 ? '1' : '0' ?>"
                    aria-label="<?= count($sabores) > 0 ? 'Escolher sabor de ' . $nome : 'Adicionar ' . $nome . ' ao carrinho' ?>"
                >
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </button>
            </div>

        </div>
    </article>
    <?php
}
?>

<a class="pular-conteudo" href="#cardapio">Ir para o cardápio</a>

<!-- =========================
     CABEÇALHO
========================= -->

<header class="site-topo">
    <div class="cardapio-container site-topo-linha">

        <a href="index.php" class="site-marca" aria-label="Saborê, página inicial">
            <img src="assets/logo-sabore.png" alt="Saborê" width="985" height="517">
        </a>

        <nav class="site-nav" aria-label="Principal">
            <a href="index.php" aria-current="page">Cardápio</a>
            <a href="acompanhar_pedido.php">Acompanhar pedido</a>
        </nav>

        <?php if (!empty($config['horario'])): ?>
            <p class="site-horario">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                <span><?= htmlspecialchars($config['horario']) ?></span>
            </p>
        <?php endif; ?>

        <button type="button" class="abrir-carrinho" id="abrirCarrinho" aria-label="Abrir carrinho">
            <span class="abrir-carrinho-icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.6l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2l1.7-7.3H6.2"/></svg>
                <span class="contador-carrinho" id="contadorCarrinho">0</span>
            </span>
            <span class="abrir-carrinho-total" id="totalCabecalho">R$ 0,00</span>
        </button>

    </div>
</header>

<!-- =========================
     DESTAQUE (só no cardápio completo)
========================= -->

<?php if ($agrupar): ?>
<section class="destaque" aria-labelledby="tituloDestaque">
    <div class="destaque-foto">
        <img src="assets/destaque-x-bacon.jpg" alt="" width="1280" height="853" fetchpriority="high">
    </div>

    <div class="cardapio-container destaque-conteudo">
        <div class="destaque-texto">
            <h1 id="tituloDestaque">Hambúrgueres, lanches e porções</h1>
            <?php if (!empty($config['descricao'])): ?>
                <p><?= htmlspecialchars($config['descricao']) ?></p>
            <?php endif; ?>
            <a href="#cardapio" class="btn-primario">Ver cardápio</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =========================
     CONTEÚDO
========================= -->

<main class="cardapio-container<?= $agrupar ? ' com-destaque' : ' sem-destaque' ?>" id="cardapio">

    <!-- BUSCA E CATEGORIAS (mesmos parâmetros de antes: q e cat) -->

    <?php $urlLimparBusca = 'index.php' . ($cat > 0 ? '?cat=' . $cat : ''); ?>

    <div class="filtros">

        <form method="GET" class="busca" role="search">
            <?php if ($cat > 0): ?>
                <input type="hidden" name="cat" value="<?= $cat ?>">
            <?php endif; ?>

            <label class="busca-campo">
                <span class="sr-only">Buscar no cardápio</span>
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                <input type="search" name="q" placeholder="Buscar no cardápio" value="<?= htmlspecialchars($q) ?>" autocomplete="off">
            </label>

            <?php if ($q !== ''): ?>
                <a href="<?= $urlLimparBusca ?>" class="busca-limpar" aria-label="Limpar busca">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </a>
            <?php endif; ?>

            <button type="submit" class="btn-primario">Buscar</button>
        </form>

        <nav class="categorias-faixa" aria-label="Categorias">
            <a
                href="index.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>"
                class="categoria-chip<?= $cat === 0 ? ' ativa' : '' ?>"
                <?= $cat === 0 ? 'aria-current="true"' : '' ?>
            >Todos</a>

            <?php foreach ($categorias as $categoria): ?>
                <a
                    href="?cat=<?= $categoria['id'] ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"
                    class="categoria-chip<?= $cat === (int) $categoria['id'] ? ' ativa' : '' ?>"
                    <?= $cat === (int) $categoria['id'] ? 'aria-current="true"' : '' ?>
                ><?= htmlspecialchars($categoria['nome']) ?></a>
            <?php endforeach; ?>
        </nav>

    </div>

    <!-- PRODUTOS -->

    <?php if (count($produtos) === 0): ?>

        <div class="estado-vazio-cardapio" role="status">
            <span class="estado-vazio-icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
            </span>
            <?php if ($q !== ''): ?>
                <h2>Nenhum produto encontrado para “<?= htmlspecialchars($q) ?>”</h2>
                <p>Verifique se o nome está escrito corretamente ou tente outra palavra.</p>
                <a href="<?= $urlLimparBusca ?>" class="btn-secundario">Limpar busca</a>
            <?php else: ?>
                <h2>Nenhum produto nesta categoria</h2>
                <p>Escolha outra categoria ou veja o cardápio completo.</p>
                <a href="index.php" class="btn-secundario">Ver cardápio completo</a>
            <?php endif; ?>
        </div>

    <?php elseif ($agrupar): ?>

        <div class="secoes-grade">
        <?php foreach ($secoes as $idCategoria => $secao): ?>
            <section class="secao-cardapio secao-n<?= min(count($secao['produtos']), 3) ?>" aria-labelledby="secao-<?= $idCategoria ?>">
                <div class="secao-topo">
                    <h2 id="secao-<?= $idCategoria ?>"><?= htmlspecialchars($secao['nome']) ?></h2>
                    <span class="secao-contagem"><?= count($secao['produtos']) ?> <?= count($secao['produtos']) === 1 ? 'item' : 'itens' ?></span>
                    <?php if (count($secao['produtos']) > 1): ?>
                        <a href="?cat=<?= $idCategoria ?>" class="secao-link">Ver só <?= htmlspecialchars($secao['nome']) ?></a>
                    <?php endif; ?>
                </div>

                <div class="grade-produtos">
                    <?php foreach ($secao['produtos'] as $produto): ?>
                        <?php cardProduto($produto, false, $indiceFoto++ < $fotosAcimaDaDobra, 'horizontal'); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
        </div>

    <?php elseif ($q !== ''): ?>

        <!-- RESULTADOS DA BUSCA -->
        <section class="secao-cardapio resultados" aria-labelledby="tituloResultado">
            <div class="secao-topo">
                <h2 id="tituloResultado">Resultados para “<?= htmlspecialchars($q) ?>”</h2>
                <span class="secao-contagem" role="status"><?= count($produtos) ?> <?= count($produtos) === 1 ? 'produto encontrado' : 'produtos encontrados' ?></span>
                <a href="<?= $urlLimparBusca ?>" class="secao-link secao-link-icone">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    Limpar busca
                </a>
            </div>

            <div class="grade-resultados">
                <?php foreach ($produtos as $produto): ?>
                    <?php cardProduto($produto, true, $indiceFoto++ < $fotosAcimaDaDobra, 'vertical'); ?>
                <?php endforeach; ?>

                <?php if (count($produtos) < 3): ?>
                    <aside class="resultados-sugestao">
                        <h3>Quer ver outras opções?</h3>
                        <p>Navegue pelas categorias acima ou veja o cardápio completo.</p>
                        <a href="index.php" class="btn-secundario">Ver cardápio completo</a>
                    </aside>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>

        <!-- CATEGORIA FILTRADA -->
        <section class="secao-cardapio" aria-labelledby="tituloCategoria">
            <div class="secao-topo">
                <h2 id="tituloCategoria"><?= htmlspecialchars($nomeCategoriaFiltro ?: 'Produtos') ?></h2>
                <span class="secao-contagem"><?= count($produtos) ?> <?= count($produtos) === 1 ? 'item' : 'itens' ?></span>
                <a href="index.php" class="secao-link">Ver cardápio completo</a>
            </div>

            <div class="grade-produtos grade-categoria">
                <?php foreach ($produtos as $produto): ?>
                    <?php cardProduto($produto, false, $indiceFoto++ < $fotosAcimaDaDobra, 'horizontal'); ?>
                <?php endforeach; ?>
            </div>
        </section>

    <?php endif; ?>

</main>

<!-- =========================
     FUNDO DO CARRINHO
========================= -->

<div
    class="fundo-carrinho"
    id="fundoCarrinho"
></div>

<!-- =========================
     CARRINHO
========================= -->

<aside
    class="carrinho-lateral"
    id="carrinhoLateral"
    aria-label="Carrinho"
>

    <div class="carrinho-topo">

        <h2>
            Seu pedido
        </h2>

        <button
            type="button"
            class="fechar-carrinho"
            id="fecharCarrinho"
            aria-label="Fechar carrinho"
        >
            ×
        </button>

    </div>

    <div
        class="carrinho-itens"
        id="carrinhoItens"
    ></div>

    <div class="carrinho-rodape">

        <div class="carrinho-total">

            <span>Total</span>

            <span id="totalCarrinho">
                R$ 0,00
            </span>

        </div>

        <button
            type="button"
            class="finalizar-pedido"
            id="finalizarPedido"
        >
            Finalizar pedido
        </button>

    </div>

</aside>

<div
    class="aviso-carrinho"
    id="avisoCarrinho"
>
    Produto adicionado ao carrinho
</div>

<!-- DETALHES DO PRODUTO -->
<div
    class="modal-pedido modal-produto"
    id="modalProduto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="detalheNome"
>
    <div class="modal-conteudo detalhe-produto">

        <button
            type="button"
            class="fechar-modal fechar-detalhe"
            id="fecharModalProduto"
            aria-label="Fechar detalhes"
        >
            ×
        </button>

        <div class="detalhe-foto" id="detalheFoto"></div>

        <div class="detalhe-info">

            <div class="produto-categoria" id="detalheCategoria"></div>

            <h2 class="produto-nome detalhe-nome" id="detalheNome"></h2>

            <p class="produto-descricao detalhe-descricao" id="detalheDescricao"></p>

            <div class="sabores-produto" id="detalheSabores" hidden>
                <span>Escolha o sabor</span>
                <div class="opcoes-sabor" id="detalheOpcoesSabor"></div>
            </div>

            <div class="detalhe-aviso" id="detalheAviso" role="alert"></div>

            <div class="detalhe-compra">

                <div class="quantidade detalhe-quantidade">
                    <button type="button" id="detalheMenos" aria-label="Diminuir quantidade">−</button>
                    <strong id="detalheQuantidade" aria-live="polite">1</strong>
                    <button type="button" id="detalheMais" aria-label="Aumentar quantidade">+</button>
                </div>

                <div class="detalhe-valores">
                    <div class="preco" id="detalhePreco">R$ 0,00</div>
                    <small id="detalhePrecoUnitario"></small>
                </div>

            </div>

            <button
                type="button"
                class="btn-carrinho detalhe-adicionar"
                id="detalheAdicionar"
            >
                Adicionar ao carrinho
            </button>

        </div>

    </div>
</div>

<!-- FINALIZAÇÃO DO PEDIDO -->
<div class="modal-pedido modal-checkout" id="modalPedido" role="dialog" aria-modal="true" aria-labelledby="tituloFinalizar">
    <div class="modal-conteudo checkout-conteudo">

        <div class="modal-topo checkout-topo">
            <div>
                <h2 id="tituloFinalizar">Finalizar pedido</h2>
                <p>Confira seus dados e confirme sua compra.</p>
            </div>
            <button type="button" class="fechar-modal" id="fecharModalPedido" aria-label="Fechar">×</button>
        </div>

        <form class="form-pedido" id="formPedido">

            <div class="checkout-grid">

                <!-- DADOS DO CLIENTE -->
                <section class="checkout-painel checkout-dados">

                    <div class="checkout-painel-topo">
                        <span class="checkout-painel-icone" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/></svg>
                        </span>
                        <div>
                            <h3>Seus dados</h3>
                            <p>Preencha as informações para que possamos atender seu pedido.</p>
                        </div>
                    </div>

                    <div class="campo">
                        <label for="nomeCliente">Nome *</label>
                        <div class="campo-icone">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/></svg>
                            <input id="nomeCliente" type="text" maxlength="150" required placeholder="Digite seu nome completo">
                        </div>
                    </div>

                    <div class="campo">
                        <label for="telefoneCliente">WhatsApp *</label>
                        <div class="campo-icone">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.6a8.6 8.6 0 0 1-12.7 7.6L3.5 20.5l1.4-4.1a8.6 8.6 0 1 1 15.6-4.8Z"/><path d="M9 8.5c.3 2.6 2.4 4.8 5 5.4l1.2-1.2 1.8.9-.3 1.4c-3.7.3-7.9-3.7-8.2-7.6l1.4-.3.9 1.8L9 8.5Z"/></svg>
                            <input id="telefoneCliente" type="tel" maxlength="30" required placeholder="(11) 99999-9999">
                        </div>
                    </div>

                    <fieldset class="campo campo-entrega">
                        <legend>Como deseja receber? *</legend>
                        <div class="opcoes-entrega">
                            <label class="opcao-entrega">
                                <input type="radio" name="tipoEntrega" value="retirada" checked>
                                <span class="opcao-icone" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 9.5 5 4.5h14l1.5 5"/><path d="M3.5 9.5a2.8 2.8 0 0 0 5.7 0 2.8 2.8 0 0 0 5.6 0 2.8 2.8 0 0 0 5.7 0"/><path d="M5 12.3v7.2h14v-7.2"/><path d="M10 19.5v-4.5h4v4.5"/></svg>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Retirar no estabelecimento</strong>
                                    <small>Busque seu pedido no local.</small>
                                </span>
                            </label>
                            <label class="opcao-entrega">
                                <input type="radio" name="tipoEntrega" value="entrega">
                                <span class="opcao-icone" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 6.5h11v10h-11z"/><path d="M13.5 9.5h4l3 3.5v3.5h-7"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>
                                </span>
                                <span class="opcao-texto">
                                    <strong>Entrega</strong>
                                    <small>Receba no seu endereço.</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="campo" id="campoEndereco" style="display:none">
                        <label for="enderecoCliente">Endereço *</label>
                        <div class="campo-icone">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                            <input id="enderecoCliente" type="text" maxlength="255" placeholder="Digite seu endereço completo">
                        </div>
                    </div>

                    <div class="campo">
                        <label for="observacoesCliente">Observações <span class="campo-opcional">(opcional)</span></label>
                        <div class="campo-icone campo-icone-area">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5h16v11H9l-5 3.5v-14.5Z"/><path d="M8 9.5h8M8 12.5h5"/></svg>
                            <textarea id="observacoesCliente" rows="3" placeholder="Ex.: sem cebola, ponto da carne..."></textarea>
                        </div>
                    </div>

                </section>

                <!-- RESUMO DO PEDIDO -->
                <aside class="checkout-painel checkout-resumo resumo-final">

                    <div class="checkout-painel-topo">
                        <span class="checkout-painel-icone" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 8h14l-1 12.5H6L5 8Z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></svg>
                        </span>
                        <div>
                            <h3>Resumo do pedido</h3>
                            <p>Confira os itens do seu pedido.</p>
                        </div>
                    </div>

                    <div class="resumo-itens" id="resumoPedido"></div>

                    <div class="resumo-valores">
                        <div class="resumo-linha"><span>Subtotal</span><span id="subtotalFinal">R$ 0,00</span></div>
                        <div class="resumo-linha resumo-total"><span>Total</span><span id="totalFinal">R$ 0,00</span></div>
                    </div>

                </aside>

            </div>

            <div class="checkout-rodape">
                <button type="submit" class="confirmar-pedido" id="confirmarPedido">Confirmar pedido</button>
                <div class="mensagem-pedido" id="mensagemPedido" role="status"></div>
                <p class="checkout-aviso">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4.5 6v5.5c0 4.6 3.2 8.2 7.5 9.5 4.3-1.3 7.5-4.9 7.5-9.5V6L12 3Z"/><path d="m9 12 2.2 2.2L15.5 10"/></svg>
                    Confira os dados antes de confirmar.
                </p>
            </div>

        </form>

    </div>
</div>

<!-- =========================
     RODAPÉ
========================= -->

<!-- BARRA DO CARRINHO (celular): aparece só com itens e some com qualquer modal aberto -->
<div class="barra-carrinho" id="barraCarrinho" hidden>
    <div class="barra-carrinho-info">
        <span id="barraQuantidade">0 itens</span>
        <strong id="barraTotal">R$ 0,00</strong>
    </div>
    <button type="button" class="btn-primario" id="abrirCarrinhoBarra">Ver carrinho</button>
</div>

<!-- =========================
     RODAPÉ
========================= -->

<footer class="site-rodape">
    <div class="cardapio-container site-rodape-linha">
        <img src="assets/logo-sabore.png" alt="Saborê" width="985" height="517" loading="lazy" class="rodape-logo">

        <ul class="rodape-info">
            <?php if (!empty($config['horario'])): ?>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>
                    <?= htmlspecialchars($config['horario']) ?>
                </li>
            <?php endif; ?>
            <?php if (!empty($config['endereco'])): ?>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                    <?= htmlspecialchars($config['endereco']) ?>
                </li>
            <?php endif; ?>
            <?php if ($whatsappValido): ?>
                <li>
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.6a8.6 8.6 0 0 1-12.7 7.6L3.5 20.5l1.4-4.1a8.6 8.6 0 1 1 15.6-4.8Z"/></svg>
                    <a href="https://wa.me/55<?= htmlspecialchars($whatsappDigitos) ?>" target="_blank" rel="noopener">WhatsApp: <?= htmlspecialchars($config['whatsapp']) ?></a>
                </li>
            <?php endif; ?>
            <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3.5h12v17l-3-2-3 2-3-2-3 2v-17Z"/><path d="M9 8.5h6M9 12h6"/></svg>
                <a href="acompanhar_pedido.php">Acompanhar um pedido</a>
            </li>
        </ul>
    </div>
</footer>

<!-- =========================
     JAVASCRIPT DO CARRINHO
========================= -->

<script>

const CHAVE_CARRINHO = 'cardapio_tcc_carrinho';

let carrinho = carregarCarrinho();

const carrinhoLateral =
    document.getElementById('carrinhoLateral');

const fundoCarrinho =
    document.getElementById('fundoCarrinho');

const carrinhoItens =
    document.getElementById('carrinhoItens');

const contadorCarrinho =
    document.getElementById('contadorCarrinho');

const totalCarrinho =
    document.getElementById('totalCarrinho');

const avisoCarrinho =
    document.getElementById('avisoCarrinho');

const totalCabecalho = document.getElementById('totalCabecalho');
const barraCarrinho = document.getElementById('barraCarrinho');
const barraQuantidade = document.getElementById('barraQuantidade');
const barraTotal = document.getElementById('barraTotal');


/* =========================
   SALVAR / CARREGAR
========================= */

function carregarCarrinho() {

    try {

        const dados =
            localStorage.getItem(CHAVE_CARRINHO);

        return dados
            ? JSON.parse(dados)
            : [];

    } catch (erro) {

        return [];

    }

}


function salvarCarrinho() {

    localStorage.setItem(
        CHAVE_CARRINHO,
        JSON.stringify(carrinho)
    );

}


/* =========================
   MOEDA
========================= */

function moeda(valor) {

    return Number(valor).toLocaleString(
        'pt-BR',
        {
            style: 'currency',
            currency: 'BRL'
        }
    );

}


/* =========================
   ABRIR / FECHAR
========================= */

function abrirCarrinho() {

    carrinhoLateral.classList.add('ativo');
    fundoCarrinho.classList.add('ativo');

}


function fecharCarrinho() {

    carrinhoLateral.classList.remove('ativo');
    fundoCarrinho.classList.remove('ativo');

}


document
    .getElementById('abrirCarrinho')
    .addEventListener(
        'click',
        abrirCarrinho
    );


document
    .getElementById('fecharCarrinho')
    .addEventListener(
        'click',
        fecharCarrinho
    );


fundoCarrinho.addEventListener(
    'click',
    fecharCarrinho
);

document.getElementById('abrirCarrinhoBarra').addEventListener('click', abrirCarrinho);

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && carrinhoLateral.classList.contains('ativo')) fecharCarrinho();
});


/* =========================
   ADICIONAR PRODUTO
========================= */

/* Caminho da foto do produto, lido do card na página (para o resumo do pedido) */
function imagemDoProduto(id) {
    const botao = document.querySelector(`.produto-card .btn-carrinho[data-id="${Number(id)}"]`);
    return botao ? (botao.closest('.produto-card').dataset.imagem || '') : '';
}

/*
 * Regra única do carrinho: usada pelo botão do card
 * e pelo modal de detalhes.
 */
function adicionarAoCarrinho(
    id,
    nome,
    preco,
    sabor,
    quantidade = 1
) {

    const existente =
        carrinho.find(
            item => item.id === id && item.sabor === sabor
        );

    if (existente) {

        existente.quantidade += quantidade;

    } else {

        carrinho.push({
            id: id,
            nome: nome,
            sabor: sabor,
            preco: preco,
            quantidade: quantidade,
            imagem: imagemDoProduto(id)   // só para exibir no resumo; não é enviado ao servidor
        });

    }

    salvarCarrinho();
    atualizarCarrinho();
    mostrarAviso();

}


document
    .querySelectorAll('.produto-card .btn-carrinho')
    .forEach(botao => {

        botao.addEventListener(
            'click',
            function () {

                const id =
                    Number(this.dataset.id);

                const nome =
                    this.dataset.nome;

                const preco =
                    Number(this.dataset.preco);

                const exigeSabor =
                    this.dataset.exigeSabor === '1';

                /* produtos com sabor: o sabor é escolhido (e validado) no modal de detalhes */
                if (exigeSabor) {
                    abrirDetalhes(this.closest('.produto-card'));
                    return;
                }

                adicionarAoCarrinho(id, nome, preco, '', 1);

            }
        );

    });


/* =========================
   DETALHES DO PRODUTO
========================= */

const modalProduto = document.getElementById('modalProduto');
const detalheFoto = document.getElementById('detalheFoto');
const detalheCategoria = document.getElementById('detalheCategoria');
const detalheNome = document.getElementById('detalheNome');
const detalheDescricao = document.getElementById('detalheDescricao');
const detalheSabores = document.getElementById('detalheSabores');
const detalheOpcoesSabor = document.getElementById('detalheOpcoesSabor');
const detalheAviso = document.getElementById('detalheAviso');
const detalheQuantidade = document.getElementById('detalheQuantidade');
const detalhePreco = document.getElementById('detalhePreco');
const detalhePrecoUnitario = document.getElementById('detalhePrecoUnitario');

let produtoDetalhe = null;
let quantidadeDetalhe = 1;
let elementoAntesDoModal = null;

function abrirDetalhes(card) {

    const botao = card.querySelector('.btn-carrinho');

    let sabores = [];

    try {
        sabores = JSON.parse(card.dataset.sabores || '[]');
    } catch (erro) {
        sabores = [];
    }

    produtoDetalhe = {
        id: Number(botao.dataset.id),
        nome: botao.dataset.nome,
        preco: Number(botao.dataset.preco),
        sabores: sabores
    };

    quantidadeDetalhe = 1;

    /* sem destaque de quantidade/preço ao abrir (ele só aparece no + e −) */
    detalheQuantidade.classList.remove('valor-atualizado');
    detalhePreco.classList.remove('valor-atualizado');

    /* Foto */
    detalheFoto.innerHTML = '';

    if (card.dataset.imagem) {
        const img = document.createElement('img');
        img.src = card.dataset.imagem;
        img.alt = produtoDetalhe.nome;
        detalheFoto.appendChild(img);
    } else {
        const semFoto = document.createElement('div');
        semFoto.className = 'sem-foto';
        semFoto.textContent = 'Foto em breve';
        detalheFoto.appendChild(semFoto);
    }

    /* Textos */
    detalheCategoria.textContent = card.dataset.categoria || '';
    detalheNome.textContent = produtoDetalhe.nome;
    detalheDescricao.textContent = card.dataset.descricao || '';
    detalheDescricao.hidden = !card.dataset.descricao;

    /* Sabores (já marca o sabor escolhido no card, se houver) */
    detalheOpcoesSabor.innerHTML = '';

    const saborNoCard =
        document.querySelector(`input[name="sabor_${produtoDetalhe.id}"]:checked`);

    sabores.forEach(sabor => {
        const label = document.createElement('label');
        const input = document.createElement('input');

        input.type = 'radio';
        input.name = 'sabor_detalhe';
        input.value = sabor;
        input.checked = saborNoCard !== null && saborNoCard.value === sabor;
        input.addEventListener('change', limparAvisoDetalhe);

        label.appendChild(input);
        label.appendChild(document.createTextNode(' ' + sabor));
        detalheOpcoesSabor.appendChild(label);
    });

    detalheSabores.hidden = sabores.length === 0;

    limparAvisoDetalhe();
    atualizarQuantidadeDetalhe();

    elementoAntesDoModal = document.activeElement;
    modalProduto.classList.add('ativo');
    document.body.classList.add('modal-aberto');
    document.getElementById('fecharModalProduto').focus();

}

function fecharDetalhes() {

    if (!modalProduto.classList.contains('ativo')) {
        return;
    }

    modalProduto.classList.remove('ativo');
    document.body.classList.remove('modal-aberto');

    if (elementoAntesDoModal) {
        elementoAntesDoModal.focus();
    }

}

function atualizarQuantidadeDetalhe() {

    detalheQuantidade.textContent = quantidadeDetalhe;
    detalhePreco.textContent = moeda(produtoDetalhe.preco * quantidadeDetalhe);
    detalhePrecoUnitario.textContent = quantidadeDetalhe > 1
        ? `${moeda(produtoDetalhe.preco)} cada`
        : '';

}

function limparAvisoDetalhe() {

    detalheAviso.textContent = '';
    detalheAviso.classList.remove('ativo');

}

/* Abrir ao clicar no card (exceto botão e opções de sabor) */
document.querySelectorAll('.produto-card').forEach(card => {

    card.addEventListener('click', e => {
        if (e.target.closest('button, input, label')) {
            return;
        }

        abrirDetalhes(card);
    });

});

/* Acesso pelo teclado na foto e no nome */
document.querySelectorAll('.abrir-detalhes').forEach(gatilho => {

    gatilho.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            abrirDetalhes(gatilho.closest('.produto-card'));
        }
    });

});

/* feedback visual ao mudar a quantidade (só aparência; os valores já foram atualizados) */
function destacarValor(elemento) {
    elemento.classList.remove('valor-atualizado');
    void elemento.offsetWidth;   // reinicia a animação em cliques seguidos
    elemento.classList.add('valor-atualizado');
}

document.getElementById('detalheMenos').addEventListener('click', () => {
    if (quantidadeDetalhe > 1) {
        quantidadeDetalhe--;
        atualizarQuantidadeDetalhe();
        destacarValor(detalheQuantidade);
        destacarValor(detalhePreco);
    }
});

document.getElementById('detalheMais').addEventListener('click', () => {
    quantidadeDetalhe++;
    atualizarQuantidadeDetalhe();
    destacarValor(detalheQuantidade);
    destacarValor(detalhePreco);
});

document.getElementById('detalheAdicionar').addEventListener('click', () => {

    let sabor = '';

    if (produtoDetalhe.sabores.length > 0) {
        const selecionado =
            detalheOpcoesSabor.querySelector('input[name="sabor_detalhe"]:checked');

        if (!selecionado) {
            detalheAviso.textContent = 'Escolha o sabor antes de adicionar ao carrinho.';
            detalheAviso.classList.add('ativo');
            return;
        }

        sabor = selecionado.value;
    }

    adicionarAoCarrinho(
        produtoDetalhe.id,
        produtoDetalhe.nome,
        produtoDetalhe.preco,
        sabor,
        quantidadeDetalhe
    );

    fecharDetalhes();

});

document.getElementById('fecharModalProduto').addEventListener('click', fecharDetalhes);

modalProduto.addEventListener('click', e => {
    if (e.target === modalProduto) fecharDetalhes();
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') fecharDetalhes();
});


/* =========================
   ALTERAR QUANTIDADE
========================= */

function alterarQuantidade(
    indice,
    quantidade
) {

    const item =
        carrinho[indice];

    if (!item) {
        return;
    }

    item.quantidade += quantidade;

    if (item.quantidade <= 0) {

        carrinho.splice(indice, 1);

    }

    salvarCarrinho();
    atualizarCarrinho();

}


/* =========================
   REMOVER
========================= */

function removerProduto(indice) {

    carrinho.splice(indice, 1);

    salvarCarrinho();
    atualizarCarrinho();

}


/* =========================
   ATUALIZAR CARRINHO
========================= */

function atualizarCarrinho() {

    carrinhoItens.innerHTML = '';

    let quantidadeTotal = 0;
    let valorTotal = 0;

    carrinho.forEach((item, indice) => {

        quantidadeTotal +=
            item.quantidade;

        valorTotal +=
            item.preco * item.quantidade;

    });


    contadorCarrinho.textContent =
        quantidadeTotal;


    totalCarrinho.textContent =
        moeda(valorTotal);

    totalCabecalho.textContent = moeda(valorTotal);
    contadorCarrinho.hidden = quantidadeTotal === 0;
    barraQuantidade.textContent = quantidadeTotal + (quantidadeTotal === 1 ? ' item' : ' itens');
    barraTotal.textContent = moeda(valorTotal);
    barraCarrinho.hidden = quantidadeTotal === 0;
    document.body.classList.toggle('tem-barra-carrinho', quantidadeTotal > 0);


    if (carrinho.length === 0) {

        carrinhoItens.innerHTML = `
            <div class="carrinho-vazio">
                <strong>Seu carrinho está vazio</strong>
                <span>Escolha um item do cardápio para começar o pedido.</span>
                <button type="button" class="btn-secundario" onclick="fecharCarrinho()">Ver cardápio</button>
            </div>
        `;

        return;

    }


    carrinho.forEach((item, indice) => {

        const elemento =
            document.createElement('div');

        elemento.className =
            'item-carrinho';


        const foto = item.imagem || imagemDoProduto(item.id);

        elemento.innerHTML = `
            <div class="item-carrinho-foto">
                ${foto ? `<img src="${escaparHTML(foto)}" alt="">` : ''}
            </div>

            <div class="item-carrinho-dados">
                <div class="item-carrinho-nome">${escaparHTML(item.nome)}</div>
                ${item.sabor ? `<div class="item-carrinho-sabor">Sabor: ${escaparHTML(item.sabor)}</div>` : ''}
                <div class="item-carrinho-unitario">${item.quantidade} × ${moeda(item.preco)}</div>

                <div class="item-carrinho-info">
                    <div class="quantidade">
                        <button type="button" onclick="alterarQuantidade(${indice}, -1)" aria-label="Diminuir quantidade de ${escaparHTML(item.nome)}">−</button>
                        <strong>${item.quantidade}</strong>
                        <button type="button" onclick="alterarQuantidade(${indice}, 1)" aria-label="Aumentar quantidade de ${escaparHTML(item.nome)}">+</button>
                    </div>
                    <strong class="item-carrinho-subtotal">${moeda(item.preco * item.quantidade)}</strong>
                </div>
            </div>

            <button type="button" class="remover-item" onclick="removerProduto(${indice})" aria-label="Remover ${escaparHTML(item.nome)} do carrinho">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
            </button>
        `;


        carrinhoItens.appendChild(
            elemento
        );

    });

}


/* =========================
   SEGURANÇA DO TEXTO
========================= */

function escaparHTML(texto) {

    const div =
        document.createElement('div');

    div.textContent = texto;

    return div.innerHTML;

}


/* =========================
   AVISO
========================= */

let tempoAviso;

function mostrarAviso() {

    avisoCarrinho.classList.add(
        'ativo'
    );

    clearTimeout(tempoAviso);

    tempoAviso =
        setTimeout(
            () => {

                avisoCarrinho
                    .classList
                    .remove('ativo');

            },
            1800
        );

}


/* =========================
   FINALIZAR PEDIDO
========================= */

const modalPedido = document.getElementById('modalPedido');
const formPedido = document.getElementById('formPedido');
const campoEndereco = document.getElementById('campoEndereco');
const enderecoCliente = document.getElementById('enderecoCliente');
const resumoPedido = document.getElementById('resumoPedido');
const subtotalFinal = document.getElementById('subtotalFinal');
const totalFinal = document.getElementById('totalFinal');
const mensagemPedido = document.getElementById('mensagemPedido');
const confirmarPedido = document.getElementById('confirmarPedido');

function abrirFinalizacao() {
    if (carrinho.length === 0) { alert('Adicione pelo menos um produto ao carrinho.'); return; }
    resumoPedido.innerHTML = '';
    let total = 0;
    carrinho.forEach(item => {
        total += item.preco * item.quantidade;

        /* Item do resumo: foto, nome, sabor, quantidade e subtotal do item */
        const linha = document.createElement('div');
        linha.className = 'resumo-item';

        const foto = document.createElement('div');
        foto.className = 'resumo-item-foto';
        const imagem = item.imagem || imagemDoProduto(item.id);
        if (imagem) {
            const img = document.createElement('img');
            img.src = imagem;
            img.alt = '';
            foto.appendChild(img);
        } else {
            foto.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12.5H6L5 8Z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></svg>';
        }

        const info = document.createElement('div');
        info.className = 'resumo-item-info';
        info.innerHTML = `<strong>${escaparHTML(item.nome)}</strong>${item.sabor ? `<small>Sabor: ${escaparHTML(item.sabor)}</small>` : ''}<span class="resumo-item-qtd">${item.quantidade}x ${moeda(item.preco)}</span>`;

        const preco = document.createElement('strong');
        preco.className = 'resumo-item-preco';
        preco.textContent = moeda(item.preco * item.quantidade);

        linha.append(foto, info, preco);
        resumoPedido.appendChild(linha);
    });
    subtotalFinal.textContent = moeda(total);
    totalFinal.textContent = moeda(total);
    mensagemPedido.className = 'mensagem-pedido';
    mensagemPedido.textContent = '';
    fecharCarrinho();
    modalPedido.classList.add('ativo');
}

function fecharFinalizacao() { modalPedido.classList.remove('ativo'); }

document.getElementById('finalizarPedido').addEventListener('click', abrirFinalizacao);
document.getElementById('fecharModalPedido').addEventListener('click', fecharFinalizacao);
modalPedido.addEventListener('click', e => { if (e.target === modalPedido) fecharFinalizacao(); });

document.querySelectorAll('input[name="tipoEntrega"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const entrega = document.querySelector('input[name="tipoEntrega"]:checked').value === 'entrega';
        campoEndereco.style.display = entrega ? 'block' : 'none';
        enderecoCliente.required = entrega;
        if (!entrega) enderecoCliente.value = '';
    });
});

formPedido.addEventListener('submit', async e => {
    e.preventDefault();
    if (carrinho.length === 0) return;
    const dados = {
        nome_cliente: document.getElementById('nomeCliente').value.trim(),
        telefone: document.getElementById('telefoneCliente').value.trim(),
        tipo_entrega: document.querySelector('input[name="tipoEntrega"]:checked').value,
        endereco: enderecoCliente.value.trim(),
        observacoes: document.getElementById('observacoesCliente').value.trim(),
        itens: carrinho.map(item => ({ id: item.id, quantidade: item.quantidade, sabor: item.sabor || '' }))
    };
    confirmarPedido.disabled = true;
    confirmarPedido.textContent = 'Enviando pedido...';
    mensagemPedido.className = 'mensagem-pedido';
    try {
        const resposta = await fetch('salvar_pedido.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(dados) });
        const resultado = await resposta.json();
        if (!resposta.ok || !resultado.sucesso) throw new Error(resultado.mensagem || 'Não foi possível realizar o pedido.');
        mensagemPedido.className = 'mensagem-pedido sucesso';
        mensagemPedido.innerHTML = `
            <strong>Pedido realizado com sucesso!</strong><br>
            Pedido nº ${resultado.pedido_id}<br>
            Total: ${moeda(resultado.total)}<br>
            <a class="link-acompanhar-sucesso" href="acompanhar_pedido.php?pedido_id=${encodeURIComponent(resultado.pedido_id)}">
                Acompanhar pedido
            </a>
        `;
        carrinho = [];
        salvarCarrinho();
        atualizarCarrinho();
        formPedido.reset();
        campoEndereco.style.display = 'none';
        enderecoCliente.required = false;
    } catch (erro) {
        mensagemPedido.className = 'mensagem-pedido erro';
        mensagemPedido.textContent = erro.message;
    } finally {
        confirmarPedido.disabled = false;
        confirmarPedido.textContent = 'Confirmar pedido';
    }
});

/* INICIALIZA */

atualizarCarrinho();

</script>

</body>

</html>
