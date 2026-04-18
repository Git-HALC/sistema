# Auditoria de Seguranca - Sistema DM

Data: 2026-03-25
Escopo: aplicacao principal em `public/` + modulos em `app/` + painel `license-system/` + configuracoes em `config/`
Stack: PHP 8.2 + PDO + PostgreSQL

## Resumo executivo

O sistema possui boas iniciativas pontuais, como uso extensivo de `PDO::prepare`, `PermissionGate` em parte dos modulos e um painel `license-system` com sessao endurecida e protecao CSRF. Ainda assim, a auditoria encontrou riscos relevantes na aplicacao principal:

- segredos e credenciais hardcoded em configuracao;
- ausencia sistemica de CSRF na aplicacao principal;
- sessao principal sem endurecimento equivalente ao `license-system`;
- upload administrativo com suporte a SVG em caminho publico;
- armazenamento sensivel em texto puro para certificado e senha de certificado;
- transporte inseguro ou downgrade de integracao com API de licenca;
- ausencia de hardening HTTP basico.

## Superficie de ataque mapeada

### Entrypoints HTTP expostos

- `public/index.php`
- `public/login.php`
- `public/auth.php`
- `public/logout.php`
- `public/landing.php`
- `public/blocked.php`
- `public/orcamento_pdf_publico.php`
- `public/admin/*.php`
- `public/admin/financeiro/*.php`
- `public/admin/relatorios/**/*.php`
- `public/admin/pedidos/update-status.php`
- `license-system/admin/*.php`
- `license-system/admin/empresas/*.php`
- `license-system/api/validar-licenca.php`

### Modulos principais

- Usuarios
- Clientes
- Produtos
- Orcamentos
- Pedidos
- Financeiro
- Fiscal
- Relatorios
- Empresa Dados
- License system / multi-tenant

### Integracoes e dependencias externas

- API de licenca via `LICENSE_API_URL`
- envio de e-mail com `mail()`
- bibliotecas PDF: `dompdf/dompdf`, `tecnickcom/tcpdf`
- stack fiscal: `nfephp-org/sped-nfe`, `nfephp-org/sped-common`, `nfephp-org/sped-gtin`

## Dependencias auditadas

Resultado do `composer audit --locked`: nenhuma advisory encontrada no momento da consulta.

Pacotes Composer instalados:

- `dompdf/dompdf` 3.1.4
- `tecnickcom/tcpdf` 6.10.1
- `nfephp-org/sped-nfe` 5.2.5
- `nfephp-org/sped-common` 5.1.16
- `nfephp-org/sped-gtin` 1.1.2
- `justinrainbow/json-schema` 5.3.2
- `masterminds/html5` 2.10.0
- `sabberworm/php-css-parser` 9.1.0
- `thecodingmachine/safe` 3.4.0

Observacao:

- `package.json` contem `@anthropic-ai/sdk`, mas nao ha lockfile Node nem evidencias de uso operacional no runtime web auditado.

## Achados priorizados

### Critica

1. Credenciais e segredos hardcoded com defaults inseguros

Impacto:

- comprometimento total do banco principal e do banco master;
- falsificacao de tokens/links assinados;
- risco de takeover em ambientes que usem configuracao default.

Evidencias:

- `config/database.php`: senha fixa `masterkey`
- `license-system/config/config.php`: `MASTER_DB_PASS` default `masterkey`
- `license-system/config/config.php`: `SECRET_KEY` default `TROQUE_ESTA_SECRET_KEY_IMEDIATAMENTE`
- `app/Support/ShareLinkSigner.php`: fallback `troque-esta-chave-orcamento-share-em-producao`

Remediacao:

- mover todos os segredos para variaveis de ambiente ou secret manager;
- falhar o boot se segredo obrigatorio nao estiver definido;
- rotacionar imediatamente senha master, segredo de licenca e segredo do assinador de links;
- revisar historico e backups assumindo exposicao previa.

### Alta

2. Ausencia de protecao CSRF na aplicacao principal

Impacto:

- exclusao, alteracao e emissao/cancelamento por requisicoes forjadas a partir do navegador de usuario autenticado;
- risco elevado em funcoes administrativas.

Evidencias:

- `app/Modules/Usuarios/UsuariosController.php`: operacoes POST/AJAX sem validacao CSRF
- `app/Modules/Fiscal/NotaFiscalController.php`: `emitir`, `cancelar`, `validar` exigem apenas POST
- `public/admin/settings.php`: POST administrativo sem token CSRF
- `public/admin/pedidos/update-status.php`: endpoint POST JSON sem token CSRF

Contraste positivo:

- `license-system/includes/auth.php` implementa CSRF corretamente, mas o padrao nao foi replicado na aplicacao principal.

Remediacao:

- adotar middleware/utilitario unico de CSRF para todos os POST/PUT/PATCH/DELETE;
- exigir token tambem para chamadas AJAX e JSON;
- aplicar validacao centralizada no bootstrap/dispatch.

3. Sessao da aplicacao principal sem hardening equivalente

