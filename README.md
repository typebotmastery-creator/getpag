# GatewayPro — Guia de Instalação Completo

Bem-vindo. Este guia leva você de **uma VPS recém-comprada, ainda vazia**
até o GatewayPro **funcionando em um domínio com HTTPS**, passo a passo.

Ele foi escrito para quem não é especialista em servidores. Cada termo
técnico é explicado na hora em que aparece. Não pule nenhum passo e não
invento valores: **copie e cole os comandos**, trocando apenas o que o
texto mandar trocar.

---

## 1. O que é o GatewayPro, em uma frase

É um sistema que faz duas coisas: um **checkout** (a página onde o cliente
fecha a compra e paga) e uma **área de membros** (a página onde ele acessa
o que comprou). O pagamento pode ser por PIX, cartão e outros meios, e o
sistema também envia e-mails e mensagens de WhatsApp.

## 2. Como o sistema funciona por baixo

O GatewayPro é feito de **duas partes** que trabalham juntas:

| Parte | O que é | Para que serve |
|---|---|---|
| **app** | Seu código principal (PHP) | A parte visível: site, checkout, login |
| **db** | Um banco de dados (MariaDB) | Onde ficam guardados: usuários, vendas, cursos, produtos |

Essas duas partes são instaladas dentro de **caixas isoladas chamadas
containers**. Cada container carrega a sua parte e as dependências dela.
Por isso você **não instala PHP nem MySQL na mão** — o container já vem
com tudo pronto.

## 3. O que você precisa comprar/tener antes de começar

### 3.1 Uma VPS
**O que é:** um computador que não fica na sua casa, fica em um datacenter
ligado na internet 24 horas por dia, que você aluga por mês.

- **Porte mínimo:** 2 núcleos (vCPU) e 2 GB de memória (RAM). Recomendado: 4 GB.
- **Sistema operacional:** escolha **Ubuntu 22.04** ou **Debian 12**.
- **Acesso:** faça a compra, a empresa vai te dar um **IP** (ex.: `142.250.190.46`)
  e uma **senha de root** (a senha do "administrador geral" da máquina).
- **Firewall:** se a VPS tiver um "security group" ou firewall para
  configurar, libere as portas **22 (SSH)**, **80 (HTTP)** e **443 (HTTPS)**
  e **9443 (Portainer)**. Muitas VPS já vêm liberando tudo. Nas seções de
  instalação o comando `ufw allow` do passo 2 cuida disso se você usar Ubuntu.

Guardar as duas coisas: o **IP** e a **senha de root**. Você vai usar
durante todo o guia.

### 3.2 Um domínio
**O que é:** um endereço como `meusite.com.br` que as pessoas digitam.

- Você compra um domínio em registradoras como Registro.br, Hostinger, GoDaddy.
- Precisa conseguir **alterar o DNS** (a "placa de endereço" da internet)
  para adicionar um registro chamado **A** apontando para o IP da sua VPS.
- A instalação **funciona sem domínio**, mas **sem domínio não há HTTPS**,
  e o checkout com Pix/cartão exige HTTPS. Use um domínio.

### 3.3 Um computador para acessar a VPS
Qualquer computador (Windows, Mac ou Linux). Você só vai usar o **terminal**
(Prompt de Comando / PowerShell no Windows) para mandar comandos para a VPS.

## 4. Micro-dicionário (o que cada palavra significa)

Leia rápido. No meio do guia, se travar, volte aqui.

| Termo | Explicação simples |
|---|---|
| **VPS** | Um computador alugado, ligado na internet. |
| **SSH** | Um jeito seguro de mandar comandos do seu computador para a VPS. |
| **Root** | O usuário "dono" da VPS. Tem todos os poderes. |
| **Docker** | Programa que roda as "caixas" (containers) na VPS. |
| **Container** | Uma caixa que roda uma parte do sistema com tudo que ela precisa. |
| **Imagem** | A "receita" de um container. O Docker lê a receita e monta a caixa. |
| **Swarm** | Modo do Docker que gerencia várias caixas juntas (aqui, 2 containers). |
| **Stack** | O arquivo que descreve as caixas (neste projeto: `docker-compose.yml`). |
| **Portainer** | Um painel com botões/interface para ver e gerenciar as caixas, sem digitar comando. |
| **Traefik** | Um "porteiro" inteligente: recebe quem chega pelo domínio e leva para a caixa certa. Também cuida do certificado HTTPS. |
| **Volume** | Uma gaveta de armazenamento que sobrevive ao container. |
| **Rede overlay** | Uma rede virtual que conecta as caixas entre si. |
| **HTTPS** | A versão segura do site (cadeado na barra). Sem ele, pagamento funciona mal. |
| **Certificado SSL/TLS** | O "cadeado" do HTTPS, emitido de graça pelo Let's Encrypt via Traefik. |
| **Registro A (DNS)** | Liga o domínio ao IP da VPS. |

