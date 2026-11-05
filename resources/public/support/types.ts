export interface Option {
  value: string;
  label: string;
}

export interface OrderOption {
  id: number;
  number: string;
  label: string;
  date: string;
  total: string;
}

/** Settings every storefront app receives from PHP. */
export interface BaseConfig {
  ajaxUrl: string;
  nonce: string;
  brand: string;
  radiusCard: string;
  radiusControl: string;
  i18n: Record<string, string>;
}

export interface SupportConfig extends BaseConfig {
  manageUrl: string;
  openUrl: string;
  perPage: number;
  user: { name: string; email: string };
  categories: Option[];
  priorities: Option[];
  statusFilters: Option[];
  sortOptions: Option[];
  orders: OrderOption[];
  limits: {
    maxFileMb: number;
    maxFiles: number;
    maxTotalMb: number;
    maxSubject: number;
    maxMessage: number;
    extensions: string[];
  };
}

export type ThemeMode = 'auto' | 'light' | 'dark';

export interface InstanceProps {
  view: 'manage' | 'open' | 'auth';
  ticket: string;
  theme: ThemeMode;
  context: string;
  created: boolean;
  /** Sign-in screen (logged-out visitors). */
  auth: { title: string; lead: string; login: string; register: string };
}

export type StatusClass = 'open' | 'awaiting-staff' | 'awaiting-customer' | 'resolved' | 'closed' | 'default';
export type PriorityClass = 'high' | 'normal' | 'low';

export interface TicketRow {
  ticket_number: string;
  subject: string;
  status: string;
  status_label: string;
  status_class: StatusClass;
  category: string;
  category_label: string;
  priority: string;
  priority_label: string;
  priority_class: PriorityClass;
  customer_name: string;
  order_id: string;
  order_label: string;
  created_at: string;
  updated_at: string;
  ticket_url: string;
}

export interface TicketList {
  tickets: TicketRow[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export interface Attachment {
  id: number;
  name: string;
  mime: string;
  size: number;
  is_image: boolean;
  url: string;
  download_url: string;
}

export interface Message {
  id?: number | string;
  author_type?: string;
  sender_type?: string;
  role?: string;
  author_name?: string;
  body_html?: string;
  message?: string;
  created_at?: string;
  sent_at?: string;
  attachments?: Attachment[];
}

export interface Conversation {
  ticket_number?: string;
  subject: string;
  ticket: Partial<TicketRow>;
  messages: Message[];
  can_reply: boolean;
  reload_ticket?: string;
  message?: string;
}

export type Route =
  | { name: 'list' }
  | { name: 'ticket'; number: string; created?: boolean }
  | { name: 'new' };