Impacto:

- maior risco de fixation/hijacking;
- ausencia de `SameSite`, `HttpOnly` e politica de cookie controlada;
- comportamento dependente de defaults do PHP/browser.

Evidencias:

- `public/auth.php`: login nao executa `session_regenerate_id(true)` apos autenticar
- `config/tenant.php`: inicia sessao sem `session_set_cookie_params`
- `license-system/includes/auth.php`: usa `session_set_cookie_params(...)`, `SameSite=Strict` e `session_regenerate_id(true)` no painel de licencas, mostrando inconsistencia de postura

Remediacao:

- configurar cookie de sessao da app principal com `secure`, `httponly`, `samesite=Lax` ou `Strict`;
- regenerar sessao no login e em elevacao de privilegio;
- definir timeout de inatividade e invalidacao explicita.

4. Upload administrativo com SVG em diretorio publico

Impacto:

- risco de stored XSS ou conteudo ativo hospedado dentro do proprio dominio;
- vetor util para roubo de sessao/admin se SVG malicioso for renderizado.

Evidencias:

- `public/admin/settings.php`: aceita `image/svg+xml`
- `public/admin/settings.php`: grava upload em `public/uploads/settings/`
- `public/admin/settings.php`: persistencia do caminho em `config/site_settings.php`

Remediacao:

- bloquear SVG ou sanitiza-lo fora de banda;
- armazenar uploads fora do webroot;
- servir arquivos com tipo controlado e `Content-Disposition: attachment` quando aplicavel;
- validar extensao e conteudo assincronicamente.

5. Certificado digital e senha do certificado armazenados sem protecao adequada

Impacto:

- comprometimento fiscal e criptografico do tenant;
- reutilizacao de chave privada em emissao indevida de NF-e.

Evidencias:

- `app/Modules/EmpresaDados/EmpresaDadosRepository.php`: persiste `certificado_path` e `certificado_senha`
- `app/Modules/EmpresaDados/EmpresaDadosService.php`: salva arquivo em `storage/certificados`
- `app/views/empresa-dados/form.php`: exibe e permite editar `certificado_senha`

Remediacao:

- criptografar senha do certificado em repouso com chave fora do banco;
- restringir ACL do diretorio do certificado para conta do processo;
- registrar rotacao e trilha de acesso;
- considerar armazenamento em secret manager ou keystore dedicado.

6. Integracao com API de licenca permite transporte inseguro e fallback por GET

Impacto:

- exposicao de chave de licenca e nome do banco em rede insegura;
- maior risco de interceptacao e replay;
- superficie extra por downgrade de metodo.

Evidencias:

- `config/license.php`: default usa `http://localhost/license-system/api/validar-licenca.php`
- `app/Support/LicenseValidator.php`: fallback de POST para `file_get_contents` e depois GET query string

Remediacao:

- exigir HTTPS/TLS valido;
- remover fallback GET;
- autenticar a chamada entre sistemas com segredo compartilhado ou assinatura HMAC;
- limitar origem e incluir timeout/telemetria de falha.

7. Provisionamento de tenant concede privilegios excessivos ao role de banco

Impacto:

- aumento do impacto em caso de SQLi ou comprometimento de credencial da app;
- capacidade de `TRIGGER`, `EXECUTE`, `CREATE` e alteracoes futuras desnecessarias.

Evidencias:

- `license-system/admin/empresas/criar.php`: grants amplos em schema, tabelas, sequences e functions

Remediacao:

- aplicar principio do menor privilegio;
- separar role de migracao da role de runtime;
- remover `CREATE`, `TRIGGER`, `TRUNCATE` e `EXECUTE` se nao forem estritamente necessarios em producao.

### Media

8. Login principal sem rate limiting, lockout ou antiforce brute force

Impacto:

- viabiliza credential stuffing e enumeracao operacional;
- combinado com logs verbosos aumenta risco de coleta indevida.

Evidencias:

- `public/auth.php`: registra tentativas, mas nao limita nem bloqueia
- `license-system/api/validar-licenca.php`: possui rate limit por chave, mostrando inconsistencia entre superficies

Remediacao:

- rate limit por IP + usuario;
- backoff progressivo;
- lock temporario e monitoramento de abuso;
- MFA para perfis administrativos.

9. Logging excessivo e exibicao de erros no endpoint de autenticacao

Impacto:

- vazamento de PII e metadados sensiveis em logs;
- ampliacao de superficie para engenharia reversa e enumeracao.

Evidencias:

- `public/auth.php`: `ini_set('display_errors', 1)`
- `public/auth.php`: loga IP, e-mail, SQL e metadados do usuario encontrado

Remediacao:

- desabilitar `display_errors` fora de desenvolvimento;
- mascarar e-mails/IPs quando possivel;
- nunca registrar SQL sensivel ou entradas autenticaveis em texto puro.

10. Ausencia de cabecalhos HTTP de seguranca

Impacto:

- maior risco de clickjacking, MIME sniffing e reducao de defesa em profundidade.

