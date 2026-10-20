<?php

namespace App\Http\Controllers;

use App\Models\Imovel;
use Illuminate\Http\Request;

class FavoritoController extends Controller
{
    /**
     * Adiciona um imóvel aos favoritos do usuário.
     */
    public function adicionar(Request $request, Imovel $imovel)
    {
        $request->user()
            ->favoritos()
            ->syncWithoutDetaching([$imovel->id]);

        return response()->json([
            'message' => 'Imóvel adicionado aos favoritos.',
            'imovel' => $imovel,
        ], 201);
    }

    /**
     * Remove um imóvel dos favoritos do usuário.
     */
    public function remover(Request $request, Imovel $imovel)
    {
        $removido = $request->user()
            ->favoritos()
            ->detach($imovel->id);

        if ($removido === 0) {
            return response()->json([
                'message' => 'Este imóvel não está nos seus favoritos.',
            ], 404);
        }

        return response()->json([
            'message' => 'Imóvel removido dos favoritos.',
        ]);
    }

    /**
     * Lista os imóveis favoritos do usuário autenticado.
     */
    public function listar(Request $request)
    {
        $favoritos = $request->user()
            ->favoritos()
            ->paginate(10);

        return response()->json($favoritos);
    }

    /**
     * Pesquisa imóveis na lista de favoritos.
     */
    public function pesquisar(Request $request)
    {
        $dados = $request->validate([
            'busca' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $favoritos = $request->user()
            ->favoritos()
            ->where(function ($query) use ($dados) {
                $busca = $dados['busca'];

                $query->where('titulo', 'ILIKE', "%{$busca}%")
                    ->orWhere('cidade', 'ILIKE', "%{$busca}%")
                    ->orWhere('bairro', 'ILIKE', "%{$busca}%");
            })
            ->paginate(10);

        return response()->json($favoritos);
    }
}