## 5. Vista geral: como tudo fica no final

```
         Internet
             |
    (portas 80 e 443)
             |
        [ Traefik ]  ------------------ Portainer (painel, porta 9443)
        /          |
   domínio       domínio
  com o app   (o Traefik roteia o domínio para o app)
             |
        [  app  ]
             |
   (rede interna, a "caixa" do banco não fala com a internet)
             |
        [  db   ]
```

A **sequência** de instalação deste guia é:

1. Acessar a VPS (SSH)
2. Atualizar o sistema
3. Instalar o Docker
4. Ativar o Swarm
5. Instalar o Portainer (painel)
6. Instalar o Traefik (porteiro + HTTPS)
7. Apontar o domínio para a VPS (DNS)
8. Baixar o sistema na VPS
9. Editar o arquivo de configuração
10. Subir o sistema
11. Entrar no sistema pela primeira vez

---

## Passo 1 — Acessar a VPS (SSH)

Você vai abrir uma janela de comandos **no SEU computador**. É essa janela
que vai conversar com a VPS.

**No Windows:**

1. Abra o menu Iniciar, digite `PowerShell` e abra.
2. Digite o comando abaixo, trocando `SEU_IP` pelo IP que você recebeu:

```powershell
ssh root@SEU_IP
```

**No Mac ou Linux:** abra o Terminal e rode o mesmo comando acima.

- Na primeira vez ele vai perguntar (em inglês) se você confia neste
  computador: `Are you sure you want to continue connecting (yes/no)?`
  Responda `yes` e aperte Enter.
- Vai pedir a senha: digite a **senha de root** que você recebeu.
  (Ao digitar senha, nada aparece na tela — nem bolinhas. É normal. Digite
  e aperte Enter.)

**Como saber que deu certo:** o cursor muda e passa a mostrar algo como:

```
root@seu-servidor:~#
```

Esse é o **prompt** da VPS. Todo comando daqui pra frente é para ser rodado
**neste prompt**, ou seja, já dentro da VPS.

## Passo 2 — Atualizar a VPS

A VPS vem com uma lista de programas desatualizada. Atualize tudo antes.

**Se a VPS é Ubuntu** (copie e cole):

```bash
apt update && apt upgrade -y && apt install -y curl git nano ufw
```

**Se a VPS é Debian**, use:

```bash
apt update && apt upgrade -y && apt install -y curl git nano ufw
```

> `apt` é o instalador de programas do sistema. `update` baixa a lista nova,
> `upgrade` instala as atualizações, e `install` adiciona ferramentas que você
> vai usar: `curl` (baixar arquivos), `git` (baixar o código), `nano` (editor),
> `ufw` (firewall do Ubuntu).

**Na VPS Ubuntu**, confira o firewall (o Debian pode não usar `ufw`):

```bash
ufw allow 22/tcp && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 9443/tcp
ufw enable
```

> `ufw` é o firewall: só deixamos entrar o SSH (22), o site (80 e 443) e o
> painel (9443). Responda `y` se ele perguntar `Proceed with operation?`.

## Passo 3 — Instalar o Docker

**O que é o Docker:** o programa que cria e administra as caixas
(containers) onde o GatewayPro roda.

Rode o instalador oficial (um único comando):

```bash
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
```

Isso instala o Docker e liga ele para iniciar sozinho. Confira que está tudo
funcionando:

```bash
docker --version
docker run hello-world
```
- `docker --version` deve mostrar um número (ex.: `Docker version 24.0.9`).
- `docker run hello-world` baixa uma caixa de teste, roda e mostra uma
  mensagem em inglês. É a prova de que o Docker funciona.

