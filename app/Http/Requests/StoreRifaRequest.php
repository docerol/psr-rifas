<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRifaRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Rifa::class);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'total_tickets' => ['required', 'integer', 'min:10', 'max:100000'],
            'draw_date' => ['required', 'date', 'after:today'],
            'expired_at' => ['required', 'date', 'after:draw_date'],
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published', 'finished', 'canceled'])],
            'slug' => ['required', 'string', 'max:255', 'unique:rifas,slug'],
        ];
    }

    /**
     * Obtém as mensagens de erro personalizadas para as regras de validação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'O título da rifa é obrigatório',
            'title.max' => 'O título não pode ter mais de 255 caracteres',
            'description.required' => 'A descrição da rifa é obrigatória',
            'price.required' => 'O preço do bilhete é obrigatório',
            'price.min' => 'O preço mínimo do bilhete é R$ 0,01',
            'price.max' => 'O preço máximo do bilhete é R$ 10.000,00',
            'total_tickets.required' => 'O número total de bilhetes é obrigatório',
            'total_tickets.min' => 'A rifa deve ter no mínimo 10 bilhetes',
            'total_tickets.max' => 'A rifa pode ter no máximo 100.000 bilhetes',
            'draw_date.required' => 'A data do sorteio é obrigatória',
            'draw_date.after' => 'A data do sorteio deve ser futura',
            'expired_at.required' => 'A data de expiração é obrigatória',
            'expired_at.after' => 'A data de expiração deve ser posterior à data do sorteio',
            'thumbnail.required' => 'A imagem de capa é obrigatória',
            'thumbnail.image' => 'O arquivo deve ser uma imagem',
            'thumbnail.mimes' => 'A imagem deve ser do tipo JPG, JPEG ou PNG',
            'thumbnail.max' => 'A imagem não pode ter mais de 2MB',
            'status.required' => 'O status da rifa é obrigatório',
            'status.in' => 'O status informado é inválido',
            'slug.required' => 'O slug é obrigatório',
            'slug.unique' => 'Este slug já está em uso',
        ];
    }

    /**
     * Prepara os dados para validação.
     */
    protected function prepareForValidation()
    {
        // Garante que o preço seja tratado como número
        if ($this->has('price')) {
            $this->merge([
                'price' => (float) str_replace(['R$', '.', ','], ['', '', '.'], $this->price)
            ]);
        }
    }
}
