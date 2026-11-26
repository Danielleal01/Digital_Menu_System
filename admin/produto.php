<?php
require_once 'auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$editando = $id > 0;
$erro = '';

$produto = [
    'categoria_id' => '',
    'nome' => '',
    'descricao' => '',
    'preco' => '',
    'imagem' => '',
    'ativo' => 1
];

// Busca categorias
$categorias = $pdo->query(
    "SELECT id, nome FROM categorias ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);


// Se estiver editando, busca o produto
if ($editando) {

    $stmt = $pdo->prepare(
        "SELECT * FROM produtos WHERE id = ? LIMIT 1"
    );

    $stmt->execute([$id]);

    $encontrado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$encontrado) {
        header('Location: index.php');
        exit;
    }

    $produto = $encontrado;
}


// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $categoria_id = (int)($_POST['categoria_id'] ?? 0);

    $nome = trim($_POST['nome'] ?? '');

    $descricao = trim($_POST['descricao'] ?? '');

    $precoTexto = str_replace(
        ',',
        '.',
        trim($_POST['preco'] ?? '')
    );

    $ativo = isset($_POST['ativo']) ? 1 : 0;

    $imagem = $produto['imagem'] ?? '';


    // Validação
    if (
        $categoria_id <= 0 ||
        $nome === '' ||
        $precoTexto === '' ||
        !is_numeric($precoTexto)
    ) {

        $erro = 'Preencha corretamente nome, categoria e preço.';

    }


    // UPLOAD DA IMAGEM
    if (
        $erro === '' &&
        isset($_FILES['imagem']) &&
        $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {

            $erro = 'Não foi possível enviar a imagem.';

        } else {

            $permitidos = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $mime = mime_content_type(
                $_FILES['imagem']['tmp_name']
            );


            if (!isset($permitidos[$mime])) {

                $erro = 'Use uma imagem JPG, PNG ou WEBP.';

            } elseif (
                $_FILES['imagem']['size'] >
                5 * 1024 * 1024
            ) {

                $erro = 'A imagem deve ter no máximo 5 MB.';

            } else {

                $pasta = __DIR__ . '/../uploads';


                // Cria a pasta uploads caso não exista
                if (!is_dir($pasta)) {

                    mkdir(
                        $pasta,
                        0755,
                        true
                    );

                }


                $nomeArquivo =
                    'produto_' .
                    bin2hex(random_bytes(8)) .
                    '.' .
                    $permitidos[$mime];


                $destino =
                    $pasta .
                    '/' .
                    $nomeArquivo;


                if (
                    move_uploaded_file(
                        $_FILES['imagem']['tmp_name'],
                        $destino
                    )
                ) {

                    // Exclui imagem antiga ao editar
                    if (
                        $editando &&
                        !empty($produto['imagem'])
                    ) {

                        $antiga =
                            __DIR__ .
                            '/../' .
                            ltrim(
                                $produto['imagem'],
                                '/'
                            );


                        if (is_file($antiga)) {

                            @unlink($antiga);

                        }

                    }


                    $imagem =
                        'uploads/' .
                        $nomeArquivo;

                } else {

                    $erro =
                        'Não foi possível salvar a imagem.';

                }

            }

        }

    }


    // GRAVAR NO BANCO
    if ($erro === '') {

        $preco = (float)$precoTexto;


        if ($editando) {

            $stmt = $pdo->prepare(
                "UPDATE produtos
                 SET
                    categoria_id = ?,
                    nome = ?,
                    descricao = ?,
                    preco = ?,
                    imagem = ?,
                    ativo = ?
                 WHERE id = ?"
            );


            $stmt->execute([
                $categoria_id,
                $nome,
                $descricao,
                $preco,
                $imagem,
                $ativo,
                $id
            ]);

        } else {

            $stmt = $pdo->prepare(
                "INSERT INTO produtos
                (
                    categoria_id,
                    nome,
                    descricao,
                    preco,
                    imagem,
                    ativo
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );


            $stmt->execute([
                $categoria_id,
                $nome,
                $descricao,
                $preco,
                $imagem,
                $ativo
            ]);

        }


        header('Location: produtos.php');
        exit;

    }


    // Mantém dados preenchidos caso dê erro
    $produto['categoria_id'] = $categoria_id;

    $produto['nome'] = $nome;

    $produto['descricao'] = $descricao;

    $produto['preco'] = $precoTexto;

    $produto['imagem'] = $imagem;

    $produto['ativo'] = $ativo;

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

<title>
    <?= $editando ? 'Editar Produto' : 'Novo Produto' ?>
    - Cardápio Digital
</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #252525;
}


