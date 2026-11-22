<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aprovação automática de locadores (MVP)
    |--------------------------------------------------------------------------
    |
    | Quando verdadeiro, quem envia o cadastro de locador é aprovado na hora:
    | o papel muda para "landlord" sem passar por análise de administrador e
    | sem exigir o envio de documentos.
    |
    | Na versão com painel administrativo, defina LANDLORD_AUTO_APPROVE=false
    | no .env: o fluxo de análise (aprovar/recusar) volta a valer.
    |
    */

    'auto_approve' => filter_var(env('LANDLORD_AUTO_APPROVE', false), FILTER_VALIDATE_BOOLEAN),

];