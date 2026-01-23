declare type Order = {
  id: number;
  customer_fullname: string;
  customer_email: string;
  customer_telephone: string;
  numbers_reserved: string[];
  status: string;
  expire_at: string | null;
  created_at: string;
  updated_at: string;
  rifa_id: number;
};

declare type Payment = {
  ticket_url: string;
  order_id: number;
  id: number;
};

declare type OrderWithPayment = Order & {
  payment: Payment | null;
};

declare type OrderStatuses = {
  expired: string;
  paid: string;
  reserved: string;
  unknown: string;
  [k: string]: string;
};

declare type Ranking = {
  customer_fullname: string;
  total_numbers: number;
};

declare interface Rifa {
  id: number;
  title: string;
  thumbnail: string;
  ticket_price: number;
  price: number; // Mantendo para compatibilidade
  description: string;
  slug: string;
  total_tickets: number;
  total_numbers_available: number; // Mantendo para compatibilidade
  available_tickets: number;
  buy_max: number;
  buy_min: number;
  raffle: string;
  status: 'draft' | 'published' | 'finished' | 'active' | 'completed' | 'cancelled';
  ranking_buyer: boolean;
  draw_date: string;
  expired_at: string | null;
  created_at: string;
  updated_at: string;
};

declare type FormReserveNumbers = {
  fullname: string;
  email: string;
  telephone: string;
  confirmTelephone: string;
  terms: boolean;
  quantity: number;
  rifa: Rifa['id'];
};

declare type Slideshow = {
  image: string;
  alt: string;
};

declare type Winner = {
  customer_fullname: Order['customer_fullname'];
  video: string | null;
  position: number;
};

declare type Testimonial = {
  id: number;
  testimonial: string;
  order: Pick<Order, 'customer_fullname'>;
};

// Função para buscar uma rifa
declare function fetchRifa(id: number): Promise<Rifa>;

export type { Order, Payment, OrderWithPayment, OrderStatuses, Ranking, Rifa, FormReserveNumbers, Slideshow, Winner, Testimonial };
export { fetchRifa };
