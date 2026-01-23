<?php

namespace Database\Seeders;

use App\Models\Rifa;
use App\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    /**
     * Executa o seeder para popular a tabela de bilhetes
     */
    public function run(): void
    {
        // Obtém todas as rifas ativas
        $rifas = Rifa::where('status', Rifa::STATUS_PUBLISHED)->get();

        if ($rifas->isEmpty()) {
            $this->command->warn('Nenhuma rifa ativa encontrada. Crie uma rifa antes de executar este seeder.');
            return;
        }

        // Para cada rifa, cria os bilhetes
        foreach ($rifas as $rifa) {
            $this->createTicketsForRifa($rifa);
        }
    }

    /**
     * Cria bilhetes numerados para uma rifa específica
     */
    protected function createTicketsForRifa(Rifa $rifa): void
    {
        $this->command->info("Criando bilhetes para a rifa: {$rifa->title} (ID: {$rifa->id})");
        
        $totalTickets = $rifa->total_numbers_available;
        $batchSize = 1000; // Tamanho do lote para inserção em massa
        $tickets = [];
        $existingNumbers = [];
        
        // Verifica se já existem bilhetes para esta rifa
        $existingTickets = Ticket::where('rifa_id', $rifa->id)->pluck('number')->toArray();
        
        if (!empty($existingTickets)) {
            $this->command->warn("Já existem bilhetes cadastrados para esta rifa. Nenhum bilhete será criado.");
            return;
        }
        
        $bar = $this->command->getOutput()->createProgressBar($totalTickets);
        $bar->start();
        
        // Cria bilhetes em lotes para melhor desempenho
        for ($i = 1; $i <= $totalTickets; $i++) {
            // Verifica se o número já foi criado (evita duplicatas)
            if (in_array($i, $existingNumbers)) {
                continue;
            }
            
            $tickets[] = [
                'rifa_id' => $rifa->id,
                'number' => $i,
                'price' => $rifa->price,
                'status' => 'available',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            $existingNumbers[] = $i;
            $bar->advance();
            
            // Insere em lotes para melhor desempenho
            if (count($tickets) >= $batchSize) {
                Ticket::insert($tickets);
                $tickets = [];
            }
        }
        
        // Insere os bilhetes restantes
        if (!empty($tickets)) {
            Ticket::insert($tickets);
        }
        
        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("Foram criados " . count($existingNumbers) . " bilhetes para a rifa {$rifa->title}.");
    }
}