## Passo 4 — Ativar o Swarm

**O que é o Swarm:** o modo do Docker que administra caixas em grupo.
Como você vai usar 2 caixas (app e banco) que precisam se falar, o Swarm
fica com essa responsabilidade.

```bash
docker swarm init
```

**Como saber que deu certo:**

```bash
docker node ls
```

Deve aparecer uma linha com `Leader` e `Ready`:

```
ID                            HOSTNAME    STATUS    AVAILABILITY    MANAGER STATUS
xxxxx...                      vps-1       Ready     Active          Leader
```

## Passo 5 — Instalar o Portainer (o painel)

**O que é o Portainer:** um painel **com botões e tela** para você ver e
gerenciar as caixas sem decorar comandos. Útil principalmente para (a)
ver **logs** (as mensagens de erro do sistema) com cliques e (b) rodar
backups no futuro.

Crie a gaveta de armazenamento do Portainer e rode o painel:

```bash
docker volume create portainer_data
docker run -d -p 8000:8000 -p 9443:9443 \
  --name portainer --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  docker.io/portainer/portainer-ce:latest
```

**O que cada parte faz:**

- `-d` → roda em segundo plano
- `-p 8000:8000` e `-p 9443:9443` → abre as portas do painel
- `--restart=always` → se a VPS reiniciar, o painel volta sozinho
- `-v /var/run/docker.sock` → dá ao painel acesso ao Docker
- `-v portainer_data:/data` → guarda os dados do painel na gaveta

**Como saber que deu certo:** abra o navegador do SEU computador e entre em:

```
https://SEU_IP:9443
```

(ex.: `https://142.250.190.46:9443`)

O navegador vai avisar sobre "conexão não segura" — é esperado, o painel
ainda não tem certificado. Clique em "Avançado" e "Continuar".

Na primeira visita ele pede para **criar o usuário administrador**:
escolha um nome de usuário e uma senha forte, e confirme. Depois clique em
**"Get Started"** e no menu da esquerda em **"Local"** / **"Connect"**.

**Tem que fazer isso agora** — os passos a seguir não precisam do painel,
mas você vai voltar nele no Passo 10.

## Passo 6 — Instalar o Traefik (o porteiro + HTTPS)

**O que é o Traefik:** o programa que olha para a porta 80/443, vê quem
chegou (o seu domínio) e leva até a caixa certa. Ele também **emite e
renova o certificado HTTPS de graça**.

### 6.1 Criar a rede do Traefik

```bash
docker network create --driver overlay --attachable traefik_proxy
```

> Você acabou de criar uma rede virtual. O Traefik e o GatewayPro vão
> "entrar" nela para conversar. Anote o nome `traefik_proxy` — ele vai ser
> usado no `docker-compose.yml` como `TROCAR_NOME_DA_REDE_EXTERNA`.

Confira que ela existiu:

```bash
docker network ls
```

Deve aparecer uma rede com o nome `traefik_proxy`.

### 6.2 Criar o arquivo de configuração do Traefik

Crie a pasta e o arquivo:

```bash
mkdir -p /opt/traefik/letsencrypt
```

Agora crie o arquivo de configuração. **Copie e cole o bloco inteiro** em um
único comando:

```bash
cat > /opt/traefik/traefik.yml <<'EOF'
entryPoints:
  web:
    address: ":80"
  websecure:
    address: ":443"

providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    swarmMode: true
    exposedByDefault: false
    network: traefik_proxy

certificatesResolvers:
  letsencryptresolver:
    acme:
      email: SEU_EMAIL_AQUI
      storage: /letsencrypt/acme.json
      httpChallenge:
        entryPoint: web
EOF
```

**Troque** `SEU_EMAIL_AQUI` pelo seu e-mail (é onde o Let's Encrypt avisa
sobre renovação de certificado). Para isso:

```bash
nano /opt/traefik/traefik.yml
```

Dentro do `nano` (editor): use as setinhas para ir até a palavra
`SEU_EMAIL_AQUI`, apague e digite seu e-mail. Depois **Ctrl+O** (salvar),
Enter, **Ctrl+X** (sair).

