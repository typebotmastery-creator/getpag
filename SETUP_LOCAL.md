# Como rodar o GatewayPro na sua maquina

**Nao precisa de Docker. Nao precisa reinstalar o XAMPP.**

Voce tem MariaDB 10.4.32 rodando e PHP 8.2.12. Isso e suficiente. O
servidor embutido do PHP substitui o Apache durante o desenvolvimento.

---

## Video HLS (.m3u8) - Gumlet e similares

O sistema originalmente so tocava video do **YouTube**. Agora tambem aceita:

| Link | Como toca |
|---|---|
| `.../main.m3u8` (Gumlet, Mux, Cloudflare) | hls.js - Escolhe a qualidade sozinho, aceita mudar, traz as legendas |
| `.../video.mp4` | `<video>` nativo do navegador |
| Link do YouTube | player YMin de sempre (continua igual) |

**Como usar:** em `Infoprodutor > Produtos > Gerenciar Curso > Editar modulo > Editar aula`, cole
o link em **URL do Video**. O campo aceita `.m3u8` direto.

Para testar com o link do Gumlet, existe um curso de teste pronto:
**http://localhost:8088/curso_preview?produto_id=41**

### Aviso importante: o link do Gumlet expira

O link `video.gumlet.io/.../main.m3u8` e **assinado** e contem um token com
data de validade. O que foi testado aqui expira em **31/10/2026** - depois disso
a aula para de tocar para todo mundo.

Se isso for um problema, use no painel do Gumlet uma URL **sem token** (a
opcao "no token" / "signed = off"), ou gere token com validade longa. Do jeito
que esta, se voce colar links assinados com prazo curto, as aulas vao quebrar
sozinhas.

### Arquivos envolvidos

| Arquivo | O que faz |
|---|---|
| `assets/vendor/hls.min.js` | hls.js 1.5.17 (baixado, nao depende de CDN) |
| `assets/js/video-source.js` | cria o `<video>` e monta a lista de segmentos |
| `views/member/member_course_view.php` | player do aluno |
| `views/curso_preview.php` | player da previa publica |
| `views/gerenciar_curso.php` | rotulo e dica do campo de URL |

O hls.js ficou **local** de proposito: e o coracao do produto e nao pode
depender de CDN externo. O CORS do Gumlet ja esta liberado (`*`), entao
funciona em qualquer dominio.

---

## Rodar (2 passos, ja feito nesta maquina)

**1. O MySQL precisa estar rodando.** Pelo XAMPP, clique em **Start** no MySQL.
Se ja estiver rodando, pule este passo.

**2. De dois cliques no arquivo:**
```
F:\checkout\iniciar-local.bat
```

Depois abra no navegador: **http://localhost:8088**

| Campo | Valor |
|---|---|
| E-mail | `admin@gmail.com` |
| Senha | `admin123` |

Para **parar** o servidor: feche a janela preta do `.bat` (ou `CTRL+C`).

---

## O que ja esta pronto nesta maquina

Foi configurado e testado. Nao precisa refazer:

- Banco `checkout` criado (29 tabelas importadas do `Banco_de_Dados.sql`)
- Usuario `gatewaypro` / `gatewaypro_secret` criado
- `config/config.php` copiado de `config/config.local.php`
- Licenca desligada (constante LICENCA_DESATIVADA, veja abaixo)
- 16 URLs testadas, todas respondendo HTTP 200

---

## Licenca: como foi desligada

O app original e licenciado. O `login.php:69` chama `checkLicenseOnLogin()`
**antes** de deixar voce entrar, e sem licenca valida qualquer login vai para
`/ativacao`.

Foi desligado em **um unico lugar**: a funcao `isMasterPanel()` em
`helpers/master_helper.php`. Todos os pontos de checagem passam por ela:

| Arquivo | Linha | Chamada |
|---|---|---|
| `login.php` | 69 | `checkLicenseOnLogin()` |
| `helpers/license_helper.php` | 8, 154, 184 | `isMasterPanel()` |
| `api/license_api.php` | 29 | `isMasterPanel()` |
| `api/member_api.php` | 240, 357 | `isMasterPanel()` |

O controle esta na constante `LICENCA_DESATIVADA`, dentro do
`config/config.php`:

```php
define('LICENCA_DESATIVADA', true);   // false = volta a exigir licenca
```

**Para reativar a licenca:** troque para `false` e limpe as configuracoes no
banco:
```sql
UPDATE configuracoes_sistema SET valor='' WHERE chave='license_key';
UPDATE configuracoes_sistema SET valor='0'  WHERE chave='is_master_panel';
```

A pagina `/ativacao` continua no sistema (nao foi removida), so deixa de ser
chamada. Se quiser tirar a pagina do menu depois, e so remover o link.

---

