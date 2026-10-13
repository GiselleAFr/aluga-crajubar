<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchImovelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',               // Busca textual (ex: "casa para alugar", "quarto em pousada")
            'tipo' => 'nullable|string',                    // Ex: "casa", "apartamento", "quarto", "pousada" ou múltiplos separados por vírgula
            'finalidade' => 'nullable|string|in:aluguel,venda,diaria',
            'preco_min' => 'nullable|numeric|min:0',
            'preco_max' => 'nullable|numeric|min:0',
            'preco_dia_max' => 'nullable|numeric|min:0',    // Para buscas do tipo "até 100/dia"
            'cidade' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'quartos' => 'nullable|integer|min:0',
            'ordenacao' => 'nullable|string|in:mais_recentes,menor_preco,maior_preco',
            'per_page' => 'nullable|integer|min:1|max:100', // Paginação para o app Flutter
        ];
    }
}