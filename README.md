# Aluga Crajubar — API

API Laravel 12 (PHP 8.3) do aplicativo Flutter **Aluga Crajubar**, um app de aluguel por temporada em Crato, Juazeiro do Norte e Barbalha. Usa PostgreSQL com replicação **master/slave**, **Traefik** como proxy reverso e balanceador de carga e tokens Bearer do Laravel Sanctum.

Projeto integrador de DevOps — UniFAP.

---

## Arquitetura

```
App Flutter ──► :80 Traefik ──┬──► api-1 ─┬──► master (escrita) ──WAL──► slave (leitura)
                              └──► api-2 ─┘          ▲                       │
                                                     └──── leituras ─────────┘
```

| Serviço   | Imagem                | Função |
|-----------|-----------------------|--------|
| `traefik` | `traefik:v3.6`        | Único ponto de entrada (porta 80). Roteia e balanceia entre as réplicas da API. Painel em `http://localhost:8080/dashboard/`. |
| `api`     | `aluga-crajubar/api` (Dockerfile deste repositório) | Laravel + Apache. Sobe **2 réplicas**. Healthcheck em `/up`. |
| `master`  | `postgres:16-alpine`  | Banco primário: recebe todas as escritas. |
| `slave`   | `postgres:16-alpine`  | Réplica por *streaming replication* (hot standby): somente leitura. |

- **Redes:** `aluga-proxy` (Traefik ↔ API) e `aluga-backend` (API ↔ bancos, **interna**: os bancos não ficam expostos no seu PC).
- **Leitura/escrita:** `config/database.php` usa `read` → `slave` e `write` → `master`, com `sticky => true`. Transações e `lockForUpdate()` usam sempre o master.
- **Fotos:** volume `storage_public`, compartilhado entre as réplicas da API.
- **Volumes de dados:** `master_data`, `slave_data` e `storage_public`. Sobrevivem a `docker compose down`.

### Arquivos de infraestrutura

```
docker-compose.yml               # todos os serviços
Dockerfile                       # imagem da API (PHP 8.3 + Apache)
.dockerignore                    # o que NÃO entra na imagem
.env.docker.example              # modelo de configuração do Docker
docker/php/php.ini               # limites de upload (10 fotos de até 10 MB)
docker/postgres/master-init.sh   # cria o usuário replicator e o slot de replicação
docker/postgres/slave-entrypoint.sh  # clona o master (pg_basebackup) e inicia como réplica
database/seeders/fotos/          # fotos dos imóveis de demonstração
```

---

## Como rodar (Docker) — recomendado

### Requisitos

- **Docker Desktop** (no Windows, com WSL 2), já aberto e com *Engine running*.
- **Porta 80 livre**: feche IIS, XAMPP, Apache ou outro servidor local que use essa porta.
- **Não** é preciso ter PHP, Composer ou PostgreSQL instalados.

### Primeira vez

PowerShell, na pasta do repositório:

```powershell
# 1. Configuração
Copy-Item .env.docker.example .env.docker

# 2. Gerar a APP_KEY e colar no .env.docker (linha APP_KEY=)
docker compose run --rm api php artisan key:generate --show

# 3. Gerar o JWT_SECRET e colar no .env.docker (linha JWT_SECRET=)
[Convert]::ToBase64String((1..48 | ForEach-Object { Get-Random -Maximum 256 }))

# 4. Subir tudo (Traefik, master, slave e 2 réplicas da API)
docker compose up -d --build

# 5. Criar as tabelas e os dados de demonstração
docker compose exec -u www-data api php artisan migrate:fresh --seed --force
```

No Linux/macOS: use `cp .env.docker.example .env.docker` e gere o `JWT_SECRET` com `openssl rand -base64 48`.

Confira:

```powershell
docker compose ps                 # 5 containers: traefik, master, slave, api-1, api-2
curl.exe -i http://localhost/up   # HTTP 200
```

### Dia a dia

| Ação | Comando |
|------|---------|
| Ligar | `docker compose up -d` |
| Desligar (mantém os dados) | `docker compose down` |
| Ver status | `docker compose ps` |
| Ver logs da API (ao vivo) | `docker compose logs -f api` |
| Alterei código PHP | `docker compose up -d --build` |
| Alterei o `.env.docker` | `docker compose up -d` |
| Rodar um comando artisan | `docker compose exec -u www-data api php artisan <comando>` |
| Recriar banco com os dados de demonstração | `docker compose exec -u www-data api php artisan migrate:fresh --seed --force` |
| Apagar **tudo**, inclusive os dados | `docker compose down -v` |

