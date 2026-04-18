<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($titulo ?? 'Usuário'); ?></h1>
        <a href="<?php echo htmlspecialchars(function_exists('tenantUrl') ? tenantUrl('admin/usuarios.php') : '/admin/usuarios.php'); ?>" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Voltar
        </a>
    </div>

    <?php if (!empty($_SESSION['mensagem'])): ?>
        <div class="alert alert-<?php echo $_SESSION['mensagem']['tipo'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['mensagem']['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <?php
    $editando = $editando ?? false;
    $baseUrl = function_exists('tenantUrl') ? tenantUrl('admin/usuarios.php') : '/admin/usuarios.php';
    $action = $editando
        ? $baseUrl . '?action=atualizar'
        : $baseUrl . '?action=salvar';

    $niveisAcesso = $niveisAcesso ?? [];
    if (empty($niveisAcesso)) {
        $niveisAcesso = [
            ['id' => 1, 'nome' => 'Administrador'],
            ['id' => 2, 'nome' => 'Suporte'],
            ['id' => 3, 'nome' => 'Financeiro'],
            ['id' => 4, 'nome' => 'Personalizado'],
        ];
    }

    $descricaoNiveis = [
        1 => 'Acesso total ao sistema',
        2 => 'Dashboard, Clientes, Produtos, Orçamentos, Pedidos, Rel. Pedidos',
        3 => 'Dashboard, Financeiro, Relatórios Financeiros',
        4 => 'Módulos definidos individualmente',
    ];

    $nivelSel = (int) ($form['nivel_acesso_id'] ?? 2);
    $modulosPersonalizacao = $modulosPersonalizacao ?? [];
    $modulosMarcados = array_map(static fn ($id): int => (int) $id, (array) ($form['modulos'] ?? []));
    $modulosMarcados = array_values(array_unique(array_filter($modulosMarcados, static fn (int $id): bool => $id > 0)));
    $podeOperarPdv = !empty($form['pode_operar_pdv']);
    $podeConferirCaixa = !empty($form['pode_conferir_caixa']);
    ?>

    <style>
        #painel-personalizado .painel-personalizado-box {
            background: rgba(255, 255, 255, 0.38);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
            padding: 1rem;
        }

        [data-theme='dark'] #painel-personalizado .painel-personalizado-box {
            background: rgba(17, 24, 39, 0.52);
            border-color: rgba(148, 163, 184, 0.25);
        }

        .modulo-card {
            position: relative;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 14px;
            padding: .95rem;
            min-height: 86px;
            cursor: pointer;
            transition: all .18s ease;
            background: rgba(255, 255, 255, 0.5);
            outline: none;
        }

        [data-theme='dark'] .modulo-card {
            background: rgba(15, 23, 42, 0.45);
        }

        .modulo-card:hover {
            transform: translateY(-1px);
            border-color: rgba(var(--bs-primary-rgb), .55);
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
        }

        .modulo-card:focus-visible {
            box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .22);
        }

        .modulo-card.ativo {
            border-color: rgba(var(--bs-primary-rgb), .85);
            background: rgba(var(--bs-primary-rgb), .14);
        }

        .modulo-checkbox {
            display: none;
        }

        .modulo-icone {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(var(--bs-primary-rgb), .14);
            color: var(--bs-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
    </style>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold">Dados do Usuário</h6>
        </div>
        <div class="card-body">
            <form method="post" action="<?php echo $action; ?>">
                <?php if ($editando): ?>
                    <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" class="form-control" required
                            value="<?php echo htmlspecialchars($form['nome'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">E-mail *</label>
                        <input type="email" name="email" class="form-control" required
                            value="<?php echo htmlspecialchars($form['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Telefone</label>
                        <input type="text" name="telefone" class="form-control"
                            value="<?php echo htmlspecialchars($form['telefone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nível de Acesso *</label>
                        <select name="nivel_acesso_id" id="nivel_acesso_id" class="form-select" required>
                            <?php foreach ($niveisAcesso as $nivel): ?>
                                <?php
                                $nivelId = (int) ($nivel['id'] ?? 0);
                                if ($nivelId <= 0) {
                                    continue;
                                }
                                $descricaoNivel = $descricaoNiveis[$nivelId] ?? '';
                                $textoNivel = (string) ($nivel['nome'] ?? ('Nível ' . $nivelId));
                                $textoOption = $textoNivel . ($descricaoNivel !== '' ? ' - ' . $descricaoNivel : '');
                                ?>
                                <option value="<?php echo $nivelId; ?>" <?php echo $nivelSel === $nivelId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($textoOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12" id="painel-personalizado" style="display:none;">
                        <div class="painel-personalizado-box mt-2">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3 gap-2">
                                <div>
                                    <h6 class="mb-0">Permissões personalizadas</h6>
                                    <small class="text-muted">Defina acessos extras e os módulos liberados para este usuário.</small>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pode_operar_pdv" id="pode_operar_pdv" <?php echo $podeOperarPdv ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="pode_operar_pdv">Pode operar PDV</label>
                                        <div class="form-text">Permite abrir caixa e registrar vendas no modo PDV.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="pode_conferir_caixa" id="pode_conferir_caixa" <?php echo $podeConferirCaixa ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="pode_conferir_caixa">Pode conferir caixas</label>
                                        <div class="form-text">Libera conferência, relatórios e visão gerencial dos caixas.</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($modulosPersonalizacao)): ?>
                                <div class="alert alert-warning mb-0">Nenhum módulo cadastrado para personalização.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($modulosPersonalizacao as $modulo): ?>
                                        <?php
                                        $moduloId = (int) ($modulo['id'] ?? 0);
                                        if ($moduloId <= 0) {
                                            continue;
                                        }

                                        $checked = in_array($moduloId, $modulosMarcados, true);
                                        if (!$checked && empty($modulosMarcados) && !empty($modulo['ativo'])) {
                                            $checked = true;
                                        }

                                        $nomeModulo = (string) ($modulo['nome'] ?? ('Módulo ' . $moduloId));
                                        $iconeModulo = trim((string) ($modulo['icone'] ?? ''));
                                        if ($iconeModulo === '') {
                                            $iconeModulo = 'fas fa-cube';
                                        }
                                        ?>
                                        <div class="col-12 col-md-6 col-xl-4">
                                            <div
                                                class="modulo-card <?php echo $checked ? 'ativo' : ''; ?>"
                                                data-modulo-card="1"
                                                tabindex="0"
                                                role="checkbox"
                                                aria-checked="<?php echo $checked ? 'true' : 'false'; ?>"
                                            >
                                                <input
                                                    type="checkbox"
                                                    class="modulo-checkbox"
                                                    name="modulos[]"
                                                    value="<?php echo $moduloId; ?>"
                                                    <?php echo $checked ? 'checked' : ''; ?>
                                                >

                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="modulo-icone"><i class="<?php echo htmlspecialchars($iconeModulo); ?>"></i></span>
                                                    <div class="d-flex flex-column">
                                                        <strong><?php echo htmlspecialchars($nomeModulo); ?></strong>
                                                        <small class="text-muted">Módulo customizável</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Senha <?php echo $editando ? '<small class="text-muted">(deixe vazio para não alterar)</small>' : '*'; ?>
                        </label>
                        <input type="password" name="senha" class="form-control"
                            <?php echo $editando ? '' : 'required'; ?> minlength="6"
                            autocomplete="new-password">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Confirmar Senha</label>
                        <input type="password" name="confirmar_senha" class="form-control"
                            <?php echo $editando ? '' : 'required'; ?> minlength="6"
                            autocomplete="new-password">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <?php $ativo = (int) ($form['ativo'] ?? 1); ?>
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativoCheck"
                                <?php echo $ativo ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ativoCheck">Ativo</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Salvar
                    </button>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>" class="btn btn-outline-secondary ms-1">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectNivel = document.getElementById('nivel_acesso_id');
    const painelPersonalizado = document.getElementById('painel-personalizado');

    const atualizarVisibilidadePainel = function () {
        if (!selectNivel || !painelPersonalizado) {
            return;
        }

        const nivelSelecionado = Number(selectNivel.value || 0);
        painelPersonalizado.style.display = nivelSelecionado === 4 ? 'block' : 'none';
    };

    atualizarVisibilidadePainel();
    if (selectNivel) {
        selectNivel.addEventListener('change', atualizarVisibilidadePainel);
    }

    document.querySelectorAll('[data-modulo-card="1"]').forEach(function (card) {
        const checkbox = card.querySelector('.modulo-checkbox');
        if (!checkbox) {
            return;
        }

        const sincronizarEstado = function () {
            card.classList.toggle('ativo', checkbox.checked);
            card.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
        };

        card.addEventListener('click', function (event) {
            event.preventDefault();
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });

        card.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            checkbox.checked = !checkbox.checked;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });

        checkbox.addEventListener('change', sincronizarEstado);
        sincronizarEstado();
    });
});
</script>
