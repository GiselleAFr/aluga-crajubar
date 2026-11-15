<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchImovelRequest;
use App\Http\Resources\ImovelResource;
use App\Models\Imovel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class ImovelController extends Controller
{
    private const STATUSES = ['available', 'rented', 'unavailable'];

    public function index(SearchImovelRequest $request)
    {
        $query = Imovel::query()->with('fotos');

        // Busca textual por título, descrição ou localização
        // Busca textual: ignora maiúsculas/minúsculas e aceita várias palavras
        if ($request->filled('q')) {
            $palavras = preg_split(
                '/\s+/',
                mb_strtolower(trim($request->input('q'))),
                -1,
                PREG_SPLIT_NO_EMPTY
            );

            foreach ($palavras as $palavra) {
                $termo = '%' . addcslashes($palavra, '%_\\') . '%';

                $query->where(function ($q) use ($termo) {
                    $q->whereRaw('LOWER(titulo) LIKE ?', [$termo])
                        ->orWhereRaw('LOWER(descricao) LIKE ?', [$termo])
                        ->orWhereRaw('LOWER(cidade) LIKE ?', [$termo])
                        ->orWhereRaw('LOWER(bairro) LIKE ?', [$termo])
                        ->orWhereRaw('LOWER(tipo) LIKE ?', [$termo]);
                });
            }
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
        $query->when($request->filled('cidade'), function ($q) use ($request) {
            $q->whereRaw('LOWER(cidade) LIKE ?', ['%' . mb_strtolower($request->input('cidade')) . '%']);
        });

        // Filtro por bairro
        $query->when($request->filled('bairro'), function ($q) use ($request) {
            $q->whereRaw('LOWER(bairro) LIKE ?', ['%' . mb_strtolower($request->input('bairro')) . '%']);
        });


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

            case 'mais_favoritados':
                $query->withCount('usuariosFavoritaram')
                    ->orderByDesc('usuarios_favoritaram_count')
                    ->orderByDesc('created_at');
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

        $imoveis->through(fn (Imovel $imovel) => (new ImovelResource($imovel))->resolve());

        return response()->json($imoveis);
    }

    public function landlordIndex(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $imoveis = Imovel::query()
            ->with('fotos')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);

        $imoveis->through(fn (Imovel $imovel) => (new ImovelResource($imovel))->resolve());

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
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        // Cria o imóvel e atribui o proprietário autenticado explicitamente
        $imovel = new Imovel($dados);
        $imovel->user_id = $request->user()->id;
        $imovel->save();
        $imovel->load('fotos');

        return response()->json([
            'message' => 'Imóvel cadastrado com sucesso.',
            'data' => new ImovelResource($imovel),
        ], 201);
    }

    public function show(Imovel $imovel)
    {
        $imovel->load('fotos');

        return response()->json([
            'data' => new ImovelResource($imovel),
        ]);
    }

    public function update(Request $request, Imovel $imovel)
    {
        $this->ensureOwner($request, $imovel);

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
            'status' => ['sometimes', Rule::in(self::STATUSES)],
        ]);

        if (($dados['finalidade'] ?? $imovel->finalidade) === 'venda') {
            $dados['periodo_locacao'] = null;
        }

        $imovel->update($dados);

        return response()->json([
            'message' => 'Imóvel atualizado com sucesso.',
            'data' => new ImovelResource($imovel->fresh()->load('fotos')),
        ]);
    }

    public function destroy(Request $request, Imovel $imovel)
    {
        $this->ensureOwner($request, $imovel);

        foreach ($imovel->fotos()->get()->groupBy('disk') as $disk => $fotos) {
            $deleted = Storage::disk($disk)->delete($fotos->pluck('path')->all());
            if (! $deleted) {
                throw new RuntimeException('Não foi possível excluir as fotos do imóvel.');
            }
        }

        $imovel->delete();

        return response()->json([
            'message' => 'Imóvel excluído com sucesso.',
        ]);
    }

    private function ensureOwner(Request $request, Imovel $imovel): void
    {
        abort_unless((int) $imovel->user_id === (int) $request->user()->id, 404);
    }
}
