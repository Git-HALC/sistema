<?php
defined('APP_PATH') || die('Acesso negado');

$empresa = $empresa ?? null;
$pedidosFaturaveis = is_array($pedidosFaturaveis ?? null) ? $pedidosFaturaveis : [];
$csrfToken = \App\Support\CsrfProtection::token();

$formatarCnpj = static function (?string $cnpj): string {
    $valor = preg_replace('/\D+/', '', (string)$cnpj) ?? '';
    if (strlen($valor) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $valor) ?? $valor;
    }
    return $valor !== '' ? $valor : '?';
};

$ambienteHomologacao = $empresa !== null && (string)$empresa->ambiente_nfe === '2';
$logPath = defined('APP_PATH') ? APP_PATH . '/storage/logs/fiscal.log' : __DIR__ . '/../../../storage/logs/fiscal.log';
$conteudoLog = '(nenhum log registrado ainda)';
if (file_exists($logPath)) {
    $linhas = file($logPath, FILE_IGNORE_NEW_LINES);
    $ultimas = is_array($linhas) ? array_slice(array_reverse($linhas), 0, 30) : [];
    $conteudoLog = htmlspecialchars(implode("\n", $ultimas));
}
?>

<div class="container-fluid">
    <div class="mt-3 mb-3">
        <h1 class="h3 mb-0 text-gray-800">Painel de Homologação</h1>
    </div>

    <div class="alert alert-warning d-flex align-items-center mb-3">
        <i class="fas fa-exclamation-triangle fa-lg me-2"></i>
        <div><strong>AMBIENTE DE HOMOLOGAÇÃO</strong> - Nenhuma NF-e emitida aqui tem validade fiscal. Use apenas para testes com a SEFAZ.</div>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-2 fw-bold text-primary">Status do Ambiente</div>
                <div class="card-body text-center">
                    <?php if ($ambienteHomologacao): ?>
                        <span class="badge bg-warning text-dark" style="font-size:1.1rem;padding:10px 20px">HOMOLOGAÇÃO</span>
                        <p class="text-muted mt-2 small">NF-e de teste - sem validade fiscal</p>
                    <?php else: ?>
                        <span class="badge bg-danger" style="font-size:1.1rem;padding:10px 20px">PRODUÇÃO ATIVA</span>
                        <div class="alert alert-danger mt-2 small text-start">
                            <i class="fas fa-exclamation-octagon me-1"></i>
                            Atenção: NF-e emitidas aqui têm validade fiscal real.
                        </div>
                    <?php endif; ?>
                    <hr>
                    <dl class="row text-start small mb-0">
                        <dt class="col-5">CNPJ</dt>
                        <dd class="col-7 font-monospace"><?php echo htmlspecialchars($empresa !== null ? $formatarCnpj($empresa->cnpj) : '?'); ?></dd>
                        <dt class="col-5">Série</dt>
                        <dd class="col-7"><?php echo htmlspecialchars((string)($empresa->serie_nfe ?? '?')); ?></dd>
                        <dt class="col-5">Próximo nº</dt>
                        <dd class="col-7"><?php echo htmlspecialchars((string)($empresa->proximo_numero_nfe ?? '?')); ?></dd>
                    </dl>
                    <a href="<?php echo htmlspecialchars(tenantUrl('admin/empresa-dados.php')); ?>" class="btn btn-outline-secondary btn-sm w-100 mt-3">
                        <i class="fas fa-cog me-1"></i> Alterar configurações
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-2 fw-bold text-primary">Testar Emissão</div>
                <div class="card-body">
                    <?php if ($pedidosFaturaveis === []): ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Nenhum pedido ou serviço com produtos disponível para teste.
                        </div>
                    <?php else: ?>
                        <label for="selectPedido" class="form-label">Selecione um pedido ou serviço faturado</label>
                        <select id="selectPedido" class="form-select mb-3">
                            <option value="">-- Selecione um documento --</option>
                            <?php foreach ($pedidosFaturaveis as $p): ?>
                                <option value="<?php echo htmlspecialchars((string)(($p['origem_tipo'] ?? 'PEDIDO') === 'SERVICO' ? ($p['servico_id'] ?? '') : ($p['pedido_id'] ?? ''))); ?>" data-origem-tipo="<?php echo htmlspecialchars((string)($p['origem_tipo'] ?? 'PEDIDO')); ?>" data-pedido-id="<?php echo htmlspecialchars((string)($p['pedido_id'] ?? '')); ?>" data-servico-id="<?php echo htmlspecialchars((string)($p['servico_id'] ?? '')); ?>" data-numero="<?php echo htmlspecialchars((string)($p['numero'] ?? '')); ?>" data-cliente-id="<?php echo htmlspecialchars((string)($p['cliente_id'] ?? '')); ?>">
                                    <?php echo htmlspecialchars((string)($p['origem_tipo'] ?? 'PEDIDO') . ' Nº ' . (string)($p['numero'] ?? '') . ' - ' . (string)($p['cliente_nome'] ?? '') . ' - R$ ' . number_format((float)($p['valor_total'] ?? 0), 2, ',', '.')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="d-flex gap-2 mb-3">
                            <button id="btnValidarHom" class="btn btn-outline-info btn-sm" disabled>
                                <i class="fas fa-check-circle me-1"></i> Validar Dados Fiscais
                            </button>
                            <button id="btnEmitirHom" class="btn btn-warning btn-sm" disabled>
                                <i class="fas fa-paper-plane me-1"></i> Emitir NF-e de Teste
                            </button>
                        </div>

                        <div id="resultadoValidacao"></div>
                        <div id="retornoSefaz" class="mt-3"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary"><i class="fas fa-terminal me-1"></i>Log fiscal.log</span>
                    <button id="btnAtualizarLog" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-sync me-1"></i> Atualizar
                    </button>
                </div>
                <div class="card-body p-0">
                    <pre id="conteudoLog" class="bg-dark text-success p-3 mb-0 font-monospace" style="font-size:11px;min-height:80px;max-height:280px;overflow-y:auto"><?php echo $conteudoLog; ?></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const fiscalUrl = '<?= htmlspecialchars(
    function_exists('tenantUrl')
        ? tenantUrl('admin/fiscal.php')
        : (isset($tenant_slug)
            ? '/sistema_dm/public/' . $tenant_slug . '/admin/fiscal.php'
            : '/admin/fiscal.php')
) ?>';
const fiscalCsrfToken = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';

