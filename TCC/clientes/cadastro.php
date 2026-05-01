<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Redireciona se já estiver logado
if (isset($_SESSION['tipo_usuario'])) {
    header('Location: ' . ($_SESSION['tipo_usuario'] === 'cliente' ? '../dashboard_cliente.php' : '../dashboard_empresa.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome            = cleanInput($_POST['nome']            ?? '');
    $email           = cleanInput($_POST['email']           ?? '');
    $senha           = $_POST['senha']            ?? '';
    $confirmar_senha = $_POST['confirmar_senha']  ?? '';
    $telefone        = cleanInput($_POST['telefone']        ?? '');
    $tipo            = $_POST['tipo']             ?? 'fisica';
    $cpf_cnpj        = cleanInput($_POST['cpf_cnpj']        ?? '');

    // Campos de endereço
    $cep         = cleanInput($_POST['cep']         ?? '');
    $logradouro  = cleanInput($_POST['logradouro']  ?? '');
    $numero      = cleanInput($_POST['numero']      ?? '');
    $complemento = cleanInput($_POST['complemento'] ?? '');
    $bairro      = cleanInput($_POST['bairro']      ?? '');
    $cidade      = cleanInput($_POST['cidade']      ?? '');
    $estado      = cleanInput($_POST['estado']      ?? '');

    // Monta endereço completo para salvar no banco
    $partes = array_filter([$logradouro, $numero, $complemento, $bairro, $cidade, $estado, $cep]);
    $endereco = implode(', ', $partes);

    $erros = [];

    if (empty($nome))  $erros['nome']  = 'Nome é obrigatório.';
    if (empty($email)) $erros['email'] = 'E-mail é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'E-mail inválido.';
    if (empty($senha)) $erros['senha'] = 'Senha é obrigatória.';
    elseif (strlen($senha) < 6) $erros['senha'] = 'Senha deve ter no mínimo 6 caracteres.';
    if ($senha !== $confirmar_senha) $erros['confirmar_senha'] = 'As senhas não conferem.';

    $check = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) $erros['email'] = 'E-mail já cadastrado.';

    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO clientes (nome, email, senha, telefone, endereco, tipo, cpf_cnpj) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$nome, $email, $senha_hash, $telefone, $endereco, $tipo, $cpf_cnpj])) {
            header('Location: ../index.php?msg=' . urlencode('Cadastro realizado com sucesso! Faça login.') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao cadastrar. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Cadastro de Cliente</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0a2b3e 0%, #1a4a6f 100%);
            padding: 20px;
        }
        .auth-box {
            background: white; border-radius: 20px; padding: 40px;
            width: 100%; max-width: 660px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo-area { text-align: center; margin-bottom: 30px; }
        .logo-area h1 { font-size: 32px; color: #1a4a6f; }
        .logo-area h1 span { color: #d4af37; }
        .logo-area p { color: #666; }
        .section-title {
            font-size: 13px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: #1a4a6f; border-bottom: 2px solid #d4af37;
            padding-bottom: 6px; margin: 24px 0 16px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #333; font-size: 13px; }
        .form-control {
            width: 100%; padding: 10px 12px;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; box-sizing: border-box; transition: all 0.3s;
        }
        .form-control:focus { border-color: #d4af37; outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,0.1); }
        .form-control[readonly] { background: #f5f5f5; color: #555; cursor: default; }
        .cep-group { display: flex; gap: 10px; align-items: flex-end; }
        .cep-group .form-control { flex: 1; }
        .btn-cep {
            padding: 0 18px; height: 40px;
            background: linear-gradient(135deg, #d4af37, #b8962e);
            color: #fff; border: none; border-radius: 8px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            white-space: nowrap; transition: all 0.3s;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-cep:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212,175,55,0.4); }
        .btn-cep:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .cep-status {
            font-size: 12px; margin-top: 5px; padding: 5px 10px;
            border-radius: 5px; display: none;
        }
        .cep-status.success { display: block; color: #155724; background: #d4edda; }
        .cep-status.error   { display: block; color: #721c24; background: #f8d7da; }
        .cep-status.loading { display: block; color: #0c5460; background: #d1ecf1; }
        .btn-submit {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26,74,111,0.3); }
        .register-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .register-link a { color: #d4af37; text-decoration: none; }
        .error-text { color: #c00; font-size: 12px; display: block; margin-top: 4px; }
        .error-msg  { background: #fee; color: #c00; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .auth-box { padding: 25px 15px; }
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-box">
        <div class="logo-area">
            <h1>Service<span>Hub</span></h1>
            <p>Crie sua conta de cliente</p>
        </div>

        <?php if (!empty($erros['geral'])): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $erros['geral'] ?></div>
        <?php endif; ?>

        <form method="post">

            <!-- DADOS PESSOAIS -->
            <div class="section-title"><i class="fas fa-user"></i>&nbsp; Dados Pessoais</div>

            <div class="form-group">
                <label>Nome Completo *</label>
                <input type="text" name="nome" class="form-control"
                       value="<?= htmlspecialchars($nome ?? '') ?>" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="error-text"><?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email ?? '') ?>" required>
                    <?php if (isset($erros['email'])): ?>
                        <span class="error-text"><?= $erros['email'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" id="telefone" class="form-control"
                           value="<?= htmlspecialchars($telefone ?? '') ?>" placeholder="(11) 99999-9999">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Pessoa</label>
                    <select name="tipo" class="form-control">
                        <option value="fisica"   <?= (($tipo ?? '') === 'fisica')   ? 'selected' : '' ?>>Pessoa Física</option>
                        <option value="juridica" <?= (($tipo ?? '') === 'juridica') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control"
                           value="<?= htmlspecialchars($cpf_cnpj ?? '') ?>" placeholder="000.000.000-00">
                </div>
            </div>

            <!-- ENDEREÇO -->
            <div class="section-title"><i class="fas fa-map-marker-alt"></i>&nbsp; Endereço</div>

            <div class="form-group">
                <label>CEP</label>
                <div class="cep-group">
                    <input type="text" name="cep" id="cep" class="form-control"
                           value="<?= htmlspecialchars($cep ?? '') ?>"
                           placeholder="00000-000" maxlength="9" autocomplete="postal-code">
                    <button type="button" class="btn-cep" id="btnBuscarCep">
                        <i class="fas fa-search" id="iconeCep"></i>
                        <span id="textoBtnCep">Buscar</span>
                    </button>
                </div>
                <div class="cep-status" id="cepStatus"></div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Logradouro (Rua / Av.)</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control"
                           value="<?= htmlspecialchars($logradouro ?? '') ?>"
                           placeholder="Preenchido pelo CEP">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" name="numero" id="numero" class="form-control"
                           value="<?= htmlspecialchars($numero ?? '') ?>" placeholder="Ex: 123">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control"
                           value="<?= htmlspecialchars($complemento ?? '') ?>"
                           placeholder="Apto, Sala, Bloco...">
                </div>
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control"
                           value="<?= htmlspecialchars($bairro ?? '') ?>"
                           placeholder="Preenchido pelo CEP">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control"
                           value="<?= htmlspecialchars($cidade ?? '') ?>"
                           placeholder="Preenchido pelo CEP" readonly>
                </div>
                <div class="form-group">
                    <label>Estado (UF)</label>
                    <input type="text" name="estado" id="estado" class="form-control"
                           value="<?= htmlspecialchars($estado ?? '') ?>"
                           placeholder="UF" maxlength="2" readonly>
                </div>
            </div>

            <!-- SENHA -->
            <div class="section-title"><i class="fas fa-lock"></i>&nbsp; Segurança</div>

            <div class="form-row">
                <div class="form-group">
                    <label>Senha *</label>
                    <input type="password" name="senha" class="form-control" required>
                    <?php if (isset($erros['senha'])): ?>
                        <span class="error-text"><?= $erros['senha'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha *</label>
                    <input type="password" name="confirmar_senha" class="form-control" required>
                    <?php if (isset($erros['confirmar_senha'])): ?>
                        <span class="error-text"><?= $erros['confirmar_senha'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus"></i> Cadastrar
            </button>

            <div class="register-link">
                Já tem uma conta? <a href="../index.php">Faça login</a>
            </div>
        </form>
    </div>
</div>

<script>
// ── Máscara CEP ────────────────────────────────────
const inputCep = document.getElementById('cep');
inputCep.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 8);
    if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
    this.value = v;
    if (v.replace('-', '').length === 8) buscarCep();
});

// ── Máscara Telefone ───────────────────────────────
document.getElementById('telefone').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = '(' + v.slice(0,2) + ') ' + v.slice(2,7) + '-' + v.slice(7);
    else if (v.length > 6) v = '(' + v.slice(0,2) + ') ' + v.slice(2,6) + '-' + v.slice(6);
    else if (v.length > 2) v = '(' + v.slice(0,2) + ') ' + v.slice(2);
    this.value = v;
});

// ── Máscara CPF/CNPJ ──────────────────────────────
document.getElementById('cpf_cnpj').addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '');
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d)/, '$1.$2')
             .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        v = v.slice(0, 14)
             .replace(/^(\d{2})(\d)/, '$1.$2')
             .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
             .replace(/\.(\d{3})(\d)/, '.$1/$2')
             .replace(/(\d{4})(\d)/, '$1-$2');
    }
    this.value = v;
});

// ── Busca CEP ─────────────────────────────────────
function setStatus(tipo, msg) {
    const el = document.getElementById('cepStatus');
    el.className = 'cep-status ' + tipo;
    el.textContent = msg;
}

function limparEndereco() {
    ['logradouro', 'bairro', 'cidade', 'estado'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.value = ''; el.removeAttribute('readonly'); }
    });
}

async function buscarCep() {
    const cep = inputCep.value.replace(/\D/g, '');
    if (cep.length !== 8) {
        setStatus('error', 'CEP incompleto — informe os 8 dígitos.');
        return;
    }

    const btn  = document.getElementById('btnBuscarCep');
    const icon = document.getElementById('iconeCep');
    const txt  = document.getElementById('textoBtnCep');
    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';
    txt.textContent = 'Buscando...';
    setStatus('loading', 'Consultando ViaCEP...');

    try {
        const res  = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
        const data = await res.json();

        if (data.erro) {
            setStatus('error', 'CEP não encontrado. Verifique e tente novamente.');
            limparEndereco();
        } else {
            const mapa = { logradouro: data.logradouro, bairro: data.bairro,
                           cidade: data.localidade, estado: data.uf };
            for (const [id, val] of Object.entries(mapa)) {
                const el = document.getElementById(id);
                el.value = val || '';
                if (val) el.setAttribute('readonly', true);
                else     el.removeAttribute('readonly');
            }
            document.getElementById('numero').focus();
            setStatus('success', '✓ ' + data.logradouro + ' — ' + data.localidade + '/' + data.uf);
        }
    } catch (e) {
        setStatus('error', 'Erro ao consultar o CEP. Verifique sua conexão.');
    } finally {
        btn.disabled = false;
        icon.className = 'fas fa-search';
        txt.textContent = 'Buscar';
    }
}

document.getElementById('btnBuscarCep').addEventListener('click', buscarCep);
inputCep.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); buscarCep(); } });
</script>
</body>
</html>
