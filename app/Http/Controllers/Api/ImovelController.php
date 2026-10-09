<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Imovel;
use Illuminate\Http\Request;

class ImovelController extends Controller
{
    public function index(Request $request)
    {
        $query = Imovel::query();

        // Pesquisa por título, descrição ou localização
        if ($request->filled('busca')) {
            $busca = $request->busca;

            $query->where(function ($q) use ($busca) {
                $q->where('titulo', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%")
                    ->orWhere('cidade', 'like', "%{$busca}%")
                    ->orWhere('bairro', 'like', "%{$busca}%");
            });
        }

        // Filtros
        $query->when($request->filled('tipo'), function ($q) use ($request) {
            $q->where('tipo', $request->tipo);
        });

        $query->when($request->filled('finalidade'), function ($q) use ($request) {
            $q->where('finalidade', $request->finalidade);
        });

        $query->when($request->filled('cidade'), function ($q) use ($request) {
            $q->where('cidade', 'like', '%' . $request->cidade . '%');
        });

        $query->when($request->filled('preco_min'), function ($q) use ($request) {
            $q->where('preco', '>=', $request->preco_min);
        });

        $query->when($request->filled('preco_max'), function ($q) use ($request) {
            $q->where('preco', '<=', $request->preco_max);
        });

        // Ordenação com campos permitidos
        $ordenacoes = [
            'preco',
            'titulo',
            'created_at',
            'area',
        ];

        $ordenarPor = $request->query('ordenar_por', 'created_at');
        $direcao = $request->query('direcao', 'desc');

        if (! in_array($ordenarPor, $ordenacoes, true)) {
            return response()->json([
                'message' => 'Campo de ordenação inválido.',
            ], 422);
        }

        if (! in_array($direcao, ['asc', 'desc'], true)) {
            return response()->json([
                'message' => 'Direção de ordenação inválida.',
            ], 422);
        }

        $imoveis = $query
            ->orderBy($ordenarPor, $direcao)
            ->paginate(15)
            ->withQueryString();

        return response()->json($imoveis);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'tipo' => ['required', 'in:casa,apartamento,terreno,comercial,outro'],
            'finalidade' => ['required', 'in:venda,aluguel'],
            'preco' => ['required', 'numeric', 'min:0'],
            'cidade' => ['required', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'endereco' => ['required', 'string', 'max:255'],
            'quartos' => ['sometimes', 'integer', 'min:0'],
            'banheiros' => ['sometimes', 'integer', 'min:0'],
            'area' => ['nullable', 'numeric', 'min:0'],
        ]);

        $imovel = Imovel::create($dados);

        return response()->json([
            'message' => 'Imóvel cadastrado com sucesso.',
            'data' => $imovel,
        ], 201);
    }

    public function show(Imovel $imovel)
    {
        return response()->json([
            'data' => $imovel,
        ]);
    }

    public function update(Request $request, Imovel $imovel)
    {
        $dados = $request->validate([
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string'],
            'tipo' => ['sometimes', 'required', 'in:casa,apartamento,terreno,comercial,outro'],
            'finalidade' => ['sometimes', 'required', 'in:venda,aluguel'],
            'preco' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cidade' => ['sometimes', 'required', 'string', 'max:255'],
            'bairro' => ['sometimes', 'required', 'string', 'max:255'],
            'endereco' => ['sometimes', 'required', 'string', 'max:255'],
            'quartos' => ['sometimes', 'integer', 'min:0'],
            'banheiros' => ['sometimes', 'integer', 'min:0'],
            'area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        $imovel->update($dados);

        return response()->json([
            'message' => 'Imóvel atualizado com sucesso.',
            'data' => $imovel->fresh(),
        ]);
    }

    public function destroy(Imovel $imovel)
    {
        $imovel->delete();

        return response()->json([
            'message' => 'Imóvel excluído com sucesso.',
        ]);
    }
}
