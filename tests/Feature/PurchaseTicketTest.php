<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Rifa;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\OrderStatus;
use App\Models\RifaNumber;

class PurchaseTicketTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_prevents_double_booking_on_concurrent_requests()
    {
        // Cria uma rifa com 10 números disponíveis
        $rifa = Rifa::factory()->create([
            'total_numbers_available' => 10,
            'buy_min' => 1,
            'buy_max' => 10,
            'status' => 'published',
        ]);

        // Cria os números da rifa
        for ($i = 1; $i <= 10; $i++) {
            RifaNumber::create([
                'rifa_id' => $rifa->id,
                'number' => $i,
                'status' => 'available'
            ]);
        }

        // Cria dois usuários
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Desativa temporariamente o middleware de verificação de token CSRF para testes
        $this->withoutMiddleware();

        // Primeira requisição (deve ter sucesso)
        $response1 = $this->postJson('/api/tickets/reserve', [
            'rifa_id' => $rifa->id,
            'ticket_numbers' => [1, 2, 3],
            'customer' => [
                'name' => 'Test User 1',
                'email' => 'test1@example.com',
                'phone' => '11999999999',
            ]
        ]);

        // Verifica a resposta da primeira requisição
        $response1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Bilhetes reservados com sucesso!'
            ]);

        // Tenta reservar os mesmos números novamente (deve falhar)
        $response2 = $this->postJson('/api/tickets/reserve', [
            'rifa_id' => $rifa->id,
            'ticket_numbers' => [1, 2, 3], // Mesmos números!
            'customer' => [
                'name' => 'Test User 2',
                'email' => 'test2@example.com',
                'phone' => '11888888888',
            ]
        ]);

        // Verifica a resposta de conflito
        $response2->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error_code' => 'INSUFFICIENT_NUMBERS'
            ]);
        
        // Verifica se apenas um pedido foi criado
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', [
            'customer_email' => 'test1@example.com',
            'status' => 'reserved',
        ]);

        // Verifica se os números foram reservados corretamente
        $this->assertDatabaseHas('rifa_numbers', [
            'number' => 1,
            'status' => 'reserved',
            'rifa_id' => $rifa->id
        ]);
    }

    /** @test */
    public function it_expires_orders_after_timeout()
    {
        // Cria uma rifa
        $rifa = Rifa::factory()->create([
            'status' => 'published',
        ]);

        // Cria um pedido que já expirou
        $order = Order::factory()->create([
            'rifa_id' => $rifa->id,
            'expires_at' => now()->subMinutes(120), // 2 horas atrás
            'status' => 'reserved',
        ]);

        // Cria números associados ao pedido
        for ($i = 1; $i <= 3; $i++) {
            RifaNumber::create([
                'rifa_id' => $rifa->id,
                'order_id' => $order->id,
                'number' => $i,
                'status' => 'reserved'
            ]);
        }

        // Executa o comando para limpar pedidos expirados
        $this->artisan('app:clear-expired-orders-command')
             ->assertExitCode(0);

        // Verifica se o pedido expirado foi removido
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        
        // Verifica se os números foram liberados
        $this->assertEquals(3, RifaNumber::where('rifa_id', $rifa->id)
            ->where('status', 'available')
            ->count());
    }
}
