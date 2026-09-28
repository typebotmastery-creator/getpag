# Publicar na VPS — Guia Operacional

Este documento é o passo a passo para levar as alterações do projeto local (`F:\checkout`) para a VPS, mantendo o que já funciona em produção.

---

## 1. A regra de ouro

**NUNCA copie `config/config.php` para a VPS.**

Esse arquivo contém, no ambiente local:

| Item | Valor local | Por que não vai |
|---|---|---|
| `LICENCA_DESATIVADA` | `true` | Desliga a checagem de licença do produto. Publicando isso, o software é vendido sem proteção. |
| `DB_PASS` | senha local | Errada para o Docker da VPS |
| `MAIL_UNSUBSCRIBE_SECRET` | segredo local | Previsível se vazar; precisa ser outro |

O `.htaccess` da VPS já bloqueia a pasta `config/` por completo, então mesmo que o arquivo suba, ele fica inacessível pela web. Ainda assim, não envie.

---

## 2. Antes de começar — backup

Na VPS, rode antes de mexer em qualquer coisa:

```bash
# 1. Backup do banco
docker exec -i <container-mysql> mysqldump -u gatewaypro -p'gatewaypro_secret_2024' checkout > backup_checkout_$(date +%Y%m%d_%H%M).sql

# 2. Backup dos arquivos que serão alterados
mkdir -p /backup/app
cp .htaccess produto_config.php desinscrever.php /backup/app/ 2>/dev/null
cp -r api helpers assets views /backup/app/ 2>/dev/null
```

Guarde esse backup. É o que permite desfazer em 1 minuto.

---

## 3. Arquivos que ENTRAM na VPS (18)

Copie estes do `F:\checkout` para a pasta do app na VPS:

**Raiz**
```
.htaccess
checkout.php
desinscrever.php
produto_config.php
favicon.ico
verificar-config.php       (rode, confira, e apague — veja seção 5.1)
```

**Pastas**
```
api/admin_api.php
api/api.php
api/forgot_password.php
api/notifications_api.php

helpers/mail_helper.php
helpers/master_helper.php

assets/js/video-source.js
assets/vendor/hls.min.js

views/curso_preview.php
views/gerenciar_curso.php
views/member/member_course_view.php
views/produto_config/aba_links.php
```

**Documentação (opcional, só para referência futura)**
```
config/config.example.php
```

Total: **18 arquivos de código** + 1 de documentação.

---

## 4. Arquivos que NÃO entram na VPS (8)

Estes são só para sua máquina. Publicar é desperdício e, no caso do `.ps1` e `.php` de teste, o `.htaccess` bloqueia o acesso — mas o arquivo ficaria exposto em disco.

```
router.local.php          substituto do .htaccess no servidor local
iniciar-local.bat         launcher do Windows
SETUP_LOCAL.md            documentação local
apache-vhost-local.conf   vhost do XAMPP
teste-gumlet.ps1          verificador de link Gumlet
teste-cabecalho.php       teste de headers de e-mail
config/config.local.php   template local
legal/                    páginas privadas e de aviso; a VPS já tem as suas
```

Sobre `legal/`: o `.htaccess` libera `privacidade.html` e `termos.html` de propósito, mas são os **seus** documentos, não os meus. **Confira se a VPS já tem os dela** e, se não tiver, envie as suas manualmente. Não copie as minhas por engano.

---

## 5. Passo obrigatório — ajustar o `config/config.php` da VPS

O `config.php` que está na VPS **não tem** estas três constantes. Sem elas, o novo sistema de e-mail funciona parcialmente, mas o token de desinscrição fica previsível (falha de segurança).

Abra o `config/config.php` **na VPS** e adicione antes do `?>` final:

```php
// === Adicionado na publicação de 2026-XX-XX ===

// E-mail: headers de descadastro e verificação de certificado TLS
if (!defined('MAIL_VERIFICAR_CERTIFICADO')) {
    define('MAIL_VERIFICAR_CERTIFICADO', true);
}

// Gere um segredo novo e exclusivo desta instalação.
// NÃO copie o valor do ambiente local.
if (!defined('MAIL_UNSUBSCRIBE_SECRET')) {
    define('MAIL_UNSUBSCRIBE_SECRET', 'COLE_AQUI_O_SEGREDO_GERADO');
}
```

**Não** adicione `LICENCA_DESATIVADA` aqui. A licença precisa continuar ativa na sua loja.

Para gerar o segredo, rode na VPS:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

### 5.1. Verificação automática

Existe um script que checa tudo de uma vez. Suba ele junto com os outros arquivos e rode **por SSH**, na pasta do app:

```bash
php verificar-config.php
```

Ele verifica extensão do PHP, permissões de pasta, conexão com o banco, se as tabelas existem, as constantes de e-mail, o `mail_helper.php`, o `desinscrever.php` e se o `.htaccess` tem as 6 regras de segurança.

**Ele nunca imprime segredo nenhum** — só diz se cada item está OK. E recusa rodar pelo navegador.

O resultado tem três saídas possíveis:

| Saída | Significado |
|---|---|
| `0 falhas` | Pode publicar |
| `0 falhas` + avisos | Pode publicar, mas leia os avisos |
| `N falhas` | **Não publique.** Corrija os `[FALHA]` |

> Atenção: o script sai com código de erro `1` quando há falhas. Se você automatizar algo depois, use isso.

Depois de rodar, **apague o script da VPS**. Ele não é necessário em produção:

```bash
rm verificar-config.php
```