> O código entra na imagem no momento do build. Depois de mudar arquivos PHP, rode `docker compose up -d --build`, senão a API continua com o código antigo.
>
> Use `-u www-data` nos comandos artisan que gravam arquivos (seed, storage). Assim o Apache continua com permissão para gravar as fotos enviadas pelo app.

### Dados de demonstração

O seed (`DatabaseSeeder`) cria sempre os mesmos dados em qualquer máquina, sem Faker:

| Papel | E-mail | Senha |
|-------|--------|-------|
| Admin | `admin@alugacrajubar.test` | `password` |
| Cliente | `cliente@alugacrajubar.test` | `password` |
| Cliente | `cliente1@alugacrajubar.test`, `cliente2@alugacrajubar.test` | `password` |
| Locador | `proprietario@alugacrajubar.test` | `password` |
| Locador | `proprietario1@alugacrajubar.test`, `proprietario2@alugacrajubar.test` | `password` |

E também:

- **10 imóveis** para diária (4 em Juazeiro do Norte, 3 no Crato e 3 em Barbalha), com coordenadas e fotos de `database/seeders/fotos/`;
- favoritos já marcados para os clientes.

Fotos novas seguem o padrão `casa01.jpg` … `casa10.png`. Fotos extras de um mesmo imóvel usam `casa08-2.jpg`, `casa08-3.jpg`. São aceitos jpg, jpeg e png.

> ⚠️ Contas e dados **de demonstração**. Nunca rode o seed em produção.

Cada desenvolvedor tem o próprio banco local: o que um cadastra não aparece para os outros. Para voltar ao estado inicial, rode o `migrate:fresh --seed` de novo.

---

## App Flutter: URL da API

A URL fica em `apiBaseUrl` (`lib/services/auth_service.dart`, no repositório `aluga-crajubar-mobile`). Com o Docker, o app fala com o **Traefik na porta 80**, sem `:8000`:

| Onde o app roda | URL base |
|-----------------|----------|
| Chrome (Windows) | `http://localhost/api` |
| Emulador Android | `http://10.0.2.2/api` |
| Celular físico (mesma rede Wi-Fi) | `http://IP-DO-PC/api`, por exemplo `http://192.168.0.10/api` |

Também dá para definir a URL sem mexer no código:

```powershell
flutter run -d chrome --dart-define=API_BASE_URL=http://localhost/api
```

Depois de trocar a URL, faça um *restart* completo do app. O hot reload não recarrega constantes.

---

## Provas de funcionamento (DevOps)

```powershell
# Replicação: no master deve aparecer state=streaming
docker compose exec master psql -U aluga -d aluga_crajubar -c "SELECT client_addr, state, sync_state FROM pg_stat_replication;"

# O slave é réplica: deve retornar t (true)
docker compose exec slave psql -U aluga -d aluga_crajubar -c "SELECT pg_is_in_recovery();"

# O slave recusa escrita: deve dar "cannot execute ... in a read-only transaction"
docker compose exec slave psql -U aluga -d aluga_crajubar -c "CREATE TABLE teste (id int);"

# Dado gravado pelo app (master) chegando no slave
docker compose exec slave psql -U aluga -d aluga_crajubar -c "SELECT id, name, email FROM users ORDER BY id DESC LIMIT 3;"

# Balanceamento: requisições alternando entre api-1 e api-2
docker compose logs -f api

# Tolerância a falha: derrubar UMA réplica e o app continua funcionando
docker stop aluga-crajubar-api-1
docker compose ps          # api-1 parada, api-2 no ar
docker start aluga-crajubar-api-1
```

---

## Rodar sem Docker (opcional)

Requisitos: PHP 8.2+, Composer e PostgreSQL 16 local.

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Nesse modo, a URL base do Flutter volta a ser `http://127.0.0.1:8000/api`, ou `http://10.0.2.2:8000/api` no emulador. Sem `DB_READ_HOST` e `DB_WRITE_HOST`, leitura e escrita usam o mesmo `DB_HOST`.

### Testes automatizados

