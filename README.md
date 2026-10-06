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
