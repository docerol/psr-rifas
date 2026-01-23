import { Rifa } from '../Types';

export const useRifa = () => {
    /**
     * Busca os dados de uma rifa pelo ID
     * @param id ID da rifa
     * @returns Promise com os dados da rifa
     */
    const fetchRifa = async (id: number): Promise<Rifa> => {
        try {
            const response = await fetch(`/api/rifas/${id}`);
            if (!response.ok) {
                throw new Error('Erro ao buscar rifa');
            }
            return await response.json();
        } catch (error) {
            console.error('Erro ao buscar rifa:', error);
            throw error;
        }
    };

    return {
        fetchRifa
    };
};

export default useRifa;
