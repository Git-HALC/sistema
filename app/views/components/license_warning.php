<?php
if (!isset($_SESSION['license_warning'])) {
    return;
}

$diasRestantes = max(0, (int)$_SESSION['license_warning']);
$classe = 'license-warning--yellow';

if ($diasRestantes <= 1) {
    $classe = 'license-warning--red';
} elseif ($diasRestantes <= 3) {
    $classe = 'license-warning--orange';
}
?>
<style>
.license-warning-banner {
    position: sticky;
    top: var(--header-height);
    z-index: 890;
    width: 100%;
    border-bottom: 1px solid transparent;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.license-warning--yellow {
    background: #fef3c7;
    color: #92400e;
    border-bottom-color: #fcd34d;
}
.license-warning--orange {
    background: #fed7aa;
    color: #9a3412;
    border-bottom-color: #fb923c;
}
.license-warning--red {
    background: #fecaca;
    color: #991b1b;
    border-bottom-color: #ef4444;
}
</style>

<div class="license-warning-banner <?php echo $classe; ?>">
    <span aria-hidden="true">&#9888;</span>
    <span>Sua licen&ccedil;a vence em <?php echo $diasRestantes; ?> dias. Entre em contato para renovar.</span>
</div>
