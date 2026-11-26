<?php

require_once 'config/db.php';

$pedidoId = isset($_GET['pedido_id']) ? (int) $_GET['pedido_id'] : 0;
$telefone = trim($_GET['telefone'] ?? '');
$erro = '';
$pedido = null;
$itens = [];

function limparTelefone(string $telefone): string
{
    return preg_replace('/\D+/', '', $telefone);
}

function moedaPedido(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function statusPedidoCliente(string $status): string
{
    if (str_starts_with($status, 'Em prepara')) {
        return 'Em preparação';
    }

    return $status;
}

function etapaClasse(string $etapa, string $statusAtual, string $tipoEntrega): string
{
    $statusAtual = statusPedidoCliente($statusAtual);

    $ordem = ['Recebido', 'Em preparação', 'Pronto'];

    if ($tipoEntrega === 'entrega') {
        $ordem[] = 'Saiu para entrega';
    }

    $ordem[] = 'Finalizado';

    $indiceEtapa = array_search($etapa, $ordem, true);
    $indiceAtual = array_search($statusAtual, $ordem, true);

    if ($etapa === 'Saiu para entrega' && $statusAtual === 'Finalizado') {
        $indiceAtual = array_search('Finalizado', $ordem, true);
    }

    if ($indiceEtapa === false || $indiceAtual === false) {
        return '';
    }

    if ($indiceEtapa < $indiceAtual) {
        return 'concluida';
    }

    if ($indiceEtapa === $indiceAtual) {
        return 'atual';
    }

    return '';
}

if ($pedidoId > 0 || $telefone !== '') {
    if ($pedidoId <= 0 || $telefone === '') {
        $erro = 'Informe o número do pedido e o telefone usado na compra.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM pedidos
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$pedidoId]);
        $pedidoEncontrado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$pedidoEncontrado ||
            limparTelefone($pedidoEncontrado['telefone']) !== limparTelefone($telefone)
        ) {
            $erro = 'Pedido não encontrado. Confira o número do pedido e o telefone informado.';
        } else {
            $pedido = $pedidoEncontrado;

            $stmtItens = $pdo->prepare(
                "SELECT *
                 FROM itens_pedido
                 WHERE pedido_id = ?
                 ORDER BY id"
            );

            $stmtItens->execute([$pedidoId]);
            $itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

$etapas = ['Recebido', 'Em preparação', 'Pronto'];

if (($pedido['tipo_entrega'] ?? '') === 'entrega') {
    $etapas[] = 'Saiu para entrega';
}

$etapas[] = 'Finalizado';

/* =========================
   DADOS PARA ATUALIZAÇÃO AUTOMÁTICA
========================= */

function rotuloEtapa(string $etapa): string
{
    return $etapa === 'Finalizado' ? 'Concluído' : $etapa;
}

/*
 * Fotos dos produtos dos itens (somente leitura, apenas para exibição).
 * Retorna [produto_id => caminho da imagem]; produtos sem foto ou excluídos ficam de fora.
 */
function imagensDosProdutos(PDO $pdo, array $itens): array
{
    $ids = array_values(array_unique(array_map(fn ($item) => (int) $item['produto_id'], $itens)));

    if (count($ids) === 0) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, imagem FROM produtos WHERE id IN ($marcadores)");
    $stmt->execute($ids);

    return array_filter($stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

$imagensProdutos = $pedido ? imagensDosProdutos($pdo, $itens) : [];

function dadosPedidoCliente(array $pedido, array $itens, array $etapas, array $imagens = []): array
{
    return [
        'status' => statusPedidoCliente($pedido['status']),
        'finalizado' => $pedido['status'] === 'Finalizado',
        'etapas' => array_map(
            fn ($etapa) => [
                'rotulo' => rotuloEtapa($etapa),
                'classe' => etapaClasse($etapa, $pedido['status'], $pedido['tipo_entrega'])
            ],
            $etapas
        ),
        'itens' => array_map(
            fn ($item) => [
                'quantidade' => (int) $item['quantidade'],
                'nome' => $item['nome_produto'],
                'sabor' => $item['sabor'] ?? '',
                'subtotal' => moedaPedido((float) $item['subtotal']),
                'imagem' => $imagens[(int) $item['produto_id']] ?? ''
            ],
            $itens
        ),
        'total' => moedaPedido((float) $pedido['total'])
    ];
}

/* Resposta JSON: mesma busca e validação de pedido + telefone acima */
if (($_GET['formato'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (!$pedido) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $erro !== '' ? $erro : 'Pedido não encontrado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(
        ['sucesso' => true] + dadosPedidoCliente($pedido, $itens, $etapas, $imagensProdutos),
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar pedido - Saborê</title>
    <!-- ?v= muda quando o arquivo muda: evita que o navegador use um CSS antigo do cache -->
    <link rel="stylesheet" href="assets/css/storefront.css?v=<?= filemtime(__DIR__ . '/assets/css/storefront.css') ?>">
</head>
<body>

<header class="hero hero-cardapio hero-acompanhamento">
    <div class="hero-conteudo">
        <div class="logo-sabore-wrap logo-integrado">
            <img src="assets/logo-sabore.png" alt="Saborê" class="logo-sabore" width="985" height="517">
        </div>
        <p>Acompanhe o andamento do seu pedido.</p>
    </div>
</header>

<main class="container acompanhamento-container">

    <section class="acompanhamento-card">
        <div class="acompanhamento-topo">
            <div>
                <h1>Acompanhar pedido</h1>
                <p>Informe o número do pedido e o WhatsApp usado na compra.</p>
            </div>

            <a href="index.php" class="link-voltar-cardapio">
                Voltar ao cardápio
            </a>
        </div>

        <form method="GET" class="form-acompanhamento">
            <div class="campo">
                <label for="pedido_id">Número do pedido</label>
                <div class="campo-com-icone">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3.5h12v17l-3-2-3 2-3-2-3 2v-17Z"/><path d="M9 8.5h6M9 12h6M9 15.5h4"/></svg>
                    <input
                        type="number"
                        id="pedido_id"
                        name="pedido_id"
                        min="1"
                        value="<?= $pedidoId > 0 ? (int)$pedidoId : '' ?>"
                        placeholder="Ex.: 12"
                        required
                    >
                </div>
            </div>

            <div class="campo">
                <label for="telefone">WhatsApp</label>
                <div class="campo-com-icone">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.6a8.6 8.6 0 0 1-12.7 7.6L3.5 20.5l1.4-4.1a8.6 8.6 0 1 1 15.6-4.8Z"/><path d="M9 8.5c.3 2.6 2.4 4.8 5 5.4l1.2-1.2 1.8.9-.3 1.4c-3.7.3-7.9-3.7-8.2-7.6l1.4-.3.9 1.8L9 8.5Z"/></svg>
                    <input
                        type="tel"
                        id="telefone"
                        name="telefone"
                        value="<?= htmlspecialchars($telefone) ?>"
                        placeholder="(11) 99999-9999"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="confirmar-pedido">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                Consultar pedido
            </button>
        </form>

        <?php if ($erro !== ''): ?>
            <div class="mensagem-pedido erro acompanhamento-msg">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($pedido): ?>
        <section class="pedido-cliente-card">

            <!-- número, status, data, cliente e total -->
            <div class="pedido-cliente-header">
                <div class="pedido-identificacao">
                    <div class="pedido-titulo-linha">
                        <h2>Pedido #<?= (int)$pedido['id'] ?></h2>
                        <strong class="status-cliente" id="statusPedido" aria-live="polite">
                            <?= htmlspecialchars(statusPedidoCliente($pedido['status'])) ?>
                        </strong>
                    </div>
                    <p class="pedido-meta">
                        <?php if (!empty($pedido['criado_em'])): ?>
                            <span><?= date('d/m/Y', strtotime($pedido['criado_em'])) ?> às <?= date('H:i', strtotime($pedido['criado_em'])) ?></span>
                        <?php endif; ?>
                        <span>Cliente: <?= htmlspecialchars($pedido['nome_cliente']) ?></span>
                    </p>
                </div>

                <div class="pedido-total">
                    <span>Total do pedido</span>
                    <strong id="totalPedido"><?= moedaPedido((float)$pedido['total']) ?></strong>
                </div>
            </div>

            <!-- linha do tempo (classes concluida/atual geradas pelo PHP e pelo JS) -->
            <div class="linha-andamento" id="linhaAndamento">
                <?php foreach ($etapas as $etapa): ?>
                    <div class="etapa-pedido <?= etapaClasse($etapa, $pedido['status'], $pedido['tipo_entrega']) ?>">
                        <span></span>
                        <strong>
                            <?= $etapa === 'Finalizado'
                                ? 'Concluído'
                                : htmlspecialchars($etapa)
                            ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- itens do pedido -->
            <h3 class="pedido-secao-titulo">Itens do pedido</h3>

            <div id="itensPedido" class="itens-pedido">
            <?php foreach ($itens as $item): ?>
                <?php $imagemItem = $imagensProdutos[(int)$item['produto_id']] ?? ''; ?>
                <div class="item-acompanhamento">
                    <div class="item-foto">
                        <?php if ($imagemItem !== ''): ?>
                            <img src="<?= htmlspecialchars($imagemItem) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12.5H6L5 8Z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <strong class="item-nome"><?= htmlspecialchars($item['nome_produto']) ?></strong>
                        <?php if (!empty($item['sabor'])): ?>
                            <small>Sabor: <?= htmlspecialchars($item['sabor']) ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="item-qtd"><?= (int)$item['quantidade'] ?>x</span>
                    <strong class="item-valor"><?= moedaPedido((float)$item['subtotal']) ?></strong>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- forma de recebimento (endereço só quando for entrega e existir) -->
            <?php $ehEntrega = $pedido['tipo_entrega'] === 'entrega'; ?>
            <div class="pedido-recebimento">
                <span class="recebimento-icone" aria-hidden="true">
                    <?php if ($ehEntrega): ?>
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 9.5 5 4.5h14l1.5 5"/><path d="M3.5 9.5a2.8 2.8 0 0 0 5.7 0 2.8 2.8 0 0 0 5.6 0 2.8 2.8 0 0 0 5.7 0"/><path d="M5 12.3v7.2h14v-7.2"/><path d="M10 19.5v-4.5h4v4.5"/></svg>
                    <?php endif; ?>
                </span>
                <div class="recebimento-bloco">
                    <span>Forma de recebimento</span>
                    <strong><?= $ehEntrega ? 'Entrega' : 'Retirada no estabelecimento' ?></strong>
                </div>
                <?php if ($ehEntrega && !empty($pedido['endereco'])): ?>
                    <div class="recebimento-bloco recebimento-endereco">
                        <span>Endereço de entrega</span>
                        <strong><?= htmlspecialchars($pedido['endereco']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <p class="atualizacao-status" id="atualizacaoStatus" aria-live="polite"></p>
        </section>
    <?php endif; ?>

</main>

<footer>
    Saborê • Acompanhamento de pedidos
</footer>

<?php if ($pedido): ?>
<script>

/* =========================
   ATUALIZAÇÃO AUTOMÁTICA DO STATUS
========================= */

const INTERVALO_CONSULTA = 5000;
const INTERVALO_MAXIMO = 30000;
const TEMPO_LIMITE = 8000;

const urlStatus = 'acompanhar_pedido.php?' + new URLSearchParams(
    <?= json_encode([
        'pedido_id' => (int) $pedido['id'],
        'telefone' => $telefone,
        'formato' => 'json'
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
);

let estadoAtual = <?= json_encode(
    dadosPedidoCliente($pedido, $itens, $etapas, $imagensProdutos),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
) ?>;

const statusPedido = document.getElementById('statusPedido');
const linhaAndamento = document.getElementById('linhaAndamento');
const itensPedido = document.getElementById('itensPedido');
const totalPedido = document.getElementById('totalPedido');
const atualizacaoStatus = document.getElementById('atualizacaoStatus');

let temporizador = null;
let consultaEmAndamento = false;
let falhasSeguidas = 0;
let consultaEncerrada = estadoAtual.finalizado;

function assinaturaStatus(dados) {
    return JSON.stringify([dados.status, dados.etapas]);
}

function assinaturaItens(dados) {
    return JSON.stringify([dados.itens, dados.total]);
}

function renderizarEtapas(etapas) {
    linhaAndamento.innerHTML = '';

    etapas.forEach(etapa => {
        const div = document.createElement('div');
        const rotulo = document.createElement('strong');

        div.className = ('etapa-pedido ' + etapa.classe).trim();
        rotulo.textContent = etapa.rotulo;

        div.appendChild(document.createElement('span'));
        div.appendChild(rotulo);
        linhaAndamento.appendChild(div);
    });
}

function renderizarItens(itens) {
    itensPedido.innerHTML = '';

    /* Mesma estrutura de item gerada pelo PHP na carga da página */
    itens.forEach(item => {
        const linha = document.createElement('div');
        const foto = document.createElement('div');
        const info = document.createElement('div');
        const nome = document.createElement('strong');
        const quantidade = document.createElement('span');
        const subtotal = document.createElement('strong');

        linha.className = 'item-acompanhamento';
        foto.className = 'item-foto';
        info.className = 'item-info';
        nome.className = 'item-nome';
        quantidade.className = 'item-qtd';
        subtotal.className = 'item-valor';

        if (item.imagem) {
            const img = document.createElement('img');
            img.src = item.imagem;
            img.alt = '';
            foto.appendChild(img);
        } else {
            foto.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12.5H6L5 8Z"/><path d="M9 10V6.5a3 3 0 0 1 6 0V10"/></svg>';
        }

        nome.textContent = item.nome;
        info.appendChild(nome);

        if (item.sabor) {
            const sabor = document.createElement('small');
            sabor.textContent = `Sabor: ${item.sabor}`;
            info.appendChild(sabor);
        }

        quantidade.textContent = `${item.quantidade}x`;
        subtotal.textContent = item.subtotal;

        linha.append(foto, info, quantidade, subtotal);
        itensPedido.appendChild(linha);
    });
}

/* Atualiza somente o que mudou */
function aplicarDados(dados) {
    if (assinaturaStatus(dados) !== assinaturaStatus(estadoAtual)) {
        statusPedido.textContent = dados.status;
        renderizarEtapas(dados.etapas);

        statusPedido.classList.remove('status-atualizado');
        void statusPedido.offsetWidth;
        statusPedido.classList.add('status-atualizado');
    }

    if (assinaturaItens(dados) !== assinaturaItens(estadoAtual)) {
        renderizarItens(dados.itens);
        totalPedido.textContent = dados.total;
    }

    estadoAtual = dados;
}

function mostrarSituacao(texto, erro = false) {
    atualizacaoStatus.textContent = texto;
    atualizacaoStatus.classList.toggle('erro', erro);
}

function horaAtual() {
    return new Date().toLocaleTimeString('pt-BR');
}

function proximoIntervalo() {
    if (falhasSeguidas === 0) {
        return INTERVALO_CONSULTA;
    }

    return Math.min(
        INTERVALO_CONSULTA * 2 ** falhasSeguidas,
        INTERVALO_MAXIMO
    );
}

function agendarConsulta(espera) {
    clearTimeout(temporizador);

    if (consultaEncerrada || document.hidden) {
        return;
    }

    temporizador = setTimeout(consultarStatus, espera);
}

async function consultarStatus() {
    /* Nunca mais de uma requisição ao mesmo tempo */
    if (consultaEmAndamento || consultaEncerrada || document.hidden) {
        return;
    }

    consultaEmAndamento = true;

    const controle = new AbortController();
    const limite = setTimeout(() => controle.abort(), TEMPO_LIMITE);

    try {
        const resposta = await fetch(urlStatus, {
            cache: 'no-store',
            headers: { 'Accept': 'application/json' },
            signal: controle.signal
        });

        const dados = await resposta.json();

        if (!resposta.ok || !dados.sucesso) {
            throw new Error(dados.mensagem || 'Falha ao consultar o pedido.');
        }

        aplicarDados(dados);
        falhasSeguidas = 0;

        if (dados.finalizado) {
            consultaEncerrada = true;
            mostrarSituacao('Pedido concluído.');
        } else {
            mostrarSituacao(`Atualizado às ${horaAtual()}`);
        }
    } catch (erro) {
        falhasSeguidas++;
        mostrarSituacao('Sem conexão com o servidor. Tentando novamente…', true);
    } finally {
        clearTimeout(limite);
        consultaEmAndamento = false;
        agendarConsulta(proximoIntervalo());
    }
}

/* Pausa com a aba oculta e retoma ao voltar */
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearTimeout(temporizador);
    } else {
        consultarStatus();
    }
});

if (consultaEncerrada) {
    mostrarSituacao('Pedido concluído.');
} else {
    mostrarSituacao('O status é atualizado automaticamente.');
    agendarConsulta(INTERVALO_CONSULTA);
}

</script>
<?php endif; ?>

</body>
</html>
