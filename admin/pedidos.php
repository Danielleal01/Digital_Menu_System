<?php

require_once 'auth.php';
require_once '../config/db.php';

/* =========================
   ALTERAR STATUS
========================= */

$mensagem = '';

$statusPermitidos = [
    'Recebido',
    'Em preparação',
    'Pronto',
    'Saiu para entrega',
    'Finalizado'
];

/* "Saiu para entrega" só faz sentido em pedidos com entrega */
function statusDisponivel(string $status, string $tipoEntrega): bool
{
    return $status !== 'Saiu para entrega' || $tipoEntrega === 'entrega';
}

function classeStatusPedido(string $status): string
{
    if ($status === 'Em preparação' || str_starts_with($status, 'Em prepara')) {
        return 'status-preparacao';
    }

    if ($status === 'Pronto') {
        return 'status-pronto';
    }

    if ($status === 'Saiu para entrega') {
        return 'status-entrega';
    }

    if ($status === 'Finalizado') {
        return 'status-finalizado';
    }

    return 'status-recebido';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pedidoId = (int)($_POST['pedido_id'] ?? 0);
    $novoStatus = trim($_POST['status'] ?? '');

    $stmtTipo = $pdo->prepare(
        "SELECT tipo_entrega FROM pedidos WHERE id = ?"
    );

    $stmtTipo->execute([$pedidoId]);

    $tipoEntregaPedido = (string) $stmtTipo->fetchColumn();

    if (
        $pedidoId > 0 &&
        in_array($novoStatus, $statusPermitidos, true) &&
        statusDisponivel($novoStatus, $tipoEntregaPedido)
    ) {

        $stmt = $pdo->prepare(
            "UPDATE pedidos SET status = ? WHERE id = ?"
        );

        $stmt->execute([
            $novoStatus,
            $pedidoId
        ]);

        $mensagem = 'Status atualizado com sucesso!';
    }
}


/* =========================
   FILTROS (busca, status e período)
========================= */

$busca = trim($_GET['q'] ?? '');

$statusFiltro = $_GET['status'] ?? '';
if (!in_array($statusFiltro, $statusPermitidos, true)) {
    $statusFiltro = '';
}

$periodos = [
    ''     => 'Todo o período',
    'hoje' => 'Hoje',
    '7d'   => 'Últimos 7 dias',
    '30d'  => 'Últimos 30 dias'
];

$periodo = $_GET['periodo'] ?? '';
if (!array_key_exists($periodo, $periodos)) {
    $periodo = '';
}

/* condições fixas (sem dados do usuário) para o período */
$condicaoPeriodo = match ($periodo) {
    'hoje' => 'DATE(criado_em) = CURDATE()',
    '7d'   => 'criado_em >= NOW() - INTERVAL 7 DAY',
    '30d'  => 'criado_em >= NOW() - INTERVAL 30 DAY',
    default => '1 = 1'
};

/* busca por número do pedido (12 ou #12) ou nome do cliente, sempre com parâmetros */
$condicaoBusca = '1 = 1';
$parametrosBusca = [];

if ($busca !== '') {
    $termo = ltrim($busca, '#');

    if ($termo !== '' && ctype_digit($termo)) {
        $condicaoBusca = '(id = ? OR nome_cliente LIKE ?)';
        $parametrosBusca = [(int) $termo, '%' . $termo . '%'];
    } else {
        $condicaoBusca = 'nome_cliente LIKE ?';
        $parametrosBusca = ['%' . $busca . '%'];
    }
}

$filtrosAtuais = array_filter(
    ['q' => $busca, 'status' => $statusFiltro, 'periodo' => $periodo],
    fn ($valor) => $valor !== ''
);

/* monta links preservando os filtros atuais */
function urlPedidos(array $base, array $mudancas = [], string $ancora = ''): string
{
    $parametros = array_filter(
        array_merge($base, $mudancas),
        fn ($valor) => $valor !== '' && $valor !== null
    );

    return 'pedidos.php'
        . ($parametros ? '?' . http_build_query($parametros) : '')
        . $ancora;
}


/* =========================
   INDICADORES E CONTAGENS (somente leitura)
========================= */

$totalGeral = (int) $pdo->query(
    "SELECT COUNT(*) FROM pedidos"
)->fetchColumn();

