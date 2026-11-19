-- Delisys v0.1: cardápio, pedidos, pagamento, motoboys, rastreio e avisos de WhatsApp.
-- Recorte de brendi/replica/schema.sql para o primeiro teste no ar.
-- Acesso: o app checa a permissão em toda consulta (sessão do painel → store_id;
-- token do motoboy → courier_id; token do pedido → order_id). Sem RLS nesta versão.

create table stores (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
  name text not null,
  whatsapp_phone text,
  address_line text,
  district text,
  city text,
  lat double precision,
  lng double precision,
  is_open boolean not null default true,
  min_order_cents integer not null default 0 check (min_order_cents >= 0),
  eta_min_minutes integer not null default 30,
  eta_max_minutes integer not null default 50,
  pickup_enabled boolean not null default true,
  delivery_enabled boolean not null default true,
  payment_methods text[] not null default array['pix','cash','card_on_delivery'],
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint eta_order check (eta_max_minutes >= eta_min_minutes),
  constraint payment_methods_valid check (payment_methods <@ array['pix','cash','card_on_delivery']::text[])
);

create table users (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  email text not null unique,
  name text not null,
  password_hash text not null,
  role text not null default 'owner' check (role in ('owner','staff')),
  created_at timestamptz not null default now()
);
create index on users (store_id);

create table sessions (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references users(id) on delete cascade,
  token_hash text not null unique,
  expires_at timestamptz not null,
  created_at timestamptz not null default now()
);
create index on sessions (user_id);

create table categories (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  position integer not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);
create index on categories (store_id, position);

create table products (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  category_id uuid not null references categories(id) on delete cascade,
  name text not null,
  description text,
  image_url text,
  price_cents integer not null default 0 check (price_cents >= 0),
  compare_at_cents integer,
  badge text,
  is_available boolean not null default true,
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on products (category_id, position);
create index on products (store_id);

-- pricing: 'sum' soma cada opção; 'max' cobra só a opção mais cara (pizza meio a meio)
create table option_groups (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null references products(id) on delete cascade,
  name text not null,
  min_select smallint not null default 0,
  max_select smallint not null default 1,
  pricing text not null default 'sum' check (pricing in ('sum','max')),
  position integer not null default 0,
  constraint min_max check (min_select >= 0 and max_select >= 1 and max_select >= min_select)
);
create index on option_groups (product_id, position);

create table options (
  id uuid primary key default gen_random_uuid(),
  group_id uuid not null references option_groups(id) on delete cascade,
  name text not null,
  description text,
  price_cents integer not null default 0 check (price_cents >= 0),
  is_available boolean not null default true,
  position integer not null default 0
);
create index on options (group_id, position);

create table delivery_zones (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  fee_cents integer not null check (fee_cents >= 0),
  is_active boolean not null default true,
  position integer not null default 0
);
create index on delivery_zones (store_id, position);

create table couriers (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  phone text,
  access_token text not null unique,               -- o link secreto do motoboy
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);
create index on couriers (store_id);

create table store_counters (
  store_id uuid primary key references stores(id) on delete cascade,
  last_order_number integer not null default 1000
);

create table orders (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete restrict,
  number integer not null,
  public_token text not null unique,               -- link de acompanhamento do cliente
  customer_name text not null,
  customer_phone text not null,                    -- só dígitos, com DDI 55
  fulfillment text not null check (fulfillment in ('delivery','pickup')),
  address jsonb,
  zone_id uuid references delivery_zones(id) on delete set null,
  status text not null check (status in
    ('aguardando_pagamento','realizado','em_preparo','pronto','em_entrega','entregue','cancelado')),
  payment_method text not null check (payment_method in ('pix','cash','card_on_delivery')),
  payment_status text not null check (payment_status in ('pendente','aprovado','a_cobrar','cancelado')),
  change_for_cents integer,
  subtotal_cents integer not null check (subtotal_cents >= 0),
  delivery_fee_cents integer not null default 0 check (delivery_fee_cents >= 0),
  total_cents integer not null check (total_cents >= 0),
  notes text,
  courier_id uuid references couriers(id) on delete set null,
  idempotency_key text not null,
  placed_at timestamptz,
  preparing_at timestamptz,
  ready_at timestamptz,
  dispatched_at timestamptz,
  delivered_at timestamptz,
  canceled_at timestamptz,
  cancel_reason text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (store_id, number),
  unique (store_id, idempotency_key),
  constraint total_math check (total_cents = subtotal_cents + delivery_fee_cents),
  constraint delivery_has_address check (fulfillment = 'pickup' or address is not null)
);
create index on orders (store_id, status, created_at desc);
create index on orders (courier_id, status);
create index on orders (zone_id);

create table order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  product_id uuid references products(id) on delete set null,
  name text not null,
  unit_price_cents integer not null check (unit_price_cents >= 0),
  quantity smallint not null check (quantity between 1 and 99),
  notes text,
  position smallint not null default 0
);
create index on order_items (order_id);
create index on order_items (product_id);

create table order_item_options (
  id uuid primary key default gen_random_uuid(),
  order_item_id uuid not null references order_items(id) on delete cascade,
  group_name text not null,
  name text not null,
  price_cents integer not null default 0
);
create index on order_item_options (order_item_id);

create table order_events (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  from_status text,
  to_status text not null,
  actor text not null,                              -- 'cliente' | 'painel:<nome>' | 'motoboy:<nome>' | 'pagamento'
  created_at timestamptz not null default now()
);
create index on order_events (order_id, created_at);

create table payments (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  provider text not null,
  provider_payment_id text not null,
  status text not null check (status in ('pending','paid','expired','failed')),
  amount_cents integer not null,
  pix_payload text,
  pix_qr_data_url text,
  expires_at timestamptz,
  paid_at timestamptz,
  created_at timestamptz not null default now(),
  unique (provider, provider_payment_id)
);
create index on payments (order_id);

create table webhook_events (
  id uuid primary key default gen_random_uuid(),
  provider text not null,
  event_id text not null,
  payload jsonb not null,
  received_at timestamptz not null default now(),
  unique (provider, event_id)
);

-- fila e histórico das mensagens de WhatsApp de cada etapa
create table notifications (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  order_id uuid not null references orders(id) on delete cascade,
  stage text not null,                              -- realizado | em_preparo | pronto | em_entrega
  to_phone text not null,
  body text not null,
  mode text not null check (mode in ('api','manual')),
  status text not null check (status in ('pending','sent','failed')),
  provider_message_id text,
  error text,
  created_at timestamptz not null default now(),
  sent_at timestamptz,
  unique (order_id, stage)
);
create index on notifications (store_id, created_at desc);

-- última posição do motoboy em cada entrega (para o link de rastreio)
create table courier_positions (
  order_id uuid primary key references orders(id) on delete cascade,
  courier_id uuid not null references couriers(id) on delete cascade,
  lat double precision not null,
  lng double precision not null,
  accuracy_m double precision,
  updated_at timestamptz not null default now()
);
create index on courier_positions (courier_id);