## Por que o servidor embutido do PHP e nao o Apache


O XAMPP desta maquina **nao tem o modulo PHP do Apache**:
o arquivo `php8apache2_56.so` nao existe em `C:\xampp\apache\modules`, e o
`httpd.conf` nao tem nenhuma linha `LoadModule php_module`. Com isso o
Apache serve os `.php` como texto puro (voce veria o codigo-fonte).

Alternativas, e o que cada uma custa:

| Opcao | Custo |
|---|---|
| **Servidor embutido do PHP** (o que usei) | Nada. Perde os `.htaccess` (veja abaixo) |
| Reinstalar XAMPP com Apache + PHP | 15 min, funciona tudo igual producao |
| Usar `apache-vhost-local.conf` no Apache | So funciona **depois** de instalar o modulo PHP |

O servidor embutido do PHP e **single-threaded** no Windows (a variavel
`PHP_CLI_SERVER_WORKERS` nao funciona, o PHP responde `forking is not
supported on this platform`). Isso **nao** trava o painel, porque o navegador
so faz a chamada AJAX depois que a pagina inteira ja foi recebida. Se voce
 notar alguma tela travando, me avise.

---

## ATENCAO: o projeto nao tem .htaccess neste download

O pacote que voce baixou **nao vem com nenhum arquivo `.htaccess`**. E o
servidor embutido do PHP ignoraria eles de qualquer forma, entao aqui isso
nao muda nada.

O que isso significa:

- Em producao (VPS com Apache/nginx) as URLs sem extensao (`/login`,
  `/admin`) so funcionam porque existe um `.htaccess` **na VPS** que nao veio
  neste download.
- Como as protecoes de seguranca (403 em `Banco_de_Dados.sql`, `helpers/`,
  `*.log`) tambem vem desse `.htaccess`, elas existem na VPS e nao aqui.

Ou seja: **para deixar igual a producao, copie o `.htaccess` da VPS para
aqui.** Se quiser, eu baixo da VPS e testo as protecoes.

## Comparando com a VPS

Esta copia local foi alterada **apenas** em arquivos que nao mudam o
comportamento do app:

| Arquivo | O que e |
|---|---|
| `iniciar-local.bat` | sobe o servidor local |
| `router.local.php` | faz o PHP servir URLs sem extensao (esta no .gitignore) |
| `config/config.local.php` | template de configuracao, sem uso pelo app |
| `config/config.example.php` | template para outros devs |
| `favicon.ico` | icone do navegador (o download nao tinha) |
| `SETUP_LOCAL.md`, `apache-vhost-local.conf` | documentacao |
| `.gitignore` | impede que senhas sejam enviadas ao GitHub |
| `helpers/master_helper.php` | **licenca desligada** (1 guarda no inicio da funcao) |
| `produto_config.php` | **1 chave `}` que faltava** (erro de sintaxe real) |

O `api.php` e todos os outros arquivos do app estao **identicos ao download
original**.

---

## Rodando com Docker (quando reinstalar o Docker)

Se um dia voce instalar o Docker, o caminho original continua valendo:

```
docker-start.bat
```
Ele copia `config/config.docker.php` por cima do `config/config.php`,
sobe os containers e mostra a URL `http://localhost:8082`.
A senha do banco no Docker e `gatewaypro_secret`, e o host e `db`
(nome do servico), nao `localhost`.

Nao rode `docker-start.bat` enquanto estiver desenvolvendo no
servidor embutido: ele sobrescreve o seu `config/config.php` local.

---

## Problemas comuns

| Sintoma | Causa | Solucao |
|---|---|---|
| "Nao foi possivel conectar ao banco" | MySQL parado | XAMPP > MySQL > Start |
| Login cai sempre em `/ativacao` | Modo master desligado | Confira as 3 condicoes da secao "modo master" |
| Porta 8088 ocupada | Outro programa usando | Troque a porta no `iniciar-local.bat` e na URL |
| 404 em `/login` | Servidor iniciado de outra pasta | Rode o `.bat` (ele faz `cd` sozinho) |
| Mudou a senha e nao entra | Rate limit (5 tentativas) | Espere alguns minutos |

---

## O que NAO esta versionado (de proposito)

Estes arquivos existem na sua maquina mas **nao vao para o GitHub**:

| Arquivo | Motivo |
|---|---|
| `config/config.php` | contem a senha do banco |
| `router.local.php` | so funciona na sua maquina |
| `Banco_de_Dados.sql` | contem schema + hashes de senha + token MP |
| `*.log`, `*.txt` | logs com dados de clientes |
| `uploads/` (conteudo) | arquivos enviados pelos clientes |

Se outro devs precisarem do projeto, mande o `config/config.example.php`
(ja existe, sem senha). Eles copiam para `config/config.php` e ajustam
as 4 constantes.

