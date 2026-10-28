<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchImovelRequest;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImovelController extends Controller
{
    public function index(SearchImovelRequest $request)
    {
        $query = Imovel::query();

        // Busca textual por título, descrição ou localização
        if ($request->filled('q')) {
            $busca = $request->input('q');

            $query->where(function ($q) use ($busca) {
                $q->where('titulo', 'like', "%{$busca}%")
                    ->orWhere('descricao', 'like', "%{$busca}%")
                    ->orWhere('cidade', 'like', "%{$busca}%")
                    ->orWhere('bairro', 'like', "%{$busca}%");
            });
        }

        // Filtro por tipo de imóvel
        if ($request->filled('tipo')) {
            $tipos = explode(',', $request->input('tipo'));

            $query->whereIn('tipo', $tipos);
        }

        // Filtro por finalidade
        $query->when(
            $request->filled('finalidade'),
            function ($q) use ($request) {
                $q->where('finalidade', $request->input('finalidade'));
            }
        );

        // Filtro por período de locação
        $query->when(
            $request->filled('periodo_locacao'),
            function ($q) use ($request) {
                $q->where('periodo_locacao', $request->input('periodo_locacao'));
            }
        );

        // Filtro por cidade
        $query->when(
            $request->filled('cidade'),
            function ($q) use ($request) {
                $q->where('cidade', 'like', '%' . $request->input('cidade') . '%');
            }
        );

        // Filtro por bairro
        $query->when(
            $request->filled('bairro'),
            function ($q) use ($request) {
                $q->where('bairro', 'like', '%' . $request->input('bairro') . '%');
            }
        );

        // Filtro por preço mínimo
        $query->when(
            $request->filled('preco_min'),
            function ($q) use ($request) {
                $q->where('preco', '>=', $request->input('preco_min'));
            }
        );

        // Filtro por preço máximo
        $query->when(
            $request->filled('preco_max'),
            function ($q) use ($request) {
                $q->where('preco', '<=', $request->input('preco_max'));
            }
        );

        // Filtro por preço máximo da diária
        $query->when(
            $request->filled('preco_dia_max'),
            function ($q) use ($request) {
                $q->where('finalidade', 'aluguel')
                    ->where('periodo_locacao', 'diaria')
                    ->where('preco', '<=', $request->input('preco_dia_max'));
            }
        );

        // Filtro por quantidade mínima de quartos
        $query->when(
            $request->filled('quartos'),
            function ($q) use ($request) {
                $q->where('quartos', '>=', $request->integer('quartos'));
            }
        );

        // Ordenação dos imóveis
        $ordenacao = $request->input('ordenacao', 'mais_recentes');

        switch ($ordenacao) {
            case 'menor_preco':
                $query->orderBy('preco', 'asc');
                break;

            case 'maior_preco':
                $query->orderBy('preco', 'desc');
                break;

            case 'mais_recentes':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // Quantidade de resultados por página
        $perPage = $request->integer('per_page', 15);

        $imoveis = $query
            ->paginate($perPage)
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
            'periodo_locacao' => [
                'required_if:finalidade,aluguel',
                'prohibited_if:finalidade,venda',
                'nullable',
                'in:diaria,mensal',
            ],
            'preco' => ['required', 'numeric', 'min:0'],
            'cidade' => ['required', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'endereco' => ['required', 'string', 'max:255'],
            'quartos' => ['sometimes', 'integer', 'min:0'],
            'banheiros' => ['sometimes', 'integer', 'min:0'],
            'area' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Cria o imóvel e atribui o proprietário autenticado explicitamente
        $imovel = new Imovel($dados);
        $imovel->user_id = $request->user()->id;
        $imovel->save();

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
            'periodo_locacao' => [
                Rule::requiredIf(
                    fn () => $request->has('finalidade')
                        && $request->input('finalidade') === 'aluguel'
                ),
                Rule::prohibitedIf(
                    fn () => $request->input('finalidade', $imovel->finalidade) === 'venda'
                ),
                'nullable',
                'in:diaria,mensal',
            ],
            'preco' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cidade' => ['sometimes', 'required', 'string', 'max:255'],
            'bairro' => ['sometimes', 'required', 'string', 'max:255'],
            'endereco' => ['sometimes', 'required', 'string', 'max:255'],
            'quartos' => ['sometimes', 'integer', 'min:0'],
            'banheiros' => ['sometimes', 'integer', 'min:0'],
            'area' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        if (($dados['finalidade'] ?? $imovel->finalidade) === 'venda') {
            $dados['periodo_locacao'] = null;
        }

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
