# Aluga Crajubar — API

API Laravel 12 para o aplicativo Flutter, usando PostgreSQL e tokens Bearer do Laravel Sanctum.

## Preparação

Requisitos: PHP 8.2+, Composer e PostgreSQL 16 (ou Docker).

```bash
docker compose up -d
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

No Linux/macOS, troque `copy` por `cp`. A configuração de PostgreSQL já está no `.env.example`. Para enviar e-mails de recuperação em desenvolvimento, ajuste `MAIL_MAILER=log`; o link ficará em `storage/logs/laravel.log`.

## Endpoints para o Flutter

URL base local: `http://SEU-IP:8000/api`. Nas chamadas autenticadas envie `Authorization: Bearer {access_token}`.

| Tela/ação | Método e rota | Corpo JSON |
| --- | --- | --- |
| Botão **Log In** | `POST /auth/login` | `email`, `password`, `remember` (booleano), `device_name` (opcional) |
| Botão **Cadastre-se** | `POST /auth/register` | `name`, `email`, `phone`, `password`, `password_confirmation`, `device_name` (opcional) |
| **Esqueceu a senha?** | `POST /auth/forgot-password` | `email` |
| Definir nova senha | `POST /auth/reset-password` | `email`, `token`, `password`, `password_confirmation` |
| Solicitar alteração de senha | `POST /auth/change-password` | `current_password`, `password`, `password_confirmation` |
| Confirmar alteração de senha | `POST /auth/confirm-password-change` | `token` |
| Dados do perfil | `GET /auth/profile` | — |
| Editar perfil | `PATCH /auth/profile` | `name`, `email`, `phone` (envie ao menos um campo) |
| Histórico de hospedagens | `GET /auth/profile/stays` | `per_page` (opcional, máximo 50) |
| Buscar coordenadas | `GET /maps/geocode` | `address` |
| Localização salva de um imóvel | `GET /properties/{id}/location` | — |
| Usuário logado | `GET /auth/me` | — |
| Sair | `POST /auth/logout` | — |

O cadastro público sempre cria um usuário com `role: "client"`. Nas respostas de login e `GET /auth/me`, o campo `user.is_landlord` identifica locadores e `user.available_tabs` informa quais abas o Flutter pode exibir: `['cliente']` para clientes e `['cliente', 'locador']` para locadores. A atribuição do papel `landlord` não é exposta no cadastro público, evitando elevação indevida de privilégio.

Exemplo de login:

```json
{
  "email": "ana@exemplo.com",
  "password": "minha-senha-segura",
  "remember": true,
  "device_name": "android"
}
```

O login devolve `access_token`, `token_type`, `expires_at` e os dados do usuário. Sem “lembrar-me”, o token expira em 1 dia; marcado, em 30 dias. Guarde o token no armazenamento seguro do Flutter.

O e-mail de recuperação envia um JWT assinado (HS256), válido por 60 minutos e de uso único. O identificador interno do JWT é armazenado em hash e apagado após a redefinição, impedindo que o mesmo link seja reutilizado. Configure uma chave forte em `JWT_SECRET` no `.env` de produção.

O link usa o deep link `alugacrajubar://reset-password`. Configure o mesmo esquema no app Flutter e, se necessário, altere `MOBILE_RESET_PASSWORD_URL` no `.env`.

Para alterar a senha de uma sessão autenticada, o Flutter envia a senha atual e a nova senha para `POST /auth/change-password`. A API envia uma confirmação para `MOBILE_CONFIRM_PASSWORD_CHANGE_URL`; somente `POST /auth/confirm-password-change` com o JWT recebido aplica a alteração. A nova senha nunca é colocada no JWT, a confirmação expira em 30 minutos, pode ser usada uma única vez e encerra todas as sessões existentes.

As rotas de perfil exigem Bearer token. `GET /auth/profile` fornece os dados para o card de perfil e a contagem de hospedagens concluídas. `GET /auth/profile/stays` retorna somente as hospedagens do usuário autenticado, em ordem da mais recente para a mais antiga e com paginação. A tabela `bookings` foi criada para registrar esse histórico e será preenchida pelo futuro fluxo de reservas.

## Localização gratuita — OpenStreetMap

A geocodificação usa o Nominatim, serviço do OpenStreetMap, portanto não requer chave de API nem cadastro de cartão. Configure `NOMINATIM_USER_AGENT` no `.env` com o nome do projeto e um e-mail real de contato. A rota `GET /api/maps/geocode?address=...` retorna endereço formatado, `place_id`, latitude, longitude, `map_url`, o provedor e a atribuição obrigatória do OpenStreetMap.

O serviço público do Nominatim deve receber no máximo uma requisição por segundo. A API limita a rota a 30 requisições por minuto e mantém cada resultado em cache por sete dias; para alto volume, use um provedor hospedado ou uma instância própria. O Flutter deve exibir a atribuição `© OpenStreetMap contributors` ao mostrar dados ou mapa provenientes desta rota.

A tabela `properties` armazena endereço, coordenadas e `place_id`. `GET /api/properties/{id}/location` disponibiliza esses dados para o mapa do imóvel sem fazer nova geocodificação.