> **O que esse arquivo faz:**
> - Escuta nas portas 80 (web) e 443 (websecure).
> - `swarmMode: true` → trabalha em conjunto com o Swarm, enxergando as caixas.
> - `exposedByDefault: false` → o Traefik só atende caixas que pedirem
>   atenção (falando explicito no arquivo de stack).
> - `letsencryptresolver` → o nome PLANTÃO do seu "emissor de certificado".
>   **Esse nome aparece no compose como `TROCAR_NOME_DO_CERTIFICADOR`.**
>   Se você copiar fielmente, o valor é `letsencryptresolver`.

### 6.3 Rodar o Traefik

```bash
docker run -d --name traefik --restart=always \
  -p 80:80 -p 443:443 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /opt/traefik/traefik.yml:/etc/traefik/traefik.yml:ro \
  -v /opt/traefik/letsencrypt:/letsencrypt \
  --network traefik_proxy \
  traefik:v3
```

**O que cada parte faz:**

- `-p 80:80 -p 443:443` → o Traefik assume as portas do site
- `-v /var/run/docker.sock` → ele enxerga as caixas para saber para onde rotear
- `-v .../traefik.yml` → usa o arquivo que você editou
- `-v .../letsencrypt` → gaveta do certificado (sobrevive a reinicios)
- `--network traefik_proxy` → entra na rede criada no passo 6.1

Confira:

```bash
docker ps
```

Deve aparecer um container chamado `traefik` com status `Up`.

> **Se der erro de porta:** "bind: address already in use" significa que algo
> já está usando a porta 80 ou 443. Confira com `docker ps` se existe outro
> container ocupando, ou rode `ss -tlnp | grep -E ':80|:443'`.

## Passo 7 — Apontar o domínio para a VPS (DNS)

**O que é o DNS:** a "agenda de endereços" da internet. É o DNS que diz
"quem digita `meusite.com.br` vai parar no IP `142.250.190.46`".

1. Entre no site onde você comprou o domínio.
2. Procure por **DNS**, **"Zona DNS"** ou **"Gerenciar DNS"**.
3. Adicione um registro do tipo **A**:
   - **Nome / Host:** `@` (algumas usam o seu domínio inteiro)
   - **Tipo:** `A`
   - **Valor / Aponta para:** o IP da sua VPS
4. Salve.

A propagação (todo mundo enxergar) leva de **5 minutos a 1 hora**.

**Como saber se já está funcionando** (rode na VPS):

```bash
ping -c 1 seu-dominio.com.br
```

Se a resposta mostrar o **seu IP da VPS**, está apontado.

> Se quiser um subdomínio (ex.: `app.meusite.com.br`), use o mesmo tipo de
> registro A apontando para o IP, com **Nome** = `app`.

## Passo 8 — Baixar o sistema na VPS

Copie a pasta do projeto para `/opt` e entre nela:

```bash
cd /opt
git clone https://github.com/typebotmastery-creator/getpag.git
cd getpag
```

Confira que baixou:

```bash
ls
```

Você deve ver ao menos estes arquivos: `Dockerfile`, `docker-compose.yml`,
`README.md`, `Banco_de_Dados.sql`.

## Passo 9 — Editar o `docker-compose.yml`

**O que é o compose:** o arquivo `docker-compose.yml` é a "receita" das
caixas. Nele estão os `TROCAR_...` — os únicos valores que você precisa
definir. O resto já funciona.

**Abra o arquivo:**

```bash
cd /opt/getpag
nano docker-compose.yml
```

**Lista do que trocar** (o arquivo está comentado, procure pela palavra em
maiúsculas):

