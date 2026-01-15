<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientNumbersException;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Exibe os detalhes de um pedido
     *
     * @param string $id ID do pedido
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function show(string $id)
    {
        $result = $this->orderService->findOrderWithRelations(
            (int) $id,
            [
                'rifa' => fn ($query) => $query->select('id', 'title', 'price', 'slug'),
                'payment' => fn ($query) => $query->select('id', 'order_id')
            ]
        );

        // Se o pedido não for encontrado, redireciona para a página inicial
        if ($result === null) {
            return redirect('/');
        }

        // Se o pedido estiver expirado, redireciona para a página da rifa
        if (now() > $result->expire_at) {
            return redirect(route('rifas.show', ['rifa' => $result->rifa]));
        }

        // Se já tiver pagamento, redireciona para a página de pagamento
        if ($result->payment) {
            return redirect(route('payment.show', ['payment' => $result->payment]));
        }

        $rifa = $result->rifa;
        $order = $result->makeHidden('rifa');
        
        // Calcula o valor total da transação
        $order->transaction_amount = $rifa->price * count($order->numbers_reserved);
        $order->expire_at = $order->expire_at;

        return inertia('Order/PsrResume', [
            'order' => $order,
            'rifa' => $rifa,
        ]);
    }

    /**
     * Armazena um novo pedido
     *
     * @param StoreOrderRequest $request
     * @return \Inertia\Response|RedirectResponse
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orderService->createOrder($request->validated());
            return Inertia::location(route('orders.show', $order->id));
            
        } catch (InsufficientNumbersException $e) {
            return back()->withErrors([
                'quantity' => 'Não há números suficientes disponíveis. Por favor, tente uma quantidade menor.'
            ])->withInput();
            
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'error' => 'Ocorreu um erro ao processar seu pedido. Por favor, tente novamente.'
            ])->withInput();
        }
    }
}