Evidencias:

- `.htaccess` contem apenas rewrite e `Options -Indexes`
- nao foram encontrados `CSP`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `HSTS` no codigo auditado

Remediacao:

- adicionar pelo menos:
  - `Content-Security-Policy`
  - `X-Frame-Options: DENY` ou `SAMEORIGIN`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Strict-Transport-Security` em ambiente HTTPS

11. Inconsistencias de autorizacao entre modulos legados e modulares

Impacto:

- risco de bypass em fluxos legados ou futuras regressões;
- verificacoes distribuidas dificultam garantia uniforme.

Evidencias:

- parte dos modulos usa `PermissionGate`;
- parte usa validacao manual de `user_role`;
- parte usa apenas `isset($_SESSION['user_id'])` antes de complementar com gate.

Remediacao:

- padronizar guard de autenticacao/autorizacao central;
- mover verificacao para middleware/dispatcher unico;
- adicionar testes de autorizacao por endpoint.

### Baixa

12. Enumeracao indireta de tenants na landing

Impacto:

- facilita descoberta de slugs/empresas validas;
- insumo para ataques direcionados.

Evidencias:

- `public/landing.php?action=resolve_empresa` resolve tenant e retorna URL de login.

Remediacao:

- reduzir detalhe das mensagens;
- rate limit por IP;
- registrar abuso e considerar desafio adicional em volume alto.

## Modelagem de ameacas (STRIDE)

### Ativos criticos

- credenciais de banco master e tenant
- sessoes autenticadas de usuarios e admins
- certificados digitais e senha do certificado
- dados financeiros, fiscais, clientes e usuarios
- integridade de permissao por modulo
- integridade da configuracao multi-tenant

### Fronteiras de confianca

- navegador do usuario -> `public/*`
- navegador admin licenca -> `license-system/*`
- aplicacao tenant -> banco tenant
- aplicacao tenant -> banco master / API de licenca
- admin settings -> upload publico

### STRIDE resumido

- Spoofing:
  - fixation/hijacking de sessao principal
  - tentativa de brute force no login principal
- Tampering:
  - CSRF em operacoes autenticadas
  - alteracao de configuracao via uploads e settings
- Repudiation:
  - logs existem, mas sao verbosos e nao padronizados
- Information Disclosure:
  - segredos hardcoded
  - senha de certificado em texto puro
  - API de licenca por HTTP/GET
- Denial of Service:
  - brute force sem rate limit
  - possivel abuso do resolver de tenant
- Elevation of Privilege:
  - grants excessivos no PostgreSQL
  - upload SVG podendo virar XSS/admin takeover

## Conformidade e referenciais

### OWASP Top 10

- A01 Broken Access Control: exposicao por verificacoes dispersas e CSRF
- A02 Cryptographic Failures: segredo default, senha de certificado em texto puro
- A03 Injection: nao houve evidencias fortes de SQLi classica nas consultas principais; uso de `prepare` esta razoavel
- A05 Security Misconfiguration: `display_errors`, falta de security headers, grants amplos, HTTP interno
- A07 Identification and Authentication Failures: sessao sem regeneracao e sem rate limit
- A08 Software and Data Integrity Failures: configuracao e uploads administrativos sem endurecimento suficiente
- A09 Security Logging and Monitoring Failures: logging presente, mas com excesso de dados sensiveis e pouca padronizacao de alertas

### PCI-DSS

Nao ha evidencias de processamento direto de cartao no escopo auditado. Mesmo assim, ha gaps relevantes que conflitam com principios PCI:

- segredos hardcoded;
- controle fraco de acesso de sessao;
- ausencia de hardening basico;
- armazenamento sensivel sem protecao adequada.

Conclusao PCI:

- nao pronto para afirmar aderencia forte a PCI-DSS;
- ambiente exigiria remediation antes de qualquer escopo com dados de pagamento.

## Recomendacoes prioritarias

### 0-7 dias

- rotacionar todos os segredos e remover defaults inseguros
- implementar CSRF global na aplicacao principal
- endurecer cookie/sessao principal e regenerar ID no login
- bloquear SVG em upload administrativo
- desabilitar `display_errors` em producao

### 7-30 dias

- criptografar senha de certificado e revisar armazenamento do certificado
- obrigar HTTPS e remover fallback GET da API de licenca
- revisar grants do PostgreSQL por tenant
- adicionar rate limiting no login principal e endpoints sensiveis
- adicionar security headers e revisar uploads publicos

### 30-60 dias

- unificar middleware de auth/authz
- adicionar testes automatizados de autorizacao e CSRF
- criar checklist de hardening de deploy
- instituir varredura recorrente de dependencias e segredos em CI

## Resultado final

Postura atual: moderadamente exposta

Nivel de risco geral: alto

Principais causas-raiz:

- controles de seguranca implementados de forma inconsistente entre subsistemas;
- segredos e configuracoes sensiveis misturados ao codigo;
- falta de centralizacao de controles transversais como CSRF, sessao e headers.