| Palavra a trocar | Coloque aqui | Exemplo |
|---|---|---|
| `TROCAR_SENHA_ROOT_FORTE` | Uma senha forte do "super usuário" do banco. Use o gerador abaixo. | `x9kQ!4m2Z7rT` |
| `TROCAR_NOME_DO_BANCO` | O nome do banco. Use **uma palavra sem espaço**. | `gatewaypro` |
| `TROCAR_SENHA_DO_APP` | Senha do usuário `gatewaypro` do banco. **OBRIGATÓRIO: igual nos DOIS lugares** (`MARIADB_PASSWORD` e `DB_PASSWORD`). | `aA!5eRt2zQp7` |
| `TROCAR_SEGREDO_DA_API` | Segredo da API interna. Gere com o comando abaixo. | `c46f1a2e9b0d...` |
| `TROCAR_SEGREDO_LONGO_ALEATORIO` | Segredo para os links de descadastro de e-mail. Gere também. | (idem) |
| `TROCAR_SEU_DOMINIO` | Seu domínio, **sem `https://` e sem `/`** | `meusite.com.br` |
| `TROCAR_NOME_DO_CERTIFICADOR` | O nome que você configurou no Passo 6: `letsencryptresolver` | `letsencryptresolver` |
| `TROCAR_NOME_DA_REDE_EXTERNA` | A rede do Passo 6.1: `traefik_proxy` | `traefik_proxy` |

**Gere os segredos** (rode na VPS; são valores aleatórios longos):

```bash
openssl rand -hex 24
openssl rand -hex 24
```

O primeiro é para `TROCAR_SEGREDO_DA_API`, o segundo para
`TROCAR_SEGREDO_LONGO_ALEATORIO`. Copie cada saída e cole no arquivo.
**Importante:** esses valores são secretos — só você e o app devem conhecer.

**Salve:** `Ctrl+O`, Enter, `Ctrl+X`.

**Não pode sobrar nenhum `TROCAR_`.** Confira:

```bash
grep -n TROCAR_ docker-compose.yml
```

Se o comando não mostrar **nada**, você trocou tudo. Se mostrar linhas,
volte ao `nano` e troque o que falta.

> **Entenda o que você acabou de configurar:**
> - **db** (a caixa do banco): recebe a senha do root, a do usuário do app e
>   o token da API.
> - **app** (a caixa do site): recebe as mesmas senhas/banco + o segredo de
>   e-mail + `LICENCA_DESATIVADA: "true"` (o sistema funciona sem chave).
> - Os `deploy.limits.memory` são os limites de memória de cada caixa —
>   **não troque**.
> - O arquivo `Banco_de_Dados.sql` no `volumes` do db é o que cria as 30
>   tabelas na primeira subida. **Não apague essa linha.**

## Passo 10 — Subir o sistema

**Primeiro, monte a caixa do app.** Este é um passo que muita gente pula e
por isso trava: o Swarm não monta essa caixa sozinho, você precisa pedir
para ele fazer isso UMA vez:

```bash
cd /opt/getpag
docker build -t gatewaypro-app:latest .
```

> **O que está acontecendo:** o `Dockerfile` é a receita da caixa do app.
> Este comando lê a receita e cria a imagem `gatewaypro-app:latest`.
> Demora de 1 a 5 minutos na primeira vez (ele baixa o PHP e instala as
> extensões). Não se assuste com mensagens de `Downloading`/`Building`.

**Depois, suba a stack em si:**

```bash
docker stack deploy -c docker-compose.yml gatewaypro
```

> `stack deploy` é o comando do Swarm que lê a receita e sobe as duas
> caixas (app e db). A primeira vez que o banco sobe, ele roda o
> `Banco_de_Dados.sql` e cria as 30 tabelas automaticamente.

**Confira:**

```bash
docker stack services gatewaypro
```

Espere os serviços aparecerem. Depois acompanhe até ficarem prontos:

```bash
docker service ps gatewaypro_app
docker service ps gatewaypro_db
```

Você quer ver `Running` em vez de `Preparing`/`Starting`. Na primeira subida
o banco pode levar **de 1 a 2 minutos**. Rode o comando abaixo de novo até
ver `Running`:

```bash
docker service ps gatewaypro_db
```

**Se aparecer algo diferente de `Running`,** leia a coluna `ERROR` — os
motivos mais comuns estão no Passo 12.

> **Alternativa pelo painel (Portainer):**
> Basta ter feito o Passo 5 e você pode subir a stack clicando:
> Portainer → **Stacks** → **Add stack** → **Repository** → cole a URL
> `https://github.com/typebotmastery-creator/getpag.git`, branch `main`,
> e em **Build method** marque **Build** (para o Dockerfile ser usado).
> Nomeie como `gatewaypro` e clique em **Deploy the stack**.
> As TROCAR_ continuam valendo: você edita o arquivo dentro do Portainer
> antes de publicar. **Se você usou o painel, pule o `docker build` acima** —
> o Portainer faz essa parte.