**Comandos manuais** (caso prefira não subir o script):

```bash
php -l config/config.php
php -r "define('X',1); require 'config/config.php'; echo defined('MAIL_UNSUBSCRIBE_SECRET') ? 'OK: segredo presente' : 'ERRO: falta constante';"
```

---

## 6. Reiniciar o serviço

```bash
docker restart <container-do-app>
```

Se o container é PHP-FPM + Apache, reinicie o container. Se for Apache direto, basta o restart.

**Limpe o cache do PHP** — é o passo que mais gera confusão:

```bash
docker exec <container-do-app> php -r 'opcache_reset();' 2>/dev/null || echo "opcache_reset indisponível, reinicie o container"
```

---

## 7. Checklist de verificação (na ordem)

Faça na URL de teste primeiro, nunca no domínio real antes de passar por aqui.

| # | Teste | Como verificar | Esperado |
|---|---|---|---|
| 1 | Home | abrir `/` | HTTP 200, página normal |
| 2 | Login | `/login` | HTTP 200 |
| 3 | Painel | entrar com `admin@gmail.com` / `admin123` | Abre o painel |
| 4 | **Checkout** | `/checkout?p=31704c39dba8742c8286ec613a1e446a` | **Abre e mostra o preço certo** |
| 5 | **Link limpo** | clicar num link do produto | Abre sem redirect quebrado |
| 6 | **Link .php** | abrir `/checkout.php?p=...` | Redireciona 301 para `/checkout?p=...` |
| 7 | Produto | aba Geral | Preço e capa aparecem |
| 8 | **Preço grátis** | marcar "Produto Grátis" + salvar | Preço vira 0 — comportamento esperado |
| 9 | **Links (aba)** | aba Links do produto | Link começa com `https://`, não `http://` |
| 10 | E-mail | admin → SMTP → enviar teste | E-mail chega |
| 11 | Member | logar como cliente | Área de membros abre |
| 12 | Vídeo | abrir aula com HLS | Player toca, sem erro de CORS |
| 13 | **Tracking** | aba Rastreamento do produto, salvar um GA4 de teste, abrir `/checkout?p=...` | `gtag('config', 'G-...')` e `gtag('event', 'begin_checkout')` no HTML |

### Testes que não podem falhar

Se **4**, **6** ou **9** falhar, **não continue**. Volte ao backup.

- **4 e 6** validam o `.htaccess` — sem ele, todas as URLs limpas quebram
- **9** valida a correção do `https://` que fiz hoje em `aba_links.php`
- **13** valida o GA4/Google Ads que adicionei hoje no `checkout.php` — **limpe o ID de teste depois**

---

## 8. Verificar a proteção (segurança)

Depois de publicar, confirme que o `.htaccess` está ativo:

```bash
# Todos devem retornar 403 Forbidden
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/helpers/mail_helper.php
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/config/config.php
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/gateways/efi.php
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/teste-gumlet.ps1
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/teste-cabecalho.php
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/Banco_de_Dados.sql

# Este deve retornar 200 (o app precisa dele)
curl -s -o /dev/null -w "%{http_code}\n" https://SEU-DOMINIO/uploads/.htaccess
```

Se qualquer um dos bloqueados retornar **200**, o `.htaccess` não está sendo lido — verifique se o Apache tem `AllowOverride All` habilitado.

---

## 9. Rollback

Se algo der errado:

```bash
docker stop <container-do-app>
rm -rf /caminho/do/app
cp -r /backup/app/* /caminho/do/app/
docker start <container-do-app>
```

Tempo de recuperação: **1 minuto**. Banco não é tocado pelas alterações desta publicação — nenhuma migration é necessária.

---

## 10. Divergências conhecidas entre local e VPS

Documentadas para não te surpreenderem:

| Item | Local | VPS | Impacto |
|---|---|---|---|
| Tabela de configurações | `configuracoes` | `configuracoes_sistema` | Ambas existem. Não copie o `config.php` da VPS para cá. |
| Arquitetura do config | `load_settings.php` separado | funções dentro do `config.php` | Manter o padrão da VPS. |
| Abas do produto | as 6 abas presentes | as 6 abas presentes | Sem divergência. |
| Tracking | presente e testado | presente e testado | Paridade. GA4 e Google Ads foram adicionados ao `checkout.php` (antes só existiam na página de obrigado). |

---

## 11. Ainda pendente antes de vender

Esta publicação **não** resolve estes pontos. Eles são bloqueadores de venda:

- [ ] **Domínio de e-mail próprio (SPF/DKIM/DMARC)** — hoje os e-mails saem de `@gmail.com` do seu servidor e caem em spam. Todo e-mail de compra some.
- [ ] **Rotação do token do Mercado Pago** — o token presente no dump exposto precisa ser trocado.
- [ ] **Ambiente de teste separado** — publicar direto no domínio de venda não tem retorno rápido. Subdomínio de teste dá.
- [ ] **Cliente real na Área de Membros** — hoje só existe o infoprodutor de teste.

---

## Resumo em 8 linhas

1. Backup do banco e dos arquivos
2. Copiar os **18 arquivos** da seção 3
3. **Não** copiar `config/config.php`
4. Adicionar as **2 constantes** de e-mail no `config.php` da VPS
5. Rodar `php verificar-config.php` e exigir **0 falhas**
6. Reiniciar container + limpar opcache
7. Rodar o checklist da seção 7 na URL de teste
8. Apagar `verificar-config.php` da VPS
