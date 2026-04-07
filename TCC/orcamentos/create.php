<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_cliente  = isCliente();
$is_empresa  = isEmpresa();

$pre_servico_id = (int)($_GET['servico_id'] ?? 0);
$pre_empresa_id = (int)($_GET['empresa_id'] ?? 0);

if ($is_cliente) {
    $clientes = [];
    $cliente_id_fixo = $_SESSION['cliente_id'];
} else {
    $clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
    $cliente_id_fixo = null;
}

$servicos = $pdo->query("SELECT s.*, e.nome_empresa FROM servicos s JOIN empresas e ON e.id = s.empresa_id WHERE s.status=1 ORDER BY e.nome_empresa, s.nome")->fetchAll();

$erros = [];
$cliente_id     = $cliente_id_fixo ?? '';
$data_orcamento = date('Y-m-d');
$data_validade  = date('Y-m-d', strtotime('+30 days'));
$observacoes    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id     = $is_cliente ? $cliente_id_fixo : ($_POST['cliente_id'] ?? '');
    $data_orcamento = $_POST['data_orcamento'] ?? date('Y-m-d');
    $data_validade  = $_POST['data_validade']  ?? '';
    $observacoes    = cleanInput($_POST['observacoes'] ?? '');
    $servicos_ids   = $_POST['servicos']   ?? [];
    $quantidades    = $_POST['quantidades'] ?? [];

    if (empty($cliente_id))   $erros['cliente']  = 'Selecione um cliente.';
    if (empty($servicos_ids)) $erros['servicos'] = 'Adicione pelo menos um serviço.';

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();
            $valor_total = 0;
            $itens = [];
            $empresa_id_orc = null;
            foreach ($servicos_ids as $idx => $sid) {
                $s = $pdo->prepare("SELECT * FROM servicos WHERE id=?");
                $s->execute([$sid]); $s = $s->fetch();
                if ($s) {
                    $qty  = max(1, (int)($quantidades[$idx] ?? 1));
                    $sub  = $s['valor'] * $qty;
                    $valor_total += $sub;
                    $itens[] = [$sid, $qty, $s['valor'], $sub];
                    if ($empresa_id_orc === null) $empresa_id_orc = $s['empresa_id'];
                }
            }
            $stmt = $pdo->prepare(
                "INSERT INTO orcamentos (cliente_id, empresa_id, data_orcamento, data_validade, valor_total, observacoes, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pendente')"
            );
            $stmt->execute([$cliente_id, $empresa_id_orc, $data_orcamento, $data_validade, $valor_total, $observacoes]);
            $oid = $pdo->lastInsertId();
            $ins = $pdo->prepare(
                "INSERT INTO orcamento_itens (orcamento_id, servico_id, quantidade, valor_unitario, subtotal) VALUES (?,?,?,?,?)"
            );
            foreach ($itens as $it) $ins->execute([$oid, ...$it]);
            $pdo->commit();

            $redirect = $is_cliente ? '../dashboard_cliente.php' : 'index.php';
            header('Location: ' . $redirect . '?msg=' . urlencode('Orçamento criado com sucesso!') . '&type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $erros['geral'] = 'Erro ao criar orçamento: ' . $e->getMessage();
        }
    }
}

$servicosJS = json_encode(array_column($servicos, null, 'id'));
$preItens = $pre_servico_id ? json_encode([['id' => $pre_servico_id, 'qty' => 1]]) : '[]';