## Passo 11 — Testar e entrar no sistema

**No navegador do SEU computador, entre em:**

```
https://seu-dominio.com.br
```

**O que você espera ver:**

1. O navegador **sem** aviso de "conexão não segura" (o cadeado). Isso
   significa que o certificado HTTPS foi emitido e o Traefik está roteando.
2. O sistema abre — tela de login/checkout do GatewayPro.

**Se aparecer "Site não seguro" / "HANDSHAKE_FAILURE":** dê alguns minutos
ainda (de 1 a 5) — o Traefik pode estar terminando de emitir o certificado.
Recarregue. Se persistir, veja Passo 12.

**Login pela primeira vez:** o primeiro acesso costuma ser o usuário
administrador criado pela tela de cadastro do próprio sistema. Siga o que o
app mostrar na tela.

## Passo 12 — Problemas comuns (e como resolver)

### O site não abre / "connection refused" / "bad gateway"
Na VPS, veja o estado da stack:

```bash
docker service ps gatewaypro_app
docker service logs gatewaypro_app --tail 50
```

O `docker service logs` mostra as mensagens do app. O erro típico de início:

- **"ERRO: Não foi possível conectar ao banco de dados"** →
  senha/usuário/nome do banco divergem entre os serviços `db` e `app`.
  Edite de novo o compose (Passo 9) garantindo que `TROCAR_SENHA_DO_APP`
  e `TROCAR_NOME_DO_BANCO` são iguais nos dois lugares, e suba de novo:
  `docker stack deploy -c docker-compose.yml gatewaypro`.

### O app reinicia em loop
Mesmo motivo acima, ou o banco ainda está iniciando. Veja:
```bash
docker service ps gatewaypro_db
```
Primero espere `Running` no banco. Como o app espera o banco sozinho, ele
só roda de verdade depois.

### "502 / Bad Gateway" do Traefik
O Traefik está no ar mas não encontrou a caixa do app. Confira que:
1. O app está `Running` (`docker service ps gatewaypro_app`).
2. O nome da rede bate: `TROCAR_NOME_DA_REDE_EXTERNA = traefik_proxy`
   (Passo 9).
3. A rede existe: `docker network ls`.
4. O label `traefik.http.services.gatewaypro.loadbalancer.server.port=80`
   está presente (quando edita o compose, não apague essa linha).

### "Site não seguro" eterno
O Traefik não conseguiu emitir o certificado. Causas mais comuns:
1. O DNS ainda não apontou (Passo 7).
2. As portas 80/443 bloqueadas no firewall da VPS (Passo 2) ou na nuvem.
3. O `TROCAR_NOME_DO_CERTIFICADOR` não é o nome real do seu Traefik.
   Confira em `/opt/traefik/traefik.yml` (o valor é `letsencryptresolver`
   se você seguiu o Passo 6.2).

### Ver os logs do sistema pelo painel (Portainer)
Navegue: Portainer → **Stacks** → selecione a stack → aba **v** dos
serviços → **Logs**. Útil para enviar aos suporte com a mensagem exata.

---

## Anatomia do arquivo `docker-compose.yml`

Para quem quer entender o arquivo (não é obrigatório, mas explica o que
você editou):

```yaml
services:
  db:            # caixa 1: o banco de dados
    image: mariadb:10.11
    environment: # senhas e nome do banco (as TROCAR_ do passo 9)
    volumes:
      - db_data:/var/lib/mysql              # gaveta dos dados do banco
      - ./Banco_de_Dados.sql:/docker-entrypoint-initdb.d/01-init.sql:ro  # cria as tabelas na 1a subida
  app:           # caixa 2: o site
    build: .                                 # monta esta caixa a partir do Dockerfile
    environment: # DOMINIO é controlado pelo Traefik, não aqui
    ports: (nenhuma)   # o Traefik que roteia o dominio para esta caixa
```

- **Por que o `app` não declara `ports`?** Porque ele não fica exposto na
  internet; ele fica dentro da rede interna e só o Traefik fala com ele.