/* MENU LATERAL */

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


/* CONTEÚDO */

.main {
    margin-left: 250px;
    padding: 32px;
    max-width: 1200px;
}

.topo {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}

.topo h1 {
    margin: 0 0 6px;
    font-size: 29px;
}

.topo p {
    margin: 0;
    color: #777;
}

.voltar {
    background: white;
    color: #333;
    border: 1px solid #ddd;
    padding: 11px 17px;
    border-radius: 7px;
    text-decoration: none;
}


/* FORMULÁRIO */

.form-box {
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    padding: 28px;
    box-shadow: 0 2px 8px rgba(0,0,0,.03);
}

.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.campo-full {
    grid-column: 1 / -1;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    font-size: 14px;
}

input[type="text"],
select,
textarea,
input[type="file"] {

    width: 100%;
    padding: 12px;
    border: 1px solid #d7dce1;
    border-radius: 7px;
    font: inherit;
    background: white;
}

textarea {
    min-height: 120px;
    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {

    outline: none;
    border-color: #555;
}

.ajuda {
    font-size: 12px;
    color: #777;
    margin-top: 7px;
}


/* IMAGEM */

.preview {
    margin-top: 12px;
}

.preview img {
    width: 150px;
    height: 110px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #ddd;
}


/* STATUS */

.status-box {

    display: flex;
    align-items: center;
    gap: 10px;

    padding: 14px;

    background: #f7f8f9;

    border-radius: 7px;
}


/* BOTÕES */

.acoes {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 25px;

    border-top: 1px solid #eee;

    padding-top: 20px;
}

.cancelar,
.salvar {

    padding: 12px 20px;

    border-radius: 7px;

    text-decoration: none;

    font-size: 15px;
}

.cancelar {

    background: #eef1f4;

    color: #333;
}

.salvar {

    border: none;

    background: #202020;

    color: white;

    cursor: pointer;
}

.salvar:hover {

    background: #333;
}


/* ERRO */

.erro {

    background: #f8d7da;

    color: #842029;

    border: 1px solid #f5c2c7;

    padding: 13px;

    border-radius: 7px;

    margin-bottom: 20px;
}


/* CELULAR */

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

    .menu a {

        white-space: nowrap;

    }

    .sair {

        position: static;

        margin-top: 15px;

    }

    .main {

        margin-left: 0;

        padding: 20px;

    }

    .topo {

        align-items: flex-start;

        flex-direction: column;

        gap: 15px;

    }

    .grid {

        grid-template-columns: 1fr;

    }

    .campo-full {

        grid-column: auto;

    }

}

</style>

<!-- ?v= muda quando o arquivo muda: evita CSS antigo do cache -->
<link rel="stylesheet" href="../assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">

</head>


<body class="pagina-admin pagina-produto">


<!-- MENU LATERAL -->

<?php $paginaAtiva = 'produtos'; require __DIR__ . '/_sidebar.php'; ?>


<!-- CONTEÚDO -->

