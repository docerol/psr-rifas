<?php

namespace App\Http\Requests;

use App\Models\Rifa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRifaRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $rifa = $this->route('rifa');
        return Gate::allows('update', $rifa);
    }

    /**
     * Obtém as regras de validação que se aplicam à requisição.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rifa = $this->route('rifa');
        
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:1000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:10000'],
            'total_tickets' => [
                'sometimes', 
                'required', 
                'integer', 
                'min:10', 
                'max:100000',
                // Garante que não podemos reduzir o total de bilhetes para menos do que já foram vendidos
                function ($attribute, $value, $fail) use ($rifa) {
                    if ($rifa && $value < $rifa->orders()->count()) {
                        $fail('O número total de bilhetes não pode ser menor que a quantidade já vendida.');
                    }
                },
            ],
            'draw_date' => ['sometimes', 'required', 'date', 'after:today'],
            'expired_at' => ['sometimes', 'required', 'date', 'after:draw_date'],
            'thumbnail' => ['sometimes', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'status' => [
                'sometimes',
                'required', 
                Rule::in(['draft', 'published', 'finished', 'canceled']),
                // Validações adicionais para mudança de status
                function ($attribute, $value, $fail) use ($rifa) {
                    if ($rifa && $rifa->status === 'finished' && $value !== 'finished') {
                        $fail('Não é possível alterar o status de uma rifa finalizada.');
                    }
                    
                    if ($rifa && $rifa->status === 'canceled' && $value !== 'canceled') {
                        $fail('Não é possível alterar o status de uma rifa cancelada.');
                    }
                },
            ],
            'slug' => [
                'sometimes',
                'required', 
                'string', 
                'max:255', 
                Rule::unique('rifas', 'slug')->ignore($rifa->id)
            ],
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