document.addEventListener('DOMContentLoaded', function() {
    const selectPedido = document.getElementById('selectPedido');
    const btnValidarHom = document.getElementById('btnValidarHom');
    const btnEmitirHom = document.getElementById('btnEmitirHom');
    const resultadoDiv = document.getElementById('resultadoValidacao');
    const retornoDiv = document.getElementById('retornoSefaz');
    const btnLog = document.getElementById('btnAtualizarLog');
    const preLog = document.getElementById('conteudoLog');

    selectPedido?.addEventListener('change', function() {
        btnValidarHom.disabled = !this.value;
        btnEmitirHom.disabled = true;
        resultadoDiv.innerHTML = '';
        retornoDiv.innerHTML = '';
    });

    btnValidarHom?.addEventListener('click', function() {
        const pedidoId = selectPedido.value;
        btnValidarHom.disabled = true;
        btnValidarHom.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Validando...';

        fetch(fiscalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': fiscalCsrfToken
            },
            body: 'action=validar&csrf_token=' + encodeURIComponent(fiscalCsrfToken)
                + '&' + (selectPedido.options[selectPedido.selectedIndex]?.dataset.origemTipo === 'SERVICO' ? 'servico_id=' : 'pedido_id=')
                + encodeURIComponent(pedidoId)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.valido) {
                resultadoDiv.innerHTML = '<div class="alert alert-success mb-0">'
                    + '<i class="fas fa-check-circle me-2"></i>'
                    + 'Dados fiscais válidos. Pronto para emitir.</div>';
                btnEmitirHom.disabled = false;
            } else {
                const clienteId = selectPedido.options[selectPedido.selectedIndex]?.dataset.clienteId || '';
                const erros = data.erros.map(function(e) { return '<li>' + e + '</li>'; }).join('');
                resultadoDiv.innerHTML = '<div class="alert alert-danger mb-0">'
                    + '<strong><i class="fas fa-times-circle me-1"></i>Erros encontrados:</strong>'
                    + '<ul class="mb-0 mt-1">' + erros + '</ul>'
                    + montarAcoesCorrecao(data.erros, clienteId)
                    + '</div>';
            }
            btnValidarHom.disabled = false;
            btnValidarHom.innerHTML = '<i class="fas fa-check-circle"></i> Validar Dados Fiscais';
        })
        .catch(function() {
            Swal.fire('Erro', 'Falha ao validar.', 'error');
            btnValidarHom.disabled = false;
            btnValidarHom.innerHTML = '<i class="fas fa-check-circle"></i> Validar Dados Fiscais';
        });
    });

    btnEmitirHom?.addEventListener('click', function() {
        const opt = selectPedido.options[selectPedido.selectedIndex];
        const numero = opt.dataset.numero;

        Swal.fire({
            title: 'Emitir NF-e de Teste?',
            html: 'Esta NF-e será enviada à SEFAZ em ambiente de <strong>HOMOLOGAÇÃO</strong>.<br><small class="text-muted">Sem validade fiscal.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Emitir Teste',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ffc107',
            footer: '<i class="fas fa-info-circle text-info"></i> Pedido nº ' + numero
        }).then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Comunicando com SEFAZ...',
                allowOutsideClick: false,
                didOpen: function() { Swal.showLoading(); }
            });

            fetch(fiscalUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': fiscalCsrfToken
                },
                body: 'action=emitir&csrf_token=' + encodeURIComponent(fiscalCsrfToken)
                    + '&' + (selectPedido.options[selectPedido.selectedIndex]?.dataset.origemTipo === 'SERVICO' ? 'servico_id=' : 'pedido_id=')
                    + encodeURIComponent(selectPedido.value)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                Swal.close();
                const cor = data.sucesso ? 'text-success' : 'text-danger';
                retornoDiv.innerHTML = '<div class="card mt-2">'
                    + '<div class="card-header bg-dark text-white py-2 small fw-bold">'
                    + '<i class="fas fa-server me-1"></i>Retorno da SEFAZ</div>'
                    + '<pre class="bg-dark ' + cor + ' p-3 mb-0 font-monospace" style="font-size:11px;max-height:200px;overflow-y:auto">'
                    + escHtml(JSON.stringify(data, null, 2)) + '</pre></div>';

                if (!data.sucesso) {
                    Swal.fire('Erro na emissão', data.erro || 'Verifique o log.', 'error');
                }
            })
            .catch(function() {
                Swal.fire('Erro', 'Falha na comunicação.', 'error');
            });
        });
    });

    btnLog?.addEventListener('click', function() {
        const btn = this;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        fetch(fiscalUrl + '?action=log', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) {
                preLog.textContent = data.log || '(nenhum log registrado ainda)';
            }
            btn.innerHTML = '<i class="fas fa-sync me-1"></i> Atualizar';
        })
        .catch(function() {
            btn.innerHTML = '<i class="fas fa-sync me-1"></i> Atualizar';
        });
    });

    function montarAcoesCorrecao(erros, clienteId) {
        const links = [];
        if (clienteId && erros.some(function(e) { return /cliente/i.test(e); })) {
            links.push('<a class="btn btn-sm btn-outline-primary mt-2 me-2" href="' + fiscalUrl.replace('/fiscal.php', '/clientes.php?action=editar&id=' + encodeURIComponent(clienteId)) + '"><i class="fas fa-user-edit me-1"></i>Corrigir cliente</a>');
        }
        if (erros.some(function(e) { return /empresa|certificado|cnpj emitente|emitente/i.test(e); })) {
            links.push('<a class="btn btn-sm btn-outline-secondary mt-2" href="' + fiscalUrl.replace('/fiscal.php', '/empresa-dados.php') + '"><i class="fas fa-cog me-1"></i>Corrigir empresa</a>');
        }
        return links.length ? '<div class="mt-2">' + links.join('') + '</div>' : '';
    }

    function escHtml(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
