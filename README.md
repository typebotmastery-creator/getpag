# GatewayPro

Sistema de checkout e area de membros, em PHP + MySQL, empacotado para rodar
com Docker Swarm / Portainer.

---

## O que voce precisa

- Uma VPS com **Docker Swarm** ligado
- **Portainer** instalado e rodando
- Um **dominio** apontando para o IP da VPS (registro A)
- Porte minimo: **2 vCPU e 2 GB de RAM** (4 GB recomendado)

Instalacao media: **10 a 15 minutos**.

---

## Instalacao

### 1. Ligue o Swarm

No SSH da VPS:

```bash
docker swarm init
docker network create --driver overlay traefik_proxy
```

> Se a VPS ja usa Portainer, o Swarm e a rede `traefik_proxy` provavelmente
> ja existem. Confira com `docker network ls` e pule este passo nesse caso.
> O nome da rede precisa bater com `TROCAR_NOME_DA_REDE_EXTERNA` no
> `docker-compose.yml`.

### 2. Suba o Portainer

```bash
docker volume create portainer_data
docker run -d -p 8000:8000 -p 9443:9443 \
  --name portainer --restart=always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  docker.io/portainer/portainer-ce:latest
```

Acesse `https://SEU_IP:9443` e crie o usuario.

### 3. Crie o banco de dados

No MySQL da VPS, crie o banco que a stack vai usar:

```sql
CREATE DATABASE nome_do_banco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Edite o `docker-compose.yml`

Substitua cada `TROCAR_...` no arquivo:

| O que trocar | Onde |
|---|---|
| `TROCAR_SENHA_ROOT_FORTE` | senha do root do banco |
| `TROCAR_NOME_DO_BANCO` | nome do banco (use o mesmo do passo 3) |
| `TROCAR_SENHA_DO_APP` | senha do app (**precisa ser igual nos dois lugares**) |
| `TROCAR_SEU_DOMINIO` | seu dominio, sem `https://` |
| `TROCAR_NOME_DA_REDE_EXTERNA` | rede do Traefik (padrao: `traefik_proxy`) |
| `TROCAR_NOME_DO_CERTIFICADOR` | nome do certresolver (veja abaixo) |
| `TROCAR_SEGREDO_DA_API` | string aleatoria para a API interna |
| `TROCAR_SEGREDO_LONGO_ALEATORIO` | string aleatoria para e-mail |
| `PORTAINER_USUARIO` / `PORTAINER_SENHA_BCRYPT` | acesso ao `/manage` |

### Descubra o nome do seu certresolver

O nome **nao** pode ser inventado. Confira o que o seu Traefik usa:

```bash
docker inspect traefik --format '{{range .Config.Cmd}}{{println .}}{{end}}' \
  | grep certificatesresolvers
```

Vai sair algo como `certificatesresolvers.letsencryptresolver.acme.httpchallenge=true`.
Nesse caso, `TROCAR_NOME_DO_CERTIFICADOR` = `letsencryptresolver` — **sem o
prefixo `certificatesresolvers.` e sem o sufixo `.acme`**.

Se nao aparecer nenhum, seu Traefik nao emite certificado. Aponte o
`certresolver` para o de outra instalacao, ou faca o TLS no seu DNS.

> As linhas do Portainer em `/manage` sao opcionais. Se nao quiser o
> Portainer no mesmo dominio, apague as 7 linhas de `gatewaypro-manage`.

### 5. Publique

No Portainer: **Stacks > Add stack > Repository**, cole a URL do repositorio
e o branch `main`, faca upload do `docker-compose.yml` e clique em **Deploy**.

Se preferir via SSH:

```bash
git clone https://github.com/typebotmastery-creator/getpag.git
cd getpag && docker stack deploy -c docker-compose.yml gatewaypro
```

### 6. Acesse

`https://SEU_DOMINIO` — as 30 tabelas sao criadas no primeiro start.

---

## Por que a rede do banco e separada

O app entra em duas redes: a rede do Traefik (para o Traefik rotear o
dominio) e uma rede interna (para falar com o banco). O banco entra
**so na interna**.

Se o banco estivesse na rede do Traefik, qualquer outro servico naquela
rede — e o Traefik agenda servicos nessa rede — conseguiria abrir a porta
3306 do banco. Como o root do MySQL tem a mesma senha que o app, isso e
uma porta aberta.

Alem disso a rede do Traefik nao e problema se voce mover o banco: ele
continua resolvendo `db` pelo nome, porque as duas redes estao ligadas.

---

## Sobre o token da API

`TOKEN_AUTH_SECRET` e o que a API interna (`/api.php`) usa para
autenticar quem chama. Sem ele, o app cai no banco, nao acha a chave e
passa a usar um valor padrao que todo mundo conhece.

Gere um valor diferente para cada cliente:

```bash
openssl rand -hex 24
```

---

## Backup

Dois volumes guardam tudo que importa:

- `gatewaypro_db_data` — o banco
- `gatewaypro_app_uploads` — arquivos enviados pelos usuarios

```bash
docker run --rm -v gatewaypro_db_data:/d -v $PWD:/b alpine \
  tar czf /b/backup-db-$(date +%F).sql.gz -C /d .
```

Guarde o `Banco_de_Dados.sql` deste repositorio: e o schema. So o volume tem
os **dados**.

---

## Problemas comuns

**Container do app reiniciando em laco**
Quase sempre e senha do banco. `DB_PASSWORD` no `app` precisa ser identico ao
`MARIADB_PASSWORD` do `db`. Veja o erro com `docker service logs gatewaypro_app`.

**Site abre sem estilo nenhum**
O Apache precisa de `AllowOverride All` para os URLs limpos. Ja esta no
`Dockerfile`; se voce montou o codigo por fora, confira.

**Erro de certificado no e-mail**
Ajuste `MAIL_VERIFICAR_CERTIFICADO` para `false` em `docker/entrypoint.sh` e
rebuild. Prefira instalar o certificado CA no container.

**Caixa baixa / acentos quebrados**
O banco precisa ser `utf8mb4`. Veja o passo 3.

---

## Licenca

O sistema **nao pede chave de ativacao**. Quem compra o codigo roda a propria
instancia e nao depende de servidor de terceiro.

Para voltar a exigir chave (uso interno, com seu proprio servidor):

```yaml
LICENCA_DESATIVADA: "false"
```

E aponte o servidor no stack:

```yaml
LICENSE_WEBHOOK_URL: "https://seu-servidor/webhook"
LICENSE_WEBHOOK_USER: "usuario"
LICENSE_WEBHOOK_PASS: "senha"
```

Sem essas tres variaveis, a validacao de chave e apenas local.
