<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Redefinir senha - Aluga Crajubar</title>
    <style>
        body { margin: 0; font-family: Roboto, Arial, sans-serif; background: #f8f7f5; color: #222; }
        main { max-width: 420px; margin: 0 auto; padding: 48px 24px; text-align: center; }
        h1 { color: #963333; font-size: 26px; margin: 0 0 16px; }
        p { line-height: 1.5; margin: 0 0 24px; }
        .btn { display: inline-block; background: #963333; color: #fff; text-decoration: none; font-weight: bold; padding: 14px 28px; border-radius: 30px; }
        .small { font-size: 13px; color: #666; margin-top: 28px; }
    </style>
</head>
<body>
<main>
    <h1>Redefinir senha</h1>
    <p id="msg">Toque no botão para abrir o aplicativo e criar sua nova senha.</p>
    <a id="abrir" class="btn" href="#">Abrir no aplicativo</a>
    <p class="small">O link expira e só pode ser usado uma vez. Se você não pediu a recuperação, ignore o e-mail.</p>
</main>
<script>
    (function () {
        var base = @json($appUrl);
        var params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
        var token = params.get('token');
        var email = params.get('email');
        var botao = document.getElementById('abrir');

        if (!token || !email) {
            botao.style.display = 'none';
            document.getElementById('msg').textContent =
                'Link inválido. Peça uma nova recuperação de senha no aplicativo.';
            return;
        }

        var link = base.replace(/\?+$/, '') + '?' +
            new URLSearchParams({ token: token, email: email }).toString();
        botao.setAttribute('href', link);
        window.setTimeout(function () { window.location.href = link; }, 300);
    })();
</script>
</body>
</html>
