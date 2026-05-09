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

// ── AUTO-GERAÇÃO: cliente solicitando serviço com valor já definido ──
if ($is_cliente && $pre_servico_id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sAuto = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND status = 1");
    $sAuto->execute([$pre_servico_id]);
    $sAuto = $sAuto->fetch();

    if ($sAuto && $sAuto['valor'] !== null) {
        // Valor definido → gera orçamento automaticamente
        try {
            $pdo->beginTransaction();
            $data_orc = date('Y-m-d');
            $data_val = date('Y-m-d', strtotime('+30 days'));
            $stmt = $pdo->prepare(
                "INSERT INTO orcamentos (cliente_id, empresa_id, data_orcamento, data_validade, valor_total, observacoes, status)
                 VALUES (?, ?, ?, ?, ?, '', 'pendente')"
            );
            $stmt->execute([$cliente_id_fixo, $sAuto['empresa_id'], $data_orc, $data_val, $sAuto['valor']]);
            $oid = $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO orcamento_itens (orcamento_id, servico_id, quantidade, valor_unitario, subtotal) VALUES (?,?,1,?,?)"
            )->execute([$oid, $pre_servico_id, $sAuto['valor'], $sAuto['valor']]);
            $pdo->commit();
            header('Location: ../dashboard_cliente.php?msg='.urlencode('Orçamento gerado com sucesso! A empresa entrará em contato.').'&type=success');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            // Se falhar, mostra o formulário normalmente
        }
    }
    // Valor NULL → deixa passar para o formulário (sob consulta)
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
            $tem_valor_a_definir = false;
            $itens = [];
            $empresa_id_orc = null;
            foreach ($servicos_ids as $idx => $sid) {
                $s = $pdo->prepare("SELECT * FROM servicos WHERE id=?");
                $s->execute([$sid]); $s = $s->fetch();
                if ($s) {
                    $qty  = max(1, (int)($quantidades[$idx] ?? 1));
                    if ($s['valor'] === null) {
                        $tem_valor_a_definir = true;
                        $sub = null;
                    } else {
                        $sub = (float)$s['valor'] * $qty;
                        $valor_total += $sub;
                    }
                    $itens[] = [$sid, $qty, $s['valor'], $sub];
                    if ($empresa_id_orc === null) $empresa_id_orc = $s['empresa_id'];
                }
            }
            // Se todos os serviços são "a definir", valor_total fica NULL no banco
            $valor_total_db = $tem_valor_a_definir && $valor_total == 0 ? null : $valor_total;
            $stmt = $pdo->prepare(
                "INSERT INTO orcamentos (cliente_id, empresa_id, data_orcamento, data_validade, valor_total, observacoes, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'pendente')"
            );
            $stmt->execute([$cliente_id, $empresa_id_orc, $data_orcamento, $data_validade, $valor_total_db, $observacoes]);
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%); border-bottom:1px solid rgba(200,168,75,.2); position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none;display:flex;align-items:center;gap:6px; }
    .nav-items a:hover { color:#fff;background:rgba(200,168,75,.18); }
    .nav-items a i { font-size:13px;transition:transform .2s;flex-shrink:0; }
    .nav-items a:hover i { transform:scale(1.1); }
    .item-row { background:#f8fafc;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:10px;display:grid;grid-template-columns:1fr 100px 36px;gap:10px;align-items:center;transition:border-color .2s; }
    .item-row:hover { border-color:rgba(200,168,75,.4); }
    .item-row select,.item-row input { margin:0; }
    .btn-remove { background:none;border:1.5px solid var(--border);border-radius:var(--radius-sm);color:var(--red);cursor:pointer;font-size:16px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;transition:all var(--transition); }
    .btn-remove:hover { background:var(--red);color:#fff;border-color:var(--red); }
    .total-preview { background:var(--navy);color:#fff;border-radius:var(--radius);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;margin-top:6px;flex-wrap:wrap;gap:10px; }
    .total-preview .lbl { font-size:13px;color:var(--slate); }
    .total-preview .val { font-size:28px;font-weight:700;color:var(--gold);font-family:'DM Sans',sans-serif; }
    .aviso-consulta { background:#fffbeb;border:1.5px solid #f59e0b;border-radius:var(--radius-sm);padding:12px 16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px;margin-top:10px;line-height:1.5; }
    .aviso-consulta i { color:#f59e0b;font-size:16px;flex-shrink:0; }
    @media (max-width:600px) {
      .item-row { grid-template-columns:1fr 72px 36px;padding:10px 12px;gap:8px; }
      .total-preview .val { font-size:22px; }
      .total-preview { flex-direction:column;align-items:flex-start;gap:6px; }
    }
    @media (max-width:400px) {
      .item-row { grid-template-columns:1fr;gap:8px; }
      .btn-remove { width:100%;height:32px; }
    }
  </style>
</head>
<body>

<?php if ($is_cliente): ?>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="../dashboard_cliente.php"><i class="fas fa-home"></i> Início</a>
      <a href="../clientes/empresas.php"><i class="fas fa-building"></i> Empresas</a>
      <a href="index.php"><i class="fas fa-file-invoice-dollar"></i> Meus Orçamentos</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
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
        <div class="form-section-title"><i class="fas fa-clipboard-list"></i> Dados do Orçamento</div>

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
        <div class="form-section-title"><i class="fas fa-tools"></i> Serviços</div>
        <?php if (isset($erros['servicos'])): ?><span class="error-text" style="display:block;margin-bottom:12px;"><?=$erros['servicos']?></span><?php endif; ?>
        <div id="items-container"></div>
        <button type="button" class="btn btn-success btn-sm" style="margin-top:10px;" onclick="addRow()"><i class="fas fa-plus"></i> Adicionar Serviço</button>
        <div class="total-preview" style="margin-top:16px;">
          <div class="lbl">Total do Orçamento</div>
          <div class="val" id="totalVal">R$ 0,00</div>
        </div>
        <div id="avisoConsulta" class="aviso-consulta" style="display:none;">
          <i class="fas fa-info-circle"></i>
          <span>Este orçamento inclui serviço(s) com <strong>valor a combinar</strong>. A empresa entrará em contato para definir o preço final.</span>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title"><i class="fas fa-comment-alt"></i> Observações</div>
        <div class="form-group" style="margin-bottom:0;">
          <textarea name="observacoes" class="form-control" rows="3" placeholder="Informações adicionais…"><?= htmlspecialchars($observacoes) ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-check"></i> Criar Orçamento</button>
        <a href="<?= $back_url ?>" class="btn btn-ghost btn-lg"><i class="fas fa-times"></i> Cancelar</a>
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
    const valorLabel = s.valor !== null
      ? 'R$ ' + parseFloat(s.valor).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})
      : 'A definir';
    html += `<option value="${id}" data-valor="${s.valor ?? ''}"${sel}>${s.nome} — ${valorLabel}</option>`;
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
  let temADefinir = false;
  document.querySelectorAll('.item-row').forEach(row => {
    const sel = row.querySelector('select');
    const qty = row.querySelector('input[type=number]');
    if (sel && sel.value && qty) {
      const opt = sel.options[sel.selectedIndex];
      if (opt && opt.dataset.valor === '') {
        temADefinir = true;
      } else if (opt) {
        total += (parseFloat(opt.dataset.valor)||0) * (parseInt(qty.value)||1);
      }
    }
  });
  const el = document.getElementById('totalVal');
  const aviso = document.getElementById('avisoConsulta');
  if (temADefinir && total === 0) {
    el.textContent = 'A consultar';
    el.style.fontSize = '20px';
    el.style.color = '#f59e0b';
    aviso.style.display = 'flex';
  } else if (temADefinir) {
    el.textContent = 'R$ ' + total.toLocaleString('pt-BR',{minimumFractionDigits:2}) + ' + consulta';
    el.style.fontSize = '18px';
    el.style.color = '#f59e0b';
    aviso.style.display = 'flex';
  } else {
    el.textContent = 'R$ ' + total.toLocaleString('pt-BR',{minimumFractionDigits:2});
    el.style.fontSize = '';
    el.style.color = '';
    aviso.style.display = 'none';
  }
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
