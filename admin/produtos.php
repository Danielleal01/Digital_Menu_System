<?php

require_once 'auth.php';

/* =========================
   FILTROS (busca por nome, categoria e status)
========================= */

$busca = trim($_GET['q'] ?? '');
$categoriaFiltro = isset($_GET['categoria']) ? (int) $_GET['categoria'] : 0;
$statusFiltro = $_GET['status'] ?? '';

if (!in_array($statusFiltro, ['', 'ativo', 'inativo'], true)) {
    $statusFiltro = '';
}

$filtrando = $busca !== '' || $categoriaFiltro > 0 || $statusFiltro !== '';

/* =========================
   CONSULTAS (somente leitura)
========================= */

$categorias = $pdo->query(
    "SELECT id, nome FROM categorias ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$totalProdutos = (int) $pdo->query(
    "SELECT COUNT(*) FROM produtos"
)->fetchColumn();

$sql = "
    SELECT
        p.*,
        c.nome AS categoria
    FROM produtos p
    INNER JOIN categorias c
        ON c.id = p.categoria_id
    WHERE 1 = 1
";

$parametros = [];

if ($busca !== '') {
    $sql .= " AND p.nome LIKE ?";
    $parametros[] = '%' . $busca . '%';
}

if ($categoriaFiltro > 0) {
    $sql .= " AND p.categoria_id = ?";
    $parametros[] = $categoriaFiltro;
}

if ($statusFiltro !== '') {
    $sql .= " AND p.ativo = ?";
    $parametros[] = $statusFiltro === 'ativo' ? 1 : 0;
}

$sql .= " ORDER BY p.nome";

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Saborê</title>
    <!-- ?v= muda quando o arquivo muda: evita CSS antigo do cache -->
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>

<body class="pagina-admin pagina-produtos">

<?php $paginaAtiva = 'produtos'; require __DIR__ . '/_sidebar.php'; ?>

<main class="main">

    <div class="topo">

        <div>
            <h1>Produtos</h1>
            <p>Gerencie os itens do seu cardápio.</p>
        </div>

        <a href="produto.php" class="novo">
            <i class="ic ic-mais" aria-hidden="true"></i>
            Novo Produto
        </a>

    </div>

    <!-- BUSCA E FILTROS -->

    <form method="GET" class="produtos-filtros" role="search">

        <label class="filtro-campo filtro-busca">
            <span class="sr-only">Buscar produto pelo nome</span>
            <i class="ic ic-busca" aria-hidden="true"></i>
            <input
                type="text"
                name="q"
                value="<?= htmlspecialchars($busca) ?>"
                placeholder="Buscar produto pelo nome..."
            >
        </label>

        <label class="filtro-campo">
            <span class="sr-only">Filtrar por categoria</span>
            <i class="ic ic-etiqueta" aria-hidden="true"></i>
            <select name="categoria">
                <option value="0">Todas as categorias</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= (int) $categoria['id'] ?>" <?= $categoriaFiltro === (int) $categoria['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($categoria['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="filtro-campo">
            <span class="sr-only">Filtrar por status</span>
            <i class="ic ic-circulo" aria-hidden="true"></i>
            <select name="status">
                <option value="" <?= $statusFiltro === '' ? 'selected' : '' ?>>Todos os status</option>
                <option value="ativo" <?= $statusFiltro === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                <option value="inativo" <?= $statusFiltro === 'inativo' ? 'selected' : '' ?>>Inativos</option>
            </select>
        </label>

        <button type="submit" class="btn-filtrar">
            <i class="ic ic-filtro" aria-hidden="true"></i>
            Filtrar
        </button>

    </form>

    <!-- PRODUTOS -->

    <?php if ($totalProdutos === 0): ?>

        <div class="estado-vazio">
            <div class="estado-vazio-icone" aria-hidden="true"><i class="ic ic-caixa"></i></div>
            <h2>Nenhum produto cadastrado</h2>
            <p>Cadastre o primeiro item do cardápio para que ele apareça aqui.</p>
        </div>

    <?php elseif (count($produtos) === 0): ?>

        <div class="estado-vazio">
            <div class="estado-vazio-icone" aria-hidden="true"><i class="ic ic-busca"></i></div>
            <h2>Nenhum produto encontrado</h2>
            <p>Nenhum produto corresponde à busca ou aos filtros escolhidos.</p>
            <a href="produtos.php" class="limpar-filtros">Limpar filtros</a>
        </div>

    <?php else: ?>

        <div class="produtos-grade">

            <?php foreach ($produtos as $produto): ?>

                <article class="produto-cartao<?= $produto['ativo'] ? '' : ' inativo' ?>">

                    <div class="produto-cartao-foto">

                        <?php if (!empty($produto['imagem'])): ?>
                            <img src="../<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="produto-cartao-sem-foto">
                                <i class="ic ic-caixa" aria-hidden="true"></i>
                                Sem imagem
                            </div>
                        <?php endif; ?>

                        <?php if ($produto['ativo']): ?>
                            <span class="status ativo-status">Ativo</span>
                        <?php else: ?>
                            <span class="status inativo-status">Inativo</span>
                        <?php endif; ?>

                    </div>

                    <div class="produto-cartao-corpo">

                        <span class="categoria-pill">
                            <i class="ic ic-etiqueta" aria-hidden="true"></i>
                            <?= htmlspecialchars($produto['categoria']) ?>
                        </span>

                        <h3 title="<?= htmlspecialchars($produto['nome']) ?>"><?= htmlspecialchars($produto['nome']) ?></h3>

                        <p class="produto-cartao-descricao">
                            <?= htmlspecialchars($produto['descricao'] ?? '') ?>
                        </p>

                        <div class="produto-cartao-preco">
                            <span>Preço</span>
                            <strong>R$ <?= number_format((float) $produto['preco'], 2, ',', '.') ?></strong>
                        </div>

                        <div class="produto-cartao-acoes">
                            <a href="produto.php?id=<?= (int) $produto['id'] ?>" class="editar">
                                <i class="ic ic-lapis" aria-hidden="true"></i>
                                Editar
                            </a>
                            <a
                                href="excluir_produto.php?id=<?= (int) $produto['id'] ?>"
                                class="excluir"
                                onclick="return confirm('Deseja realmente excluir este produto?')"
                            >
                                <i class="ic ic-lixeira" aria-hidden="true"></i>
                                Excluir
                            </a>
                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <div class="produtos-rodape">
        <?php if ($filtrando): ?>
            <span><?= count($produtos) ?> de <?= $totalProdutos ?> produto<?= $totalProdutos !== 1 ? 's' : '' ?></span>
            <a href="produtos.php" class="limpar-filtros">Limpar filtros</a>
        <?php else: ?>
            <span><?= $totalProdutos ?> produto<?= $totalProdutos !== 1 ? 's' : '' ?> cadastrado<?= $totalProdutos !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>

</main>

</body>
</html>