- **Por que o banco não tem rótulos do Traefik?** Porque ninguém na
  internet deve acessar o banco. Ele só conversa com o `app`.

## Backup (faça regularmente)

O que importa de verdade vive em **dois volumes** (gavetas):
`db_data` (o banco) e `app_uploads` (os arquivos enviados).

Confira os nomes exatos das gavetas na sua VPS:

```bash
docker volume ls | grep gatewaypro
```

Os nomes normalmente são `gatewaypro_db_data` e `gatewaypro_app_uploads`
(o Swarm coloca o nome da stack na frente).

**Backup do banco** (cria um arquivo com a data de hoje na pasta atual):

```bash
cd /opt/backups 2>/dev/null || mkdir -p /opt/backups && cd /opt/backups
docker exec $(docker ps -q -f name=gatewaypro_db) sh -c \
  'exec mysqldump --single-transaction -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' \
  > backup-$(date +%F-%Hh%M).sql
```

> Nada para substituir aqui: o comando usa as variáveis de ambiente que o
> próprio container do banco guarda (`MARIADB_ROOT_PASSWORD` e
> `MARIADB_DATABASE`), então ele já sabe qual senha e qual banco exportar.

**Backup dos uploads** (os arquivos enviados):

```bash
cd /opt/backups
docker run --rm -v gatewaypro_app_uploads:/d -v $PWD:/b alpine \
  tar czf /b/uploads-$(date +%F-%Hh%M).tar.gz -C /d .
```

**Restaurar o banco** (baixar um backup de volta):

```bash
docker exec -i $(docker ps -q -f name=gatewaypro_db) sh -c \
  'exec mysql -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < SEU_BACKUP.sql
```

Guarde esses `.sql` e `.tar.gz` fora da VPS (um drive, um pendrive). Se a
VPS morrer, os dados não morrem junto.

## Atualizar o sistema (quando receber uma versão nova)

```bash
cd /opt/getpag
git pull
docker build -t gatewaypro-app:latest .
docker stack deploy -c docker-compose.yml gatewaypro
```

**O que isso faz:** baixa a versão nova (git pull), remonta a caixa do app
(build) e reaplica a stack. Seus dados ficam intactos — eles estão nas
gavetas, não na caixa.

> O `git pull` só funciona se você não editou nada além do
> `docker-compose.yml` (que é o caso). Se você editou outros arquivos e o
> `git pull` falhar, ele vai falar em inglês — só não se desespere, mande a
> mensagem para quem te vendeu.

## O que NÃO fazer

- **Não** edite arquivos dentro do container (`docker exec ...`) para
  "corrigir" alguma coisa. Qualquer alteração some no próximo deploy.
- **Não** apague os volumes `db_data` e `app_uploads` ou você perde os dados.
- **Não** exponha o painel Portainer na internet sem senha forte. Ele já
  está na porta `9443` (com senha do usuário criado no Passo 5). Se a sua
  VPS não precisa disso, use-o localmente e não mude o firewall para
  liberar 9443 para todos.
- **Não** rode o app em HTTP sem domínio quando for vender: checkout e
  pagamento exigem HTTPS.

## Sobre a licença

O GatewayPro **não pede chave de ativação**: quem comprou o código roda a
sua própria instância, sem depender de servidor de terceiro. O arquivo
`docker-compose.yml` já traz `LICENCA_DESATIVADA: "true"` — não troque esse
valor, a menos que você tenha um servidor de licenças próprio e queira
voltar a exigir chave.

---

## Checklist final

Rode tudo (da pasta `/opt/getpag`):

```bash
docker service ls                       # 1. as 2 caixas aparecem?
docker service ps gatewaypro_db         # 2. db teve Running sem erro?
docker service ps gatewaypro_app        # 3. app teve Running sem erro?
docker ps | grep -c traefik             # 4. traefik rodando (mostra 1)?
curl -I https://seu-dominio.com.br      # 5. responde HTTP/2 200?
```

Se os cinco pontos responderem certo (1, 2 e 3 mostram `Running`, o 4
mostra `1`, e o 5 mostra `200`), sua instalação está concluída.

Precisa de suporte? Identifique qual dos cinco pontos falhou e guarde a
saída do `docker service logs`: `docker service logs gatewaypro_app --tail 100`.