$indicadores = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'Em preparação'), 0) AS preparacao,
        COALESCE(SUM(status = 'Saiu para entrega'), 0) AS entrega,
        COALESCE(SUM(status = 'Finalizado'), 0) AS finalizados
     FROM pedidos
     WHERE $condicaoPeriodo"
)->fetch(PDO::FETCH_ASSOC);

$stmtContagem = $pdo->prepare(
    "SELECT status, COUNT(*)
     FROM pedidos
     WHERE $condicaoPeriodo AND $condicaoBusca
     GROUP BY status"
);
$stmtContagem->execute($parametrosBusca);
$contagemStatus = $stmtContagem->fetchAll(PDO::FETCH_KEY_PAIR);
$totalFiltrado = array_sum($contagemStatus);


/* =========================
   BUSCAR PEDIDOS (mesma consulta de antes, agora com filtros)
========================= */

$sqlPedidos = "SELECT * FROM pedidos WHERE $condicaoPeriodo AND $condicaoBusca";
$parametrosPedidos = $parametrosBusca;

if ($statusFiltro !== '') {
    $sqlPedidos .= " AND status = ?";
    $parametrosPedidos[] = $statusFiltro;
}

$sqlPedidos .= " ORDER BY criado_em DESC, id DESC";

$stmt = $pdo->prepare($sqlPedidos);
$stmt->execute($parametrosPedidos);

$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   BUSCAR ITENS
========================= */

$stmtItens = $pdo->prepare(
    "SELECT *
     FROM itens_pedido
     WHERE pedido_id = ?
     ORDER BY id"
);


/* =========================
   PEDIDO SELECIONADO (painel de detalhes)
========================= */

$pedidoSelecionado = null;
$idSelecionado = (int) ($_GET['pedido'] ?? 0);

if ($idSelecionado > 0) {
    $stmtSelecionado = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? LIMIT 1");
    $stmtSelecionado->execute([$idSelecionado]);
    $pedidoSelecionado = $stmtSelecionado->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$pedidoSelecionado && count($pedidos) > 0) {
    $pedidoSelecionado = $pedidos[0];
}

$itensSelecionado = [];
$imagensItens = [];

if ($pedidoSelecionado) {
    $stmtItens->execute([$pedidoSelecionado['id']]);
    $itensSelecionado = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

    /* fotos dos produtos (somente para exibição) */
    $idsProdutos = array_values(array_unique(array_map(fn ($item) => (int) $item['produto_id'], $itensSelecionado)));

    if ($idsProdutos) {
        $marcadores = implode(',', array_fill(0, count($idsProdutos), '?'));
        $stmtImagens = $pdo->prepare("SELECT id, imagem FROM produtos WHERE id IN ($marcadores)");
        $stmtImagens->execute($idsProdutos);
        $imagensItens = array_filter($stmtImagens->fetchAll(PDO::FETCH_KEY_PAIR));
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

<title>Pedidos - Saborê</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #252525;
}

.sidebar {
    width: 250px;
    height: 100vh;
    background: #1d1f21;
    color: white;
    position: fixed;
    left: 0;
    top: 0;
    padding: 25px 18px;
}

.logo {
    font-size: 23px;
    font-weight: bold;
    margin-bottom: 8px;
}

.logo-sub {
    color: #aaa;
    font-size: 13px;
    margin-bottom: 35px;
}

.menu a {
    display: block;
    text-decoration: none;
    color: #ddd;
    padding: 13px 15px;
    margin-bottom: 7px;
    border-radius: 7px;
    transition: .2s;
}

.menu a:hover {
    background: #34373a;
    color: white;
}

.menu .ativo {
    background: white;
    color: #202020;
    font-weight: bold;
}

.sair {
    position: absolute;
    bottom: 25px;
    left: 18px;
    right: 18px;
}

.sair a {
    display: block;
    text-align: center;
    color: #ddd;
    text-decoration: none;
    padding: 12px;
    border: 1px solid #555;
    border-radius: 7px;
}

.sair a:hover { background: #34373a; }

.main {
    margin-left: 250px;
    padding: 32px;
}

.cabecalho {
    margin-bottom: 25px;
}

.cabecalho h1 {
    margin: 0 0 6px;
    font-size: 29px;
}

.cabecalho p {
    margin: 0;
    color: #777;
}

.mensagem {
    background: #dff5e5;
    color: #167333;
    border: 1px solid #b9e7c5;
    padding: 13px 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.pedido {
    background: white;
    border-radius: 10px;
    border: 1px solid #e4e4e4;
    margin-bottom: 22px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}

.pedido-topo {
    padding: 18px 20px;
    background: #fafafa;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.numero-pedido {
    font-size: 19px;
    font-weight: bold;
}

.data-pedido {
    color: #777;
    font-size: 13px;
    margin-top: 4px;
}

.status {
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    background: #fff0e5;
    color: #d65400;
}

.pedido-corpo {
    padding: 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.bloco h3 {
    margin: 0 0 12px;
    font-size: 16px;
}

.info {
    margin-bottom: 8px;
    color: #555;
    line-height: 1.5;
}

.info strong { color: #222; }

.item {
    padding: 10px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    gap: 15px;
}

.item:last-child { border-bottom: none; }

.total {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid #eee;
    display: flex;
    justify-content: space-between;
    font-size: 19px;
    font-weight: bold;
}

.alterar-status {
    margin-top: 20px;
    padding-top: 18px;
    border-top: 1px solid #eee;
}

.alterar-status form {
    display: flex;
    gap: 10px;
}

.alterar-status select {
    flex: 1;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 7px;
    font-size: 14px;
}

.alterar-status button {
    border: none;
    background: #202020;
    color: white;
    padding: 0 18px;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.alterar-status button:hover { background: #333; }

@media (max-width: 800px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .menu {
        display: flex;
        gap: 5px;
        overflow-x: auto;
    }

    .menu a { white-space: nowrap; }

    .sair {
        position: static;
        margin-top: 15px;
    }

    .main {
        margin-left: 0;
        padding: 20px;
    }

    .pedido-topo { align-items: flex-start; }
    .pedido-corpo { grid-template-columns: 1fr; }
    .alterar-status form { flex-direction: column; }
    .alterar-status button { padding: 12px; }
}
</style>

<!-- ?v= muda quando o arquivo muda: evita CSS antigo do cache -->
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

</head>

<body class="pagina-admin pagina-pedidos">

<?php $paginaAtiva = 'pedidos'; require __DIR__ . '/_sidebar.php'; ?>

<main class="main">

    <div class="cabecalho">
        <h1>Pedidos</h1>
        <p>Acompanhe e gerencie os pedidos do seu cardápio digital.</p>
    </div>

    <?php if ($mensagem !== ''): ?>
        <div class="mensagem">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($totalGeral === 0): ?>

        <div class="estado-vazio">

            <div class="estado-vazio-icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="20" r="1.4"/>
                    <circle cx="18" cy="20" r="1.4"/>
                    <path d="M2.5 3.5h2.6l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2l1.7-7.3H6.2"/>
                </svg>
            </div>

            <h2>Nenhum pedido recebido</h2>

            <p>
                Quando um cliente finalizar uma compra pelo cardápio digital,
                o pedido aparecerá aqui para você acompanhar e atualizar o status.
            </p>

        </div>

    <?php else: ?>

        <!-- INDICADORES (dados reais do período escolhido) -->
        <?php
        $legendaPeriodo = $periodos[$periodo];
        $cardsIndicadores = [
            ['Total de pedidos', $indicadores['total'], 'ic-carrinho', 'icone-laranja'],
            ['Em preparação', $indicadores['preparacao'], 'ic-relogio', 'icone-ambar'],
            ['Saiu para entrega', $indicadores['entrega'], 'ic-entrega', 'icone-azul'],
            ['Finalizados', $indicadores['finalizados'], 'ic-check', 'icone-verde'],
        ];
        ?>
        <div class="pedidos-indicadores">
            <?php foreach ($cardsIndicadores as [$rotulo, $valor, $icone, $cor]): ?>
                <div class="indicador-pedido">
                    <div class="card-icone <?= $cor ?>" aria-hidden="true"><i class="ic <?= $icone ?>"></i></div>
                    <div>
                        <span><?= $rotulo ?></span>
                        <strong><?= (int) $valor ?></strong>
                        <small><?= htmlspecialchars($legendaPeriodo) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ABAS DE STATUS + BUSCA E PERÍODO -->
        <div class="pedidos-barra">

            <nav class="pedidos-abas" aria-label="Filtrar por status">
                <a
                    href="<?= htmlspecialchars(urlPedidos($filtrosAtuais, ['status' => '', 'pedido' => null])) ?>"
                    class="aba-status<?= $statusFiltro === '' ? ' ativa' : '' ?>"
                    <?= $statusFiltro === '' ? 'aria-current="true"' : '' ?>
                >
                    Todos (<?= $totalFiltrado ?>)
                </a>
                <?php foreach ($statusPermitidos as $status): ?>
                    <a
                        href="<?= htmlspecialchars(urlPedidos($filtrosAtuais, ['status' => $status, 'pedido' => null])) ?>"
                        class="aba-status<?= $statusFiltro === $status ? ' ativa' : '' ?>"
                        <?= $statusFiltro === $status ? 'aria-current="true"' : '' ?>
                    >
                        <?= htmlspecialchars($status) ?> (<?= (int) ($contagemStatus[$status] ?? 0) ?>)
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="GET" class="pedidos-busca" role="search">
                <?php if ($statusFiltro !== ''): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFiltro) ?>">
                <?php endif; ?>

                <label class="filtro-campo filtro-busca-pedido">
                    <span class="sr-only">Buscar por nome do cliente ou número do pedido</span>
                    <i class="ic ic-busca" aria-hidden="true"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por nome ou número do pedido...">
                </label>

                <label class="filtro-campo">
                    <span class="sr-only">Período</span>
                    <i class="ic ic-calendario" aria-hidden="true"></i>
                    <select name="periodo">
                        <?php foreach ($periodos as $chave => $rotulo): ?>
                            <option value="<?= $chave ?>" <?= $periodo === $chave ? 'selected' : '' ?>><?= $rotulo ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" class="btn-filtrar">
                    <i class="ic ic-filtro" aria-hidden="true"></i>
                    Filtrar
                </button>
            </form>

        </div>

        <!-- LISTA + DETALHES -->
        <div class="pedidos-layout">

            <section class="secao pedidos-lista">

                <?php if (count($pedidos) === 0): ?>

                    <div class="estado-vazio estado-vazio-compacto">
                        <div class="estado-vazio-icone" aria-hidden="true"><i class="ic ic-busca"></i></div>
                        <h3>Nenhum pedido encontrado</h3>
                        <p>Nenhum pedido corresponde à busca ou aos filtros escolhidos.</p>
                        <a href="pedidos.php" class="limpar-filtros">Limpar filtros</a>
                    </div>

                <?php else: ?>

                    <div class="tabela-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th><span class="sr-only">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pedidos as $pedido): ?>
                                <?php $ehSelecionado = $pedidoSelecionado && (int) $pedidoSelecionado['id'] === (int) $pedido['id']; ?>
                                <tr class="<?= $ehSelecionado ? 'selecionado' : '' ?>">
                                    <td>
                                        <strong class="pedido-numero">#<?= (int) $pedido['id'] ?></strong>
                                        <small class="pedido-sub"><?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?></small>
                                    </td>
                                    <td>
                                        <strong class="pedido-cliente"><?= htmlspecialchars($pedido['nome_cliente']) ?></strong>
                                        <small class="pedido-sub"><?= htmlspecialchars($pedido['telefone']) ?></small>
                                    </td>
                                    <td class="preco">R$ <?= number_format((float) $pedido['total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status <?= classeStatusPedido($pedido['status']) ?>">
                                            <?= htmlspecialchars($pedido['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a
                                            href="<?= htmlspecialchars(urlPedidos($filtrosAtuais, ['pedido' => (int) $pedido['id']], '#detalhe-pedido')) ?>"
                                            class="ver-detalhes"
                                            <?= $ehSelecionado ? 'aria-current="true"' : '' ?>
                                        >
                                            Ver detalhes
                                            <i class="ic ic-seta" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

            <aside class="secao pedido-detalhe" id="detalhe-pedido">

                <?php if (!$pedidoSelecionado): ?>

                    <div class="estado-vazio estado-vazio-compacto">
                        <div class="estado-vazio-icone" aria-hidden="true"><i class="ic ic-recibo"></i></div>
                        <h3>Nenhum pedido selecionado</h3>
                        <p>Escolha um pedido na lista para ver os detalhes.</p>
                    </div>

                <?php else: ?>

                    <?php
                    $ped = $pedidoSelecionado;
                    $ehEntrega = $ped['tipo_entrega'] === 'entrega';
                    ?>

                    <div class="detalhe-topo">
                        <div>
                            <div class="detalhe-titulo">
                                <h2>Pedido #<?= (int) $ped['id'] ?></h2>
                                <span class="status <?= classeStatusPedido($ped['status']) ?>"><?= htmlspecialchars($ped['status']) ?></span>
                            </div>
                            <small class="pedido-sub"><?= date('d/m/Y H:i', strtotime($ped['criado_em'])) ?></small>
                        </div>
                    </div>

                    <h3 class="detalhe-secao">Dados do cliente</h3>
                    <ul class="detalhe-dados">
                        <li><i class="ic ic-usuario" aria-hidden="true"></i><span><small>Nome</small><?= htmlspecialchars($ped['nome_cliente']) ?></span></li>
                        <li><i class="ic ic-whatsapp" aria-hidden="true"></i><span><small>WhatsApp</small><?= htmlspecialchars($ped['telefone']) ?></span></li>
                        <li>
                            <i class="ic <?= $ehEntrega ? 'ic-entrega' : 'ic-loja' ?>" aria-hidden="true"></i>
                            <span><small>Recebimento</small><?= $ehEntrega ? 'Entrega' : 'Retirada no estabelecimento' ?></span>
                        </li>
                        <?php if ($ehEntrega && !empty($ped['endereco'])): ?>
                            <li class="dado-largo"><i class="ic ic-local" aria-hidden="true"></i><span><small>Endereço</small><?= htmlspecialchars($ped['endereco']) ?></span></li>
                        <?php endif; ?>
                        <?php if (!empty($ped['observacoes'])): ?>
                            <li class="dado-largo"><i class="ic ic-documento" aria-hidden="true"></i><span><small>Observações</small><?= nl2br(htmlspecialchars($ped['observacoes'])) ?></span></li>
                        <?php endif; ?>
                    </ul>

                    <h3 class="detalhe-secao">Itens do pedido</h3>
                    <div class="detalhe-itens">
                        <?php foreach ($itensSelecionado as $item): ?>
                            <?php $imagemItem = $imagensItens[(int) $item['produto_id']] ?? ''; ?>
                            <div class="detalhe-item">
                                <span class="detalhe-item-foto">
                                    <?php if ($imagemItem !== ''): ?>
                                        <img src="../<?= htmlspecialchars($imagemItem) ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <i class="ic ic-caixa" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </span>
                                <span class="detalhe-item-info">
                                    <strong><?= htmlspecialchars($item['nome_produto']) ?></strong>
                                    <?php if (!empty($item['sabor'])): ?>
                                        <small class="sabor-item-pedido">Sabor: <?= htmlspecialchars($item['sabor']) ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="detalhe-item-qtd"><?= (int) $item['quantidade'] ?> x R$ <?= number_format((float) $item['preco_unitario'], 2, ',', '.') ?></span>
                                <strong class="detalhe-item-total">R$ <?= number_format((float) $item['subtotal'], 2, ',', '.') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="detalhe-total">
                        <span>Total do pedido</span>
                        <strong>R$ <?= number_format((float) $ped['total'], 2, ',', '.') ?></strong>
                    </div>

                    <!-- ATUALIZAR STATUS (mesmo POST e validações de antes) -->
                    <form
                        method="POST"
                        action="<?= htmlspecialchars(urlPedidos($filtrosAtuais, ['pedido' => (int) $ped['id']])) ?>"
                        class="alterar-status detalhe-status"
                    >
                        <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">

                        <label for="statusPedido">Atualizar status do pedido</label>
                        <div class="detalhe-status-linha">
                            <select name="status" id="statusPedido">
                                <?php foreach ($statusPermitidos as $status): ?>
                                    <?php if (!statusDisponivel($status, $ped['tipo_entrega'])) continue; ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= $ped['status'] === $status ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Salvar status</button>
                        </div>
                    </form>

                <?php endif; ?>

            </aside>

        </div>

    <?php endif; ?>

</main>


</body>

</html>