Os testes usam SQLite e os pacotes de desenvolvimento (PHPUnit e Faker), que **não** estão na imagem Docker. Rode na sua máquina:

```bash
composer install
php artisan test
```

---

## Endpoints para o Flutter

URL base: veja a seção [App Flutter](#app-flutter-url-da-api). Nas chamadas autenticadas, envie `Authorization: Bearer {access_token}`.

### Autenticação e perfil

| Tela/ação | Método e rota | Corpo JSON |
| --- | --- | --- |
| Botão **Log In** | `POST /auth/login` | `email`, `password`, `remember` (booleano), `device_name` (opcional) |
| Botão **Cadastre-se** | `POST /auth/register` | `name`, `email`, `phone`, `password`, `password_confirmation`, `device_name` (opcional) |
| **Esqueceu a senha?** | `POST /auth/forgot-password` | `email` |
| Definir nova senha | `POST /auth/reset-password` | `email`, `token`, `password`, `password_confirmation` |
| Solicitar alteração de senha | `POST /auth/change-password` | `current_password`, `password`, `password_confirmation` |
| Verificar / reenviar código | `POST /auth/change-password/verify-code`, `POST /auth/change-password/resend-code` | — |
| Confirmar alteração de senha | `POST /auth/confirm-password-change` | `token` |
| Dados do perfil | `GET /auth/profile` | — |
| Editar perfil | `PATCH /auth/profile` | `name`, `email`, `phone` (envie ao menos um campo) |
| Hospedagem atual | `GET /auth/profile/active-stay` | — |
| Histórico de hospedagens | `GET /auth/profile/stays` | `per_page` (opcional, máximo 50) |
| Usuário logado | `GET /auth/me` | — |
| Sair | `POST /auth/logout` | — |

### Imóveis, favoritos, reservas e notificações

| Recurso | Rotas |
| --- | --- |
| Imóveis | `GET /imoveis` (busca e filtros), `GET /imoveis/{id}`, `POST /imoveis`, `PUT/PATCH /imoveis/{id}`, `DELETE /imoveis/{id}` |
| Fotos do imóvel | `POST /imoveis/{id}/fotos` (multipart, até 10 fotos de 10 MB), `DELETE /imoveis/{id}/fotos/{foto}`, `PATCH /imoveis/{id}/fotos/{foto}/principal` |
| Imóveis do locador | `GET /landlord/imoveis` |
| Favoritos | `GET /favoritos`, `GET /favoritos/pesquisar?busca=`, `POST /favoritos/{imovel}`, `DELETE /favoritos/{imovel}` |
| Reservas | `GET /bookings`, `POST /bookings`, `GET /bookings/disponibilidade`, `GET /bookings/solicitacoes`, `GET /bookings/{id}`, `GET /bookings/{id}/status`, `PATCH /bookings/{id}/confirmar`, `PATCH /bookings/{id}/recusar`, `PATCH /bookings/{id}/cancelar` |
| Quero ser locador | `GET /landlord-request`, `POST /landlord-request` |
| Admin: solicitações de locador | `GET /admin/landlord-requests`, `POST /admin/landlord-requests/{id}/approve`, `POST /admin/landlord-requests/{id}/reject` |
| Notificações | `GET /notifications`, `PATCH /notifications/{id}/read`, `POST /notifications/read-all` |
| Preferências de notificação | `GET /notification-preferences`, `PATCH /notification-preferences` |

Lista completa e atualizada: `docker compose exec api php artisan route:list --path=api`.

### Papéis

Papéis: `client`, `landlord` e `admin`. O cadastro público sempre cria um usuário com `role: "client"`.

Nas respostas de login e de `GET /auth/me`:
- `user.is_landlord` identifica locadores;
- `user.available_tabs` informa quais abas o Flutter pode exibir: `['cliente']` para clientes e `['cliente', 'locador']` para locadores.

Para virar locador, o usuário envia `POST /landlord-request`. Com `LANDLORD_AUTO_APPROVE=true` (padrão do MVP), a aprovação é imediata.

### Login e tokens

```json
{
  "email": "cliente@alugacrajubar.test",
  "password": "password",
  "remember": true,
  "device_name": "android"
}
```

O login devolve `access_token`, `token_type`, `expires_at` e os dados do usuário. Sem "lembrar-me", o token expira em 1 dia; marcado, em 30 dias. Guarde o token no armazenamento seguro do Flutter.

---

## Recuperação e alteração de senha

**Recuperação.** O e-mail de recuperação envia um JWT assinado (HS256), de uso único e válido por `JWT_TTL` minutos (padrão: 60). O identificador interno do JWT é guardado em hash e apagado após a redefinição. Um novo pedido invalida o anterior, e a resposta de `forgot-password` é a mesma exista a conta ou não.

O link usa o deep link `alugacrajubar://reset-password` (`MOBILE_RESET_PASSWORD_URL`). Como alguns clientes de e-mail (como o Gmail) não abrem esquemas próprios, defina `PASSWORD_RESET_WEB_URL=https://seu-endereco/reset-password` para usar a página `GET /reset-password` deste back-end, que abre o app. O token vai depois do `#`, que o navegador não envia ao servidor. Esse endereço precisa ser alcançável pelo celular: em teste local, use o IP do PC na mesma rede ou um túnel (ngrok, Cloudflare Tunnel).

**Alteração com sessão ativa.** O Flutter envia a senha atual e a nova para `POST /auth/change-password`. A alteração só é aplicada em `POST /auth/confirm-password-change`. Regras:
- a nova senha nunca vai dentro do JWT;
- a confirmação expira em `PASSWORD_CHANGE_TTL` minutos (padrão: 30) e é de uso único;
- ao confirmar, todas as sessões abertas são encerradas.

### E-mails

O padrão é `MAIL_MAILER=log`: nada é enviado.
- **No Docker**, o e-mail aparece em `docker compose logs api`.
- **Sem Docker**, ele fica em `storage/logs/laravel.log`.

Para enviar de verdade, configure SMTP no `.env.docker` (ou no `.env`) e rode `docker compose up -d`. Exemplo com Gmail, que exige verificação em duas etapas e uma **senha de app**:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=senha-de-app-gerada-no-google
MAIL_FROM_ADDRESS=seu-email@gmail.com
MAIL_FROM_NAME="Aluga Crajubar"
```

Outras opções: Brevo (`smtp-relay.brevo.com`, porta 587) e Mailtrap (caixa de testes que não entrega os e-mails). Na porta 465, acrescente `MAIL_SCHEME=smtps`.

**Nunca versione o `.env` nem o `.env.docker`, nem coloque credenciais reais no repositório.**

### Testar a recuperação sem depender do e-mail

```powershell
docker compose exec api php artisan tinker
>>> $u = App\Models\User::where('email', 'cliente@alugacrajubar.test')->first();
>>> app(App\Services\PasswordResetJwtService::class)->create($u);
```

Abra o resultado no emulador Android:

```powershell
adb shell am start -a android.intent.action.VIEW -d "alugacrajubar://reset-password?token=TOKEN&email=cliente@alugacrajubar.test"
```

---

## Problemas comuns

| Sintoma | Causa provável | Solução |
|---------|----------------|---------|
| `Bind for 0.0.0.0:80 failed` | Outro programa usa a porta 80 | Fechar IIS, XAMPP ou Apache local (`netstat -ano \| findstr ":80 "`) |
| App: "não foi possível se conectar à API" | URL ainda com `:8000` ou `127.0.0.1:8000` | Usar a URL da tabela do Flutter e fazer restart completo |
| Erro com `127.0.0.1:5433` | A requisição foi para o `php artisan serve` antigo | Desligar o `artisan serve`; o Docker substitui ele |
| `404 page not found` do Traefik | API fora do ar ou sem as labels | `docker compose ps` e `docker compose logs api` |
| `500` / `No application encryption key` | `APP_KEY` vazia no `.env.docker` | Gerar a chave (Primeira vez, passo 2) e rodar `docker compose up -d` |
| Slave não inicia (`pg_hba` / autenticação) | O volume do master foi criado antes dos scripts, ou a senha mudou | `docker compose down -v` e subir de novo (apaga os dados) |
| `sh: not found` / `\r` nos scripts | Scripts `.sh` salvos com CRLF | Salvar como **LF** (o `.gitattributes` já força `*.sh eol=lf`) |
| Mudança no PHP não aparece | A imagem não foi reconstruída | `docker compose up -d --build` |
| Upload de foto falha por permissão | Seed rodado como root | Rodar os comandos artisan com `-u www-data` |