<main class="main">


    <div class="topo">

        <div>

            <h1>

                <?= $editando
                    ? 'Editar Produto'
                    : 'Novo Produto'
                ?>

            </h1>


            <p>

                <?= $editando
                    ? 'Atualize as informações do produto.'
                    : 'Cadastre um novo item no cardápio.'
                ?>

            </p>

        </div>


        <a
            href="produtos.php"
            class="voltar"
        >
            ← Voltar
        </a>

    </div>


    <div class="form-box">


        <?php if ($erro !== ''): ?>

            <div class="erro">

                <?= htmlspecialchars($erro) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <?php
            /* ---- apenas exibição: dados reais da imagem atual e preço com vírgula ---- */
            $infoImagem = null;
            if (!empty($produto['imagem'])) {
                $arquivoImagem = __DIR__ . '/../' . ltrim($produto['imagem'], '/');
                if (is_file($arquivoImagem)) {
                    $dimensoes = @getimagesize($arquivoImagem);
                    $infoImagem = [
                        'nome' => basename($produto['imagem']),
                        'detalhes' => implode(' • ', array_filter([
                            number_format(filesize($arquivoImagem) / 1024, 0, ',', '.') . ' KB',
                            $dimensoes ? $dimensoes[0] . ' × ' . $dimensoes[1] . ' px' : ''
                        ]))
                    ];
                }
            }
            $precoExibicao = is_numeric($produto['preco'])
                ? number_format((float) $produto['preco'], 2, ',', '')
                : (string) $produto['preco'];
            ?>

            <div class="grid produto-form-grid">

                <!-- COLUNA: DADOS DO PRODUTO -->
                <div class="produto-coluna">

                    <!-- NOME -->
                    <div>
                        <label for="nome">Nome do produto <span class="obrigatorio">*</span></label>
                        <div class="campo-icone-admin">
                            <i class="ic ic-caixa" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="120"
                                value="<?= htmlspecialchars(
                                    $produto['nome']
                                ) ?>"
                                required
                            >
                        </div>
                    </div>

                    <!-- CATEGORIA -->
                    <div>
                        <label for="categoria_id">Categoria <span class="obrigatorio">*</span></label>
                        <div class="campo-icone-admin">
                            <i class="ic ic-etiqueta" aria-hidden="true"></i>
                            <select
                                id="categoria_id"
                                name="categoria_id"
                                required
                            >
                                <option value="">
                                    Selecione uma categoria
                                </option>
                                <?php foreach (
                                    $categorias as $categoria
                                ): ?>
                                    <option
                                        value="<?= $categoria['id'] ?>"
                                        <?= (int)$produto['categoria_id']
                                            ===
                                            (int)$categoria['id']
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= htmlspecialchars(
                                            $categoria['nome']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- DESCRIÇÃO -->
                    <div>
                        <label for="descricao">Descrição</label>
                        <div class="campo-icone-admin campo-icone-area">
                            <i class="ic ic-documento" aria-hidden="true"></i>
                            <textarea
                                id="descricao"
                                name="descricao"
                                maxlength="255"
                                placeholder="Ex.: Pão, hambúrguer, queijo, alface e tomate"
                            ><?= htmlspecialchars(
                                $produto['descricao']
                            ) ?></textarea>
                        </div>
                    </div>

                    <!-- PREÇO -->
                    <div>
                        <label for="preco">Preço (R$) <span class="obrigatorio">*</span></label>
                        <div class="campo-icone-admin">
                            <i class="ic ic-moeda" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="preco"
                                name="preco"
                                placeholder="Ex.: 22,90"
                                value="<?= htmlspecialchars($precoExibicao) ?>"
                                inputmode="decimal"
                                required
                            >
                        </div>
                    </div>

                </div>

                <!-- COLUNA: IMAGEM E STATUS -->
                <div class="produto-coluna">

                    <!-- IMAGEM -->
                    <div>
                        <span class="rotulo" id="rotuloImagem">Foto do produto</span>

                        <input
                            type="file"
                            id="imagem"
                            name="imagem"
                            class="input-arquivo"
                            aria-labelledby="rotuloImagem"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <label for="imagem" class="upload-area">
                            <span class="upload-preview" id="uploadPreview">
                                <?php if (!empty($produto['imagem'])): ?>
                                    <img
                                        src="../<?= htmlspecialchars(
                                            $produto['imagem']
                                        ) ?>"
                                        alt="Imagem atual"
                                    >
                                <?php else: ?>
                                    <span class="upload-sem-foto">
                                        <i class="ic ic-imagem" aria-hidden="true"></i>
                                        Sem foto
                                    </span>
                                <?php endif; ?>
                            </span>
                            <span class="upload-texto">
                                <span class="upload-icone" aria-hidden="true"><i class="ic ic-imagem"></i></span>
                                <strong><?= !empty($produto['imagem']) ? 'Escolher nova imagem' : 'Escolher imagem' ?></strong>
                                <small>JPG, PNG ou WEBP • Máximo de 5 MB.</small>
                            </span>
                        </label>

                        <div class="arquivo-info" id="arquivoInfo"<?= $infoImagem ? '' : ' hidden' ?>>
                            <span class="arquivo-miniatura">
                                <img
                                    id="arquivoMiniatura"
                                    <?= $infoImagem ? 'src="../' . htmlspecialchars($produto['imagem']) . '"' : '' ?>
                                    alt=""
                                >
                            </span>
                            <span class="arquivo-dados">
                                <strong id="arquivoNome"><?= $infoImagem ? htmlspecialchars($infoImagem['nome']) : '' ?></strong>
                                <small id="arquivoDetalhes"><?= $infoImagem ? htmlspecialchars($infoImagem['detalhes']) : '' ?></small>
                                <small class="arquivo-aviso" id="arquivoAviso" hidden>Nova imagem selecionada: será enviada ao salvar.</small>
                            </span>
                            <label for="imagem" class="trocar-imagem">
                                <i class="ic ic-trocar" aria-hidden="true"></i>
                                Trocar imagem
                            </label>
                        </div>
                    </div>

                    <!-- STATUS -->
                    <div>
                        <span class="rotulo">Status</span>
                        <label class="status-cartao" for="ativo">
                            <input
                                type="checkbox"
                                id="ativo"
                                name="ativo"
                                value="1"
                                <?= !empty($produto['ativo'])
                                    ? 'checked'
                                    : ''
                                ?>
                            >
                            <span>
                                <strong>Produto ativo e visível no cardápio</strong>
                                <small>Quando desativado, o produto não aparece para os clientes no cardápio.</small>
                            </span>
                        </label>
                    </div>

                </div>

            </div>


            <!-- BOTÕES -->

            <div class="acoes">


                <a
                    href="produtos.php"
                    class="cancelar"
                >
                    Cancelar
                </a>


                <button
                    type="submit"
                    class="salvar"
                >
                    <i class="ic ic-salvar" aria-hidden="true"></i>

                    <?= $editando
                        ? 'Salvar Alterações'
                        : 'Cadastrar Produto'
                    ?>

                </button>


            </div>


        </form>


    </div>


</main>


<script>
/* Prévia da nova imagem escolhida (apenas exibição; o envio e a validação continuam no servidor) */
(() => {
    const campo = document.getElementById('imagem');
    const preview = document.getElementById('uploadPreview');
    const info = document.getElementById('arquivoInfo');
    const miniatura = document.getElementById('arquivoMiniatura');
    const nome = document.getElementById('arquivoNome');
    const detalhes = document.getElementById('arquivoDetalhes');
    const aviso = document.getElementById('arquivoAviso');

    campo.addEventListener('change', () => {
        const arquivo = campo.files[0];
        if (!arquivo) return;

        const url = URL.createObjectURL(arquivo);
        const img = new Image();
        img.alt = 'Nova imagem selecionada';
        img.src = url;
        preview.replaceChildren(img);

        miniatura.src = url;
        nome.textContent = arquivo.name;
        const tamanho = Math.round(arquivo.size / 1024).toLocaleString('pt-BR') + ' KB';
        detalhes.textContent = tamanho;
        img.onload = () => { detalhes.textContent = tamanho + ' • ' + img.naturalWidth + ' × ' + img.naturalHeight + ' px'; };
        aviso.hidden = false;
        info.hidden = false;
    });
})();
</script>

</body>

</html>