$back_url = $is_cliente
    ? ($pre_empresa_id ? '../clientes/empresa.php?id='.$pre_empresa_id : '../dashboard_cliente.php')
    : 'index.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Novo Orçamento — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%); border-bottom:1px solid rgba(201,168,76,.2); position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none; }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    .item-row { background:#f8fafc;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:10px;display:grid;grid-template-columns:1fr 100px 36px;gap:10px;align-items:center; }
    .item-row select,.item-row input { margin:0; }
    .btn-remove { background:none;border:1.5px solid var(--border);border-radius:var(--radius-sm);color:var(--red);cursor:pointer;font-size:16px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;transition:all var(--transition); }
    .btn-remove:hover { background:var(--red);color:#fff;border-color:var(--red); }
    .total-preview { background:var(--navy);color:#fff;border-radius:var(--radius);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;margin-top:6px; }
    .total-preview .lbl { font-size:13px;color:var(--slate); }
    .total-preview .val { font-size:28px;font-weight:700;color:var(--gold);font-family:'DM Sans',sans-serif; }
  </style>
</head>
<body>

<?php if ($is_cliente): ?>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="../dashboard_cliente.php">Início</a>
      <a href="../clientes/empresas.php">Empresas</a>
      <a href="index.php">Meus Orçamentos</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>
<?php else: ?>
<header class="main-header">
  <div class="header-content">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><p>Gestão de Serviços &amp; Orçamentos</p></div>
    <nav class="main-nav"><ul>
      <li><a href="../index.php">Início</a></li>
      <li><a href="../servicos/index.php">Serviços</a></li>
      <li><a href="../clientes/index.php">Clientes</a></li>
      <li><a href="index.php" class="active">Orçamentos</a></li>
      <li><a href="../relatorios/index.php">Relatórios</a></li>
    </ul></nav>
  </div>
</header>
<?php endif; ?>

<div class="container">
  <div class="page-title-row">
    <h1>Novo Orçamento</h1>
    <a href="<?= $back_url ?>" class="btn btn-ghost">← Voltar</a>
  </div>

  <div class="form-container">
    <?php if (!empty($erros['geral'])): echo showMessage($erros['geral'], 'error'); endif; ?>

    <form method="post" id="orcForm">
      <div class="form-section">
        <div class="form-section-title">Dados do Orçamento</div>

        <?php if (!$is_cliente): ?>
        <div class="form-group">
          <label>Cliente *</label>
          <select name="cliente_id" class="form-control" required>
            <option value="">Selecione um cliente…</option>
            <?php foreach ($clientes as $c): ?>
            <option value="<?=$c['id']?>" <?=$c['id']==$cliente_id?'selected':''?>>
              <?= htmlspecialchars($c['nome']) ?><?= $c['telefone'] ? ' — '.$c['telefone'] : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($erros['cliente'])): ?><span class="error-text"><?=$erros['cliente']?></span><?php endif; ?>
        </div>
        <?php else: ?>
          <input type="hidden" name="cliente_id" value="<?= $cliente_id_fixo ?>">
          <div class="form-group">
            <label>Cliente</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['cliente_nome']) ?>" disabled>
          </div>
        <?php endif; ?>

        <div class="form-row">
          <div class="form-group">
            <label>Data do Orçamento</label>
            <input type="date" name="data_orcamento" class="form-control" value="<?=$data_orcamento?>">
          </div>
          <div class="form-group">
            <label>Data de Validade</label>
            <input type="date" name="data_validade" class="form-control" value="<?=$data_validade?>">
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title">Serviços</div>
        <?php if (isset($erros['servicos'])): ?><span class="error-text" style="display:block;margin-bottom:12px;"><?=$erros['servicos']?></span><?php endif; ?>
        <div id="items-container"></div>
        <button type="button" class="btn btn-success btn-sm" style="margin-top:10px;" onclick="addRow()">+ Adicionar Serviço</button>
        <div class="total-preview" style="margin-top:16px;">
          <div class="lbl">Total do Orçamento</div>
          <div class="val" id="totalVal">R$ 0,00</div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title">Observações</div>
        <div class="form-group" style="margin-bottom:0;">
          <textarea name="observacoes" class="form-control" rows="3" placeholder="Informações adicionais…"><?= htmlspecialchars($observacoes) ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary btn-lg">Criar Orçamento</button>
        <a href="<?= $back_url ?>" class="btn btn-ghost btn-lg">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
const SERVICOS  = <?= $servicosJS ?>;
const PRE_ITENS = <?= $preItens ?>;

function buildOptions(selectedId) {
  let html = '<option value="">Selecione um serviço…</option>';
  let lastEmp = '';
  for (const id in SERVICOS) {
    const s   = SERVICOS[id];
    const emp = s.nome_empresa || '';
    if (emp !== lastEmp) {
      if (lastEmp) html += '</optgroup>';
      html += `<optgroup label="${emp}">`;
      lastEmp = emp;
    }
    const sel = id == selectedId ? ' selected' : '';
    const val = parseFloat(s.valor).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    html += `<option value="${id}" data-valor="${s.valor}"${sel}>${s.nome} — R$ ${val}</option>`;
  }
  if (lastEmp) html += '</optgroup>';
  return html;
}

function addRow(selectedId='', qty=1) {
  const c = document.getElementById('items-container');
  const d = document.createElement('div');
  d.className = 'item-row';
  d.innerHTML = `
    <select name="servicos[]" class="form-control" onchange="calcTotal()">${buildOptions(selectedId)}</select>
    <input type="number" name="quantidades[]" class="form-control" value="${qty}" min="1" onchange="calcTotal()" oninput="calcTotal()">
    <button type="button" class="btn-remove" onclick="removeRow(this)" title="Remover">×</button>
  `;
  c.appendChild(d);
  calcTotal();
}

function removeRow(btn) {
  const rows = document.querySelectorAll('.item-row');
  if (rows.length <= 1) { alert('Mantenha ao menos um serviço.'); return; }
  btn.closest('.item-row').remove();
  calcTotal();
}

function calcTotal() {
  let total = 0;
  document.querySelectorAll('.item-row').forEach(row => {
    const sel = row.querySelector('select');
    const qty = row.querySelector('input[type=number]');
    if (sel && sel.value && qty) {
      const opt = sel.options[sel.selectedIndex];
      total += (parseFloat(opt.dataset.valor)||0) * (parseInt(qty.value)||0);
    }
  });
  document.getElementById('totalVal').textContent = 'R$ ' + total.toLocaleString('pt-BR',{minimumFractionDigits:2});
}

document.addEventListener('DOMContentLoaded', () => {
  if (PRE_ITENS.length > 0) {
    PRE_ITENS.forEach(it => addRow(it.id, it.qty));
  } else {
    addRow();
  }
});
</script>
</body>
</html>
