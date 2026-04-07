<?php
function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

function showMessage($message, $type = 'success') {
    $icon = $type === 'success' ? '✓' : '✕';
    return "<div class='alert alert-{$type}'><span>{$icon}</span> {$message}</div>";
}

function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '—';
    return (new DateTime($date))->format($format);
}

function formatMoney($value) {
    return 'R$ ' . number_format($value ?? 0, 2, ',', '.');
}

function statusBadge($status) {
    $map = [
        'pendente'  => ['label' => 'Pendente',  'class' => 'badge-pendente'],
        'aprovado'  => ['label' => 'Aprovado',  'class' => 'badge-aprovado'],
        'rejeitado' => ['label' => 'Rejeitado', 'class' => 'badge-rejeitado'],
        'concluido' => ['label' => 'Concluído', 'class' => 'badge-concluido'],
        'expirado'  => ['label' => 'Expirado',  'class' => 'badge-expirado'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-secondary'];
    return "<span class='badge {$s['class']}'>{$s['label']}</span>";
}
