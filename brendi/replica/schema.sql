-- Esquema do clone (cardápio digital + gestor de pedidos), Postgres 15+ / Supabase.
-- Base: brendi/replica/recon.md (rev. 2) e brendi/replica/architecture.md.
--
-- Convenções
--   * ids uuid, created_at/updated_at em timestamptz (UTC).
--   * dinheiro em centavos (integer) + currency 'BRL'.
--   * multi-tenant: organizations (a empresa cliente) 1-n stores (cada loja/unidade).
--     Tudo que é da loja carrega store_id; o acesso do painel é por org_members.
--   * acesso: RLS do Supabase. O painel usa a sessão do usuário (auth.uid()).
--     O cardápio público e o checkout rodam no servidor (Route Handlers) com a
--     service role e fazem a checagem de loja publicada no código; o cliente
--     final NÃO é usuário do Supabase Auth (login por celular próprio, por loja).

-- gen_random_uuid() é nativo do Postgres 13+; nenhuma extensão necessária.

-- ───────────────────────── utilitário ─────────────────────────
create or replace function set_updated_at() returns trigger
language plpgsql as $$
begin new.updated_at = now(); return new; end $$;

-- ───────────────────────── tenant e equipe ─────────────────────────
create table organizations (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  document text,                                  -- CNPJ/CPF para cobrança
  plan_status text not null default 'trialing'
    check (plan_status in ('trialing','active','past_due','canceled')),
  trial_ends_at timestamptz not null default now() + interval '15 days',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table org_members (
  org_id uuid not null references organizations(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  role text not null default 'staff' check (role in ('owner','manager','staff')),
  created_at timestamptz not null default now(),
  primary key (org_id, user_id)
);
create index on org_members (user_id);

-- ───────────────────────── loja ─────────────────────────
create table stores (
  id uuid primary key default gen_random_uuid(),
  org_id uuid not null references organizations(id) on delete cascade,
  slug text not null unique check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
  name text not null,
  logo_path text,
  cover_path text,
  whatsapp_phone text,                             -- E.164
  timezone text not null default 'America/Sao_Paulo',
  -- endereço (retirada e origem do raio de entrega)
  street text, number text, district text, city text, state char(2), cep text,
  lat double precision, lng double precision,
  -- regras de pedido
  min_order_cents integer not null default 0 check (min_order_cents >= 0),
  eta_min_minutes integer not null default 20,
  eta_max_minutes integer not null default 60,
  high_demand_extra_minutes integer not null default 0,
  pickup_enabled boolean not null default true,
  delivery_enabled boolean not null default true,
  scheduling_enabled boolean not null default false,
  paused_until timestamptz,                        -- "Pausar loja"
  accepted_payment_methods text[] not null
    default array['pix','credit_online','cash','credit_on_delivery','debit_on_delivery'],
  cashback_percent numeric(5,2) not null default 0 check (cashback_percent between 0 and 100),
  cashback_valid_days integer not null default 40,
  is_published boolean not null default false,
  currency char(3) not null default 'BRL',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint eta_order check (eta_max_minutes >= eta_min_minutes),
  constraint payment_methods_valid check (accepted_payment_methods <@ array[
    'pix','credit_online','apple_pay','google_pay',
    'cash','credit_on_delivery','debit_on_delivery','meal_voucher']::text[])
);
create index on stores (org_id);

-- contador do código do pedido ("B-5018") por loja, sem corrida
create table store_counters (
  store_id uuid primary key references stores(id) on delete cascade,
  last_order_number integer not null default 1000
);

create table store_hours (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  weekday smallint not null check (weekday between 0 and 6),   -- 0 = domingo
  opens_at time not null,
  closes_at time not null,                          -- se < opens_at, vira a meia-noite
  created_at timestamptz not null default now(),
  constraint not_zero check (opens_at <> closes_at)
);
create index on store_hours (store_id, weekday);

-- exceções: feriado fechado, horário especial
create table store_hour_exceptions (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  day date not null,
  closed boolean not null default true,
  opens_at time, closes_at time,
  unique (store_id, day)
);

create table delivery_zones (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  kind text not null check (kind in ('radius','district','polygon')),
  max_km numeric(6,2),                               -- kind = radius
  districts text[],                                  -- kind = district (bairros)
  polygon jsonb,                                     -- kind = polygon (GeoJSON)
  fee_cents integer not null check (fee_cents >= 0),
  eta_extra_minutes integer not null default 0,
  min_order_cents integer,
  position integer not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint zone_shape check (
    (kind = 'radius'   and max_km is not null) or
    (kind = 'district' and districts is not null) or
    (kind = 'polygon'  and polygon is not null))
);
create index on delivery_zones (store_id, position);

-- ───────────────────────── cardápio ─────────────────────────
create table categories (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  position integer not null default 0,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on categories (store_id, position);

create table products (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  category_id uuid not null references categories(id) on delete cascade,
  kind text not null default 'simple' check (kind in ('simple','pizza_size')),
  slug text not null,
  name text not null,
  description text,
  image_path text,
  price_cents integer not null default 0 check (price_cents >= 0),
  compare_at_cents integer check (compare_at_cents is null or compare_at_cents > price_cents), -- "De/por"
  serves smallint,
  unit_label text,                                  -- "3un", "500g"
  badge text check (badge in ('recommended','favorite','new')),
  -- pizza_size
  slices smallint,
  max_flavors smallint check (max_flavors between 1 and 4),
  flavor_pricing text check (flavor_pricing in ('max','average')),
  is_available boolean not null default true,       -- false = "Em falta"
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (store_id, slug),
  constraint pizza_fields check (
    kind = 'simple' or (max_flavors is not null and flavor_pricing is not null))
);
create index on products (category_id, position);
create index on products (store_id);

create table option_groups (
  id uuid primary key default gen_random_uuid(),
  product_id uuid not null references products(id) on delete cascade,
  name text not null,
  min_select smallint not null default 0,
  max_select smallint not null default 1,
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint min_max check (min_select >= 0 and max_select >= 1 and max_select >= min_select)
);
create index on option_groups (product_id, position);

create table options (
  id uuid primary key default gen_random_uuid(),
  group_id uuid not null references option_groups(id) on delete cascade,
  name text not null,
  description text,
  price_cents integer not null default 0 check (price_cents >= 0),
  max_qty smallint not null default 1 check (max_qty >= 1),
  is_available boolean not null default true,
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on options (group_id, position);

create table flavors (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  description text,
  image_path text,
  is_available boolean not null default true,
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on flavors (store_id, position);

-- preço da pizza INTEIRA daquele sabor naquele tamanho
create table flavor_prices (
  flavor_id uuid not null references flavors(id) on delete cascade,
  product_id uuid not null references products(id) on delete cascade,  -- o tamanho
  price_cents integer not null check (price_cents >= 0),
  primary key (flavor_id, product_id)
);
create index on flavor_prices (product_id);

-- ───────────────────────── cliente final (por loja) ─────────────────────────
create table customers (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  phone text not null,                              -- E.164, é o login
  name text,
  phone_verified_at timestamptz,
  marketing_opt_out boolean not null default false,
  order_count integer not null default 0,
  total_spent_cents bigint not null default 0,
  last_order_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (store_id, phone)
);
create index on customers (store_id, last_order_at);

create table customer_addresses (
  id uuid primary key default gen_random_uuid(),
  customer_id uuid not null references customers(id) on delete cascade,
  formatted text not null,
  street text, number text, complement text, reference text,
  district text, city text, state char(2), cep text,
  lat double precision not null, lng double precision not null,
  google_place_id text,
  created_at timestamptz not null default now()
);
create index on customer_addresses (customer_id);

create table customer_otps (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  phone text not null,
  code_hash text not null,
  attempts smallint not null default 0,
  expires_at timestamptz not null,
  consumed_at timestamptz,
  created_at timestamptz not null default now()
);
create index on customer_otps (store_id, phone, created_at desc);

create table customer_sessions (
  id uuid primary key default gen_random_uuid(),
  customer_id uuid not null references customers(id) on delete cascade,
  token_hash text not null unique,
  expires_at timestamptz not null,
  created_at timestamptz not null default now()
);
create index on customer_sessions (customer_id);

-- sacola guardada no servidor: permite o recuperador de carrinho abandonado
create table carts (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  customer_id uuid references customers(id) on delete set null,
  items jsonb not null default '[]',
  subtotal_cents integer not null default 0,
  converted_order_id uuid,
  recovered_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on carts (store_id, updated_at) where converted_order_id is null;

-- ───────────────────────── marketing ─────────────────────────
create table coupons (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  code text,                                        -- null = cupom automático do card
  kind text not null check (kind in ('percent','amount','free_delivery')),
  value integer not null check (value >= 0),        -- % inteiro ou centavos
  min_order_cents integer not null default 0,
  weekly_limit integer,                             -- null = sem limite
  per_customer_limit integer,
  first_order_only boolean not null default false,
  show_on_menu boolean not null default false,
  starts_at timestamptz, ends_at timestamptz,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint percent_range check (kind <> 'percent' or value between 1 and 100)
);
create unique index coupons_code_per_store on coupons (store_id, lower(code)) where code is not null;
create index on coupons (store_id, is_active);

-- ───────────────────────── entrega ─────────────────────────
create table couriers (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  name text not null,
  phone text,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on couriers (store_id);

-- ───────────────────────── pedidos ─────────────────────────
create table orders (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete restrict,
  number integer not null,                          -- exibido como "B-5018"
  customer_id uuid references customers(id) on delete set null,
  channel text not null default 'menu' check (channel in ('menu','whatsapp','manual','marketplace')),
  fulfillment text not null check (fulfillment in ('delivery','pickup')),
  scheduled_for timestamptz,
  customer_name text not null,
  customer_phone text not null,
  address jsonb,                                    -- cópia do endereço no momento do pedido
  zone_id uuid references delivery_zones(id) on delete set null,
  status text not null default 'em_analise' check (status in
    ('aguardando_pagamento','em_analise','em_preparo','pronto','em_entrega','finalizado','recusado','cancelado')),
  payment_method text not null check (payment_method in
    ('pix','credit_online','apple_pay','google_pay','cash','credit_on_delivery','debit_on_delivery','meal_voucher')),
  payment_status text not null check (payment_status in ('pendente','aprovado','a_cobrar','estornado','falhou')),
  change_for_cents integer,                         -- troco para
  subtotal_cents integer not null check (subtotal_cents >= 0),
  delivery_fee_cents integer not null default 0 check (delivery_fee_cents >= 0),
  discount_cents integer not null default 0 check (discount_cents >= 0),
  cashback_used_cents integer not null default 0 check (cashback_used_cents >= 0),
  total_cents integer not null check (total_cents >= 0),
  cashback_earned_cents integer not null default 0,
  currency char(3) not null default 'BRL',
  coupon_id uuid references coupons(id) on delete set null,
  courier_id uuid references couriers(id) on delete set null,
  notes text,
  refuse_reason text,
  idempotency_key text not null,                    -- gerado no navegador por tentativa de checkout
  accepted_at timestamptz, ready_at timestamptz, dispatched_at timestamptz,
  finished_at timestamptz, canceled_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (store_id, number),
  unique (store_id, idempotency_key),
  constraint total_math check (
    total_cents = subtotal_cents + delivery_fee_cents - discount_cents - cashback_used_cents),
  constraint delivery_has_address check (fulfillment = 'pickup' or address is not null)
);
create index on orders (store_id, status, created_at desc);   -- o quadro
create index on orders (store_id, created_at desc);           -- relatórios
create index on orders (customer_id, created_at desc);
create index on orders (coupon_id);
create index on orders (courier_id);
create index on orders (zone_id);

create table order_items (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  product_id uuid references products(id) on delete set null,
  name text not null,                               -- cópia
  unit_price_cents integer not null check (unit_price_cents >= 0),  -- já com opções/sabores
  quantity smallint not null check (quantity >= 1),
  notes text,
  position smallint not null default 0
);
create index on order_items (order_id);
create index on order_items (product_id);

create table order_item_options (
  id uuid primary key default gen_random_uuid(),
  order_item_id uuid not null references order_items(id) on delete cascade,
  kind text not null check (kind in ('option','flavor')),
  option_id uuid references options(id) on delete set null,
  flavor_id uuid references flavors(id) on delete set null,
  group_name text,
  name text not null,
  price_cents integer not null default 0,
  quantity smallint not null default 1,
  fraction numeric(4,3)                              -- 0.5 = meia, 0.333 = um terço
);
create index on order_item_options (order_item_id);
create index on order_item_options (option_id);
create index on order_item_options (flavor_id);

create table order_events (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  from_status text, to_status text not null,
  actor_user_id uuid references auth.users(id) on delete set null,
  note text,
  created_at timestamptz not null default now()
);
create index on order_events (order_id, created_at);

-- carts.converted_order_id só existe depois de orders
alter table carts add constraint carts_order_fk
  foreign key (converted_order_id) references orders(id) on delete set null;

create table coupon_redemptions (
  id uuid primary key default gen_random_uuid(),
  coupon_id uuid not null references coupons(id) on delete cascade,
  order_id uuid not null unique references orders(id) on delete cascade,
  customer_id uuid references customers(id) on delete set null,
  week_start date not null,                         -- segunda-feira, no fuso da loja
  created_at timestamptz not null default now()
);
create index on coupon_redemptions (coupon_id, week_start);
create index on coupon_redemptions (customer_id);

-- livro-razão do cashback: saldo = soma dos lançamentos válidos
create table cashback_entries (
  id uuid primary key default gen_random_uuid(),
  customer_id uuid not null references customers(id) on delete cascade,
  order_id uuid references orders(id) on delete set null,
  kind text not null check (kind in ('earn','spend','expire','adjust')),
  amount_cents integer not null,                    -- + ganho, − uso/expiração
  expires_at timestamptz,                           -- só para earn
  created_at timestamptz not null default now(),
  constraint sign_by_kind check (
    (kind = 'earn' and amount_cents > 0 and expires_at is not null) or
    (kind in ('spend','expire') and amount_cents < 0) or kind = 'adjust')
);
create index on cashback_entries (customer_id, created_at);
create index on cashback_entries (order_id);
create index on cashback_entries (expires_at) where kind = 'earn';

-- ───────────────────────── pagamentos ─────────────────────────
create table payments (
  id uuid primary key default gen_random_uuid(),
  order_id uuid not null references orders(id) on delete cascade,
  provider text not null,                           -- 'asaas' | 'pagarme' | ...
  provider_payment_id text not null,
  method text not null,
  status text not null check (status in ('pending','paid','expired','refunded','failed')),
  amount_cents integer not null,
  fee_cents integer,
  pix_qr_code text, pix_copy_paste text,
  expires_at timestamptz,
  paid_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (provider, provider_payment_id)
);
create index on payments (order_id);

-- toda notificação externa entra aqui primeiro: dedup por (provider, event_id)
create table webhook_events (
  id uuid primary key default gen_random_uuid(),
  provider text not null,                           -- 'asaas' | 'whatsapp' | ...
  event_id text not null,
  payload jsonb not null,
  processed_at timestamptz,
  error text,
  received_at timestamptz not null default now(),
  unique (provider, event_id)
);

-- ───────────────────────── recompra e WhatsApp ─────────────────────────
create table campaigns (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  kind text not null check (kind in ('inactive','abandoned_cart','broadcast','repurchase')),
  name text not null,
  rules jsonb not null default '{}',                -- ex.: {"inactive_days":21}
  whatsapp_template text,                           -- modelo aprovado pela Meta
  coupon_id uuid references coupons(id) on delete set null,
  is_active boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on campaigns (store_id, kind);
create index on campaigns (coupon_id);

create table campaign_sends (
  id uuid primary key default gen_random_uuid(),
  campaign_id uuid not null references campaigns(id) on delete cascade,
  customer_id uuid not null references customers(id) on delete cascade,
  cart_id uuid references carts(id) on delete set null,
  sent_at timestamptz not null default now(),
  converted_order_id uuid references orders(id) on delete set null,
  provider_message_id text
);
create index on campaign_sends (campaign_id, sent_at);
create index on campaign_sends (customer_id, sent_at);
create index on campaign_sends (cart_id);
create index on campaign_sends (converted_order_id);

create table whatsapp_accounts (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null unique references stores(id) on delete cascade,
  waba_id text not null,
  phone_number_id text not null unique,
  display_phone text not null,
  access_token_secret_id uuid,                      -- referência ao Supabase Vault, nunca o token em texto
  status text not null default 'connected' check (status in ('connected','disconnected')),
  ai_enabled boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table conversations (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  customer_id uuid references customers(id) on delete set null,
  wa_contact_phone text not null,
  ai_paused boolean not null default false,        -- humano assumiu
  last_message_at timestamptz,
  last_inbound_at timestamptz,                      -- janela de 24 h da Meta
  created_at timestamptz not null default now(),
  unique (store_id, wa_contact_phone)
);
create index on conversations (store_id, last_message_at desc);
create index on conversations (customer_id);

create table messages (
  id uuid primary key default gen_random_uuid(),
  conversation_id uuid not null references conversations(id) on delete cascade,
  direction text not null check (direction in ('in','out')),
  author text not null check (author in ('customer','ai','staff','system')),
  type text not null check (type in ('text','audio','image','sticker','template','interactive')),
  body text,
  media_path text,
  provider_message_id text unique,
  status text check (status in ('sent','delivered','read','failed')),
  created_at timestamptz not null default now()
);
create index on messages (conversation_id, created_at);

create table integrations (
  id uuid primary key default gen_random_uuid(),
  store_id uuid not null references stores(id) on delete cascade,
  provider text not null check (provider in ('meta_pixel','google_analytics','gtm')),
  config jsonb not null default '{}',               -- só ids públicos (pixel id, GA id)
  created_at timestamptz not null default now(),
  unique (store_id, provider)
);

-- ───────────────────────── cobrança do SaaS ─────────────────────────
create table subscriptions (
  id uuid primary key default gen_random_uuid(),
  org_id uuid not null unique references organizations(id) on delete cascade,
  provider text not null,
  provider_customer_id text,
  provider_subscription_id text unique,
  revenue_band text not null default 'ate_3k',      -- faixa de faturamento
  price_cents integer not null,
  status text not null check (status in ('trialing','active','past_due','canceled')),
  current_period_end timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- ───────────────────────── triggers updated_at ─────────────────────────
do $$
declare t text;
begin
  foreach t in array array['organizations','stores','delivery_zones','categories','products',
    'option_groups','options','flavors','customers','carts','coupons','couriers','orders',
    'payments','campaigns','whatsapp_accounts','subscriptions'] loop
    execute format('create trigger %I_updated before update on %I
                    for each row execute function set_updated_at()', t, t);
  end loop;
end $$;

-- ───────────────────────── funções de domínio ─────────────────────────
-- próximo número de pedido da loja, atômico (UPDATE ... RETURNING trava a linha)
create or replace function next_order_number(p_store uuid) returns integer
language sql as $$
  insert into store_counters (store_id) values (p_store)
  on conflict (store_id) do update set last_order_number = store_counters.last_order_number + 1
  returning last_order_number;
$$;

create or replace function cashback_balance(p_customer uuid) returns integer
language sql stable as $$
  select coalesce(sum(amount_cents), 0)::integer from cashback_entries where customer_id = p_customer;
$$;

-- ───────────────────────── RLS (painel) ─────────────────────────
create or replace function is_store_member(p_store uuid) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from stores s join org_members m on m.org_id = s.org_id
    where s.id = p_store and m.user_id = auth.uid());
$$;

create or replace function is_org_member(p_org uuid) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from org_members where org_id = p_org and user_id = auth.uid());
$$;

alter table organizations enable row level security;
create policy org_read on organizations for select using (is_org_member(id));
create policy org_write on organizations for update using (is_org_member(id));

alter table org_members enable row level security;
create policy members_read on org_members for select using (is_org_member(org_id));

alter table stores enable row level security;
create policy stores_rw on stores for all using (is_org_member(org_id)) with check (is_org_member(org_id));

-- tabelas com store_id direto: membro da loja lê e escreve
do $$
declare t text;
begin
  foreach t in array array['store_hours','store_hour_exceptions','delivery_zones','categories',
    'products','flavors','customers','carts','coupons','couriers','orders','campaigns',
    'whatsapp_accounts','conversations','integrations','customer_otps'] loop
    execute format('alter table %I enable row level security', t);
    execute format('create policy %I_member on %I for all
                    using (is_store_member(store_id)) with check (is_store_member(store_id))', t, t);
  end loop;
end $$;

-- tabelas filhas: acesso pelo pai
alter table option_groups enable row level security;
create policy og_member on option_groups for all
  using (is_store_member((select store_id from products p where p.id = product_id)))
  with check (is_store_member((select store_id from products p where p.id = product_id)));

alter table options enable row level security;
create policy opt_member on options for all
  using (is_store_member((select p.store_id from option_groups g join products p on p.id = g.product_id where g.id = group_id)))
  with check (is_store_member((select p.store_id from option_groups g join products p on p.id = g.product_id where g.id = group_id)));

alter table flavor_prices enable row level security;
create policy fp_member on flavor_prices for all
  using (is_store_member((select store_id from flavors f where f.id = flavor_id)))
  with check (is_store_member((select store_id from flavors f where f.id = flavor_id)));

alter table order_items enable row level security;
create policy oi_member on order_items for select
  using (is_store_member((select store_id from orders o where o.id = order_id)));

alter table order_item_options enable row level security;
create policy oio_member on order_item_options for select
  using (is_store_member((select o.store_id from order_items i join orders o on o.id = i.order_id where i.id = order_item_id)));

alter table order_events enable row level security;
create policy oe_member on order_events for select
  using (is_store_member((select store_id from orders o where o.id = order_id)));

alter table payments enable row level security;
create policy pay_member on payments for select
  using (is_store_member((select store_id from orders o where o.id = order_id)));

alter table coupon_redemptions enable row level security;
create policy cr_member on coupon_redemptions for select
  using (is_store_member((select store_id from coupons c where c.id = coupon_id)));

alter table cashback_entries enable row level security;
create policy cb_member on cashback_entries for select
  using (is_store_member((select store_id from customers c where c.id = customer_id)));

alter table customer_addresses enable row level security;
create policy ca_member on customer_addresses for select
  using (is_store_member((select store_id from customers c where c.id = customer_id)));

alter table campaign_sends enable row level security;
create policy cs_member on campaign_sends for select
  using (is_store_member((select store_id from campaigns c where c.id = campaign_id)));

alter table messages enable row level security;
create policy msg_member on messages for all
  using (is_store_member((select store_id from conversations c where c.id = conversation_id)))
  with check (is_store_member((select store_id from conversations c where c.id = conversation_id)));

alter table subscriptions enable row level security;
create policy sub_read on subscriptions for select using (is_org_member(org_id));

-- sem política = só a service role: customer_sessions, store_counters, webhook_events
alter table customer_sessions enable row level security;
alter table store_counters enable row level security;
alter table webhook_events enable row level security;

-- tempo real do quadro de pedidos
-- (no Supabase: alter publication supabase_realtime add table orders;)
