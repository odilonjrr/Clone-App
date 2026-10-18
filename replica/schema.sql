-- Schema inicial do clone (recorte de chatbots multicanal).
-- Postgres 15+ no Supabase. Todos os horários em timestamptz (UTC).
-- Acesso: Row Level Security por workspace. Os workers do servidor usam a
-- service role (ignora RLS) e precisam filtrar workspace_id explicitamente.

create extension if not exists pgcrypto;
create extension if not exists btree_gist;

-- ---------------------------------------------------------------------------
-- Helpers
-- ---------------------------------------------------------------------------

create or replace function set_updated_at() returns trigger
language plpgsql as $$
begin
  new.updated_at = now();
  return new;
end $$;

-- ---------------------------------------------------------------------------
-- Conta e equipe
-- ---------------------------------------------------------------------------

create table workspaces (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  default_timezone text not null default 'America/Sao_Paulo',
  plan text not null default 'trial'
    check (plan in ('trial','starter','pro','business')),
  stripe_customer_id text unique,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- Espelho de auth.users com dados de exibição.
create table profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text not null,
  full_name text,
  avatar_url text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table memberships (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  user_id uuid not null references profiles(id) on delete cascade,
  role text not null default 'agent' check (role in ('owner','admin','agent')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (workspace_id, user_id)
);
create index on memberships (user_id);

create or replace function is_member(ws uuid) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from memberships m
    where m.workspace_id = ws and m.user_id = auth.uid()
  )
$$;

create or replace function is_admin(ws uuid) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from memberships m
    where m.workspace_id = ws and m.user_id = auth.uid()
      and m.role in ('owner','admin')
  )
$$;

create table subscriptions (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null unique references workspaces(id) on delete cascade,
  stripe_subscription_id text not null unique,
  status text not null
    check (status in ('trialing','active','past_due','canceled','incomplete')),
  price_id text not null,
  current_period_end timestamptz not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- ---------------------------------------------------------------------------
-- Conhecimento
-- ---------------------------------------------------------------------------

create table knowledge_bases (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on knowledge_bases (workspace_id);

create table knowledge_sources (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  knowledge_base_id uuid not null references knowledge_bases(id) on delete cascade,
  kind text not null check (kind in ('website','file','text')),
  source_url text,                -- site ou caminho no storage
  title text,
  status text not null default 'pending'
    check (status in ('pending','processing','ready','failed')),
  error text,
  token_count integer not null default 0,
  last_synced_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on knowledge_sources (knowledge_base_id);
create index on knowledge_sources (workspace_id);

create table faq_items (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  knowledge_base_id uuid not null references knowledge_bases(id) on delete cascade,
  question text not null,
  answer text not null,
  position integer not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on faq_items (knowledge_base_id, position);
create index on faq_items (workspace_id);

-- Texto extraído e dividido. Busca por texto completo no v1; uma coluna de
-- embedding (pgvector) entra depois, se as bases crescerem.
create table knowledge_chunks (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  knowledge_base_id uuid not null references knowledge_bases(id) on delete cascade,
  source_id uuid not null references knowledge_sources(id) on delete cascade,
  content text not null,
  source_url text,
  token_count integer not null,
  search tsvector generated always as (to_tsvector('simple', content)) stored,
  created_at timestamptz not null default now()
);
create index on knowledge_chunks (knowledge_base_id);
create index on knowledge_chunks (source_id);
create index on knowledge_chunks (workspace_id);
create index on knowledge_chunks using gin (search);

-- ---------------------------------------------------------------------------
-- Agentes
-- ---------------------------------------------------------------------------

create table agents (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  is_active boolean not null default true,
  published_version_id uuid,      -- fk adicionada abaixo (referência circular)
  draft_version_id uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on agents (workspace_id);

-- Uma linha por versão. O rascunho é editado no lugar; publicar copia o
-- rascunho para uma nova linha 'published' imutável.
create table agent_versions (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  agent_id uuid not null references agents(id) on delete cascade,
  status text not null check (status in ('draft','published','archived')),
  instructions text not null default '',
  tone text not null default 'friendly'
    check (tone in ('friendly','professional','casual','enthusiastic','custom')),
  tone_custom text,
  primary_language text not null default 'pt-BR',
  playbook jsonb not null default '[]',      -- [{title, goal, questions[]}]
  always_on boolean not null default true,
  -- {"mon":[["09:00","18:00"]], ...} no fuso abaixo
  active_hours jsonb not null default '{}',
  timezone text not null default 'America/Sao_Paulo',
  -- {"enabled":true,"quick":{"count":1,"delays_minutes":[60]},
  --  "long":{"count":2,"delays_minutes":[1440,4320]}}
  follow_ups jsonb not null default '{"enabled": false}',
  -- {"booking":{"enabled":true,"calendar_connection_id":"...","duration_minutes":30},
  --  "web_search":{"enabled":false},"webhooks":[{"name","url","description","schema"}]}
  abilities jsonb not null default '{}',
  typing_speed text not null default 'natural'
    check (typing_speed in ('instant','fast','natural','slow')),
  split_messages boolean not null default true,
  ai_disclosure text,             -- aviso de IA na primeira mensagem
  published_at timestamptz,
  published_by uuid references profiles(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on agent_versions (agent_id, status);
create index on agent_versions (workspace_id);
create unique index one_draft_per_agent on agent_versions (agent_id)
  where status = 'draft';

alter table agents
  add constraint agents_published_fk foreign key (published_version_id)
    references agent_versions(id) on delete set null,
  add constraint agents_draft_fk foreign key (draft_version_id)
    references agent_versions(id) on delete set null;

-- Vínculos por versão, para que publicar congele também o conhecimento.
create table agent_version_knowledge (
  agent_version_id uuid not null references agent_versions(id) on delete cascade,
  knowledge_base_id uuid not null references knowledge_bases(id) on delete cascade,
  workspace_id uuid not null references workspaces(id) on delete cascade,
  primary key (agent_version_id, knowledge_base_id)
);
create index on agent_version_knowledge (knowledge_base_id);

create table agent_media (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  agent_version_id uuid not null references agent_versions(id) on delete cascade,
  name text not null,
  storage_path text not null,
  mime_type text not null,
  size_bytes integer not null check (size_bytes > 0),
  send_when text not null,        -- instrução em texto para a IA
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on agent_media (agent_version_id);
create index on agent_media (workspace_id);

create table tags (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  color text not null default '#64748b',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (workspace_id, name)
);

create table agent_tag_rules (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  agent_version_id uuid not null references agent_versions(id) on delete cascade,
  tag_id uuid not null references tags(id) on delete cascade,
  rule text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (agent_version_id, tag_id)
);
create index on agent_tag_rules (tag_id);
create index on agent_tag_rules (workspace_id);

-- ---------------------------------------------------------------------------
-- Canais e roteamento
-- ---------------------------------------------------------------------------

create table channels (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  kind text not null check (kind in ('web','telegram','whatsapp','instagram')),
  display_name text not null,
  status text not null default 'connected'
    check (status in ('connected','error','disconnected')),
  status_detail text,
  -- Tokens cifrados na aplicação (AES-256-GCM, chave fora do banco).
  credentials_encrypted bytea,
  -- web: public_key; telegram: bot_id, bot_username, webhook_secret;
  -- whatsapp: waba_id, phone_number_id; instagram: ig_user_id, page_id
  external_id text,               -- id da conta no provedor
  settings jsonb not null default '{}',
  connected_at timestamptz not null default now(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (kind, external_id)      -- a mesma conta não entra em dois workspaces
);
create index on channels (workspace_id);

create table widget_configs (
  channel_id uuid primary key references channels(id) on delete cascade,
  workspace_id uuid not null references workspaces(id) on delete cascade,
  public_key text not null unique default encode(gen_random_bytes(16), 'hex'),
  primary_color text not null default '#2563eb',
  theme text not null default 'light' check (theme in ('light','dark','auto')),
  position text not null default 'bottom-right'
    check (position in ('bottom-right','bottom-left')),
  font text not null default 'system',
  layout text not null default 'bubble' check (layout in ('bubble','inline')),
  welcome_message text,
  collect_fields text[] not null default '{}',  -- {'name','email','phone'}
  allowed_domains text[] not null default '{}', -- vazio = qualquer domínio
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on widget_configs (workspace_id);

create table entry_points (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  kind text not null check (kind in
    ('channel_default','keyword','ig_comment','ig_new_follower')),
  channel_id uuid not null references channels(id) on delete cascade,
  agent_id uuid not null references agents(id) on delete cascade,
  keyword text,                   -- keyword / ig_comment (vazio = qualquer)
  ig_media_id text,               -- ig_comment: post específico (null = todos)
  public_reply text,              -- ig_comment: resposta pública
  priority integer not null default 100,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint keyword_required check (kind <> 'keyword' or keyword is not null)
);
create index on entry_points (channel_id, is_active, priority);
create index on entry_points (agent_id);
create index on entry_points (workspace_id);
create unique index one_default_per_channel on entry_points (channel_id)
  where kind = 'channel_default';

-- ---------------------------------------------------------------------------
-- Contatos e conversas
-- ---------------------------------------------------------------------------

create table contacts (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text,
  email text,
  phone text,                     -- E.164
  timezone text,
  bot_active boolean not null default true,
  follow_ups_disabled boolean not null default false,
  agent_id uuid references agents(id) on delete set null,
  assigned_user_id uuid references profiles(id) on delete set null,
  custom_fields jsonb not null default '{}',
  last_message_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on contacts (workspace_id, last_message_at desc);
create index on contacts (workspace_id, email);
create index on contacts (workspace_id, phone);
create index on contacts (agent_id);
create index on contacts (assigned_user_id);

-- Um contato pode ter identidade em vários canais (uma linha do tempo).
create table contact_identities (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  contact_id uuid not null references contacts(id) on delete cascade,
  channel_id uuid not null references channels(id) on delete cascade,
  external_id text not null,      -- wa_id, ig scoped id, telegram chat id, visitor id
  username text,
  created_at timestamptz not null default now(),
  unique (channel_id, external_id)
);
create index on contact_identities (contact_id);
create index on contact_identities (workspace_id);

create table conversations (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  contact_id uuid not null references contacts(id) on delete cascade,
  channel_id uuid not null references channels(id) on delete cascade,
  last_message_at timestamptz,
  last_inbound_at timestamptz,    -- base da janela de 24 h (Meta)
  unread_count integer not null default 0 check (unread_count >= 0),
  pinned boolean not null default false,
  starred boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (contact_id, channel_id)
);
create index on conversations (workspace_id, last_message_at desc);
create index on conversations (channel_id);

create table messages (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  conversation_id uuid not null references conversations(id) on delete cascade,
  channel_id uuid not null references channels(id) on delete cascade,
  direction text not null check (direction in ('in','out','internal')),
  author text not null check (author in ('contact','ai','human','system')),
  author_user_id uuid references profiles(id) on delete set null,
  kind text not null default 'text'
    check (kind in ('text','audio','image','video','file','note','template')),
  body text,
  transcript text,                -- áudio transcrito
  media_path text,
  media_mime text,
  status text not null default 'pending'
    check (status in ('pending','scheduled','sent','delivered','read','failed')),
  error text,
  scheduled_for timestamptz,      -- retido fora do horário / atraso de digitação
  external_id text,               -- id da mensagem no provedor
  agent_version_id uuid references agent_versions(id) on delete set null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint note_is_internal check ((kind = 'note') = (direction = 'internal'))
);
create index on messages (conversation_id, created_at);
create index on messages (workspace_id);
create index on messages (status, scheduled_for) where status = 'scheduled';
-- Idempotência: o provedor reenvia webhooks.
create unique index messages_external_unique on messages (channel_id, external_id)
  where external_id is not null;

create table contact_tags (
  contact_id uuid not null references contacts(id) on delete cascade,
  tag_id uuid not null references tags(id) on delete cascade,
  workspace_id uuid not null references workspaces(id) on delete cascade,
  applied_by text not null check (applied_by in ('ai','human','automation')),
  created_at timestamptz not null default now(),
  primary key (contact_id, tag_id)
);
create index on contact_tags (tag_id);
create index on contact_tags (workspace_id);

create table follow_up_jobs (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  conversation_id uuid not null references conversations(id) on delete cascade,
  step integer not null check (step between 1 and 6),
  cycle text not null check (cycle in ('quick','long')),
  send_at timestamptz not null,
  status text not null default 'scheduled'
    check (status in ('scheduled','sent','cancelled','skipped')),
  reason text,                    -- por que foi cancelado/pulado
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on follow_up_jobs (conversation_id, status);
create index on follow_up_jobs (workspace_id);
create unique index one_pending_step on follow_up_jobs (conversation_id, cycle, step)
  where status = 'scheduled';

-- ---------------------------------------------------------------------------
-- Agenda
-- ---------------------------------------------------------------------------

create table calendar_connections (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  user_id uuid references profiles(id) on delete set null,
  provider text not null default 'google' check (provider in ('google')),
  account_email text not null,
  calendar_id text not null default 'primary',
  tokens_encrypted bytea not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on calendar_connections (workspace_id);

create table bookings (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  contact_id uuid not null references contacts(id) on delete cascade,
  conversation_id uuid references conversations(id) on delete set null,
  calendar_connection_id uuid not null references calendar_connections(id) on delete cascade,
  start_at timestamptz not null,
  end_at timestamptz not null,
  contact_timezone text not null,
  status text not null default 'confirmed'
    check (status in ('confirmed','cancelled')),
  external_event_id text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  constraint booking_positive check (end_at > start_at),
  -- Dois contatos não pegam o mesmo horário na mesma agenda.
  constraint no_double_booking exclude using gist (
    calendar_connection_id with =,
    tstzrange(start_at, end_at) with &&
  ) where (status = 'confirmed')
);
create index on bookings (workspace_id, start_at);
create index on bookings (contact_id);

-- ---------------------------------------------------------------------------
-- Automações e tarefas
-- ---------------------------------------------------------------------------

create table automations (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  trigger text not null check (trigger in ('tag_applied','conversation_started')),
  filters jsonb not null default '{}',   -- {"tag_id":..., "applied_by":"ai"}
  steps jsonb not null default '[]',     -- [{"type":"assign_agent","agent_id":...}]
  stop_on_reply boolean not null default false,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on automations (workspace_id, trigger) where is_active;

create table automation_runs (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  automation_id uuid not null references automations(id) on delete cascade,
  contact_id uuid not null references contacts(id) on delete cascade,
  status text not null default 'running'
    check (status in ('running','completed','stopped','failed')),
  error text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on automation_runs (automation_id);
create index on automation_runs (contact_id, status);
create index on automation_runs (workspace_id);

create table tasks (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  contact_id uuid references contacts(id) on delete cascade,
  assignee_id uuid references profiles(id) on delete set null,
  title text not null,
  due_at timestamptz,
  done boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index on tasks (workspace_id, done, due_at);
create index on tasks (contact_id);
create index on tasks (assignee_id);

-- ---------------------------------------------------------------------------
-- Operação
-- ---------------------------------------------------------------------------

-- Custo de cada chamada de IA, para limites de plano e margem.
create table usage_events (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  kind text not null check (kind in ('reply','tagging','transcription','ingest','playground')),
  model text not null,
  input_tokens integer not null default 0,
  cached_input_tokens integer not null default 0,
  output_tokens integer not null default 0,
  cost_micros bigint not null default 0,  -- US$ * 1e6
  currency text not null default 'USD',
  message_id uuid references messages(id) on delete set null,
  created_at timestamptz not null default now()
);
create index on usage_events (workspace_id, created_at);

-- Registro de webhooks recebidos: deduplicação e reprocessamento.
create table webhook_events (
  id uuid primary key default gen_random_uuid(),
  provider text not null check (provider in ('telegram','meta','stripe','google')),
  external_id text not null,
  payload jsonb not null,
  processed_at timestamptz,
  error text,
  created_at timestamptz not null default now(),
  unique (provider, external_id)
);
create index on webhook_events (processed_at) where processed_at is null;

-- ---------------------------------------------------------------------------
-- updated_at automático
-- ---------------------------------------------------------------------------

do $$
declare t text;
begin
  foreach t in array array[
    'workspaces','profiles','memberships','subscriptions','knowledge_bases',
    'knowledge_sources','faq_items','agents','agent_versions','agent_media',
    'tags','agent_tag_rules','channels','widget_configs','entry_points',
    'contacts','conversations','messages','follow_up_jobs',
    'calendar_connections','bookings','automations','automation_runs','tasks'
  ] loop
    execute format(
      'create trigger %I before update on %I for each row execute function set_updated_at()',
      t || '_updated_at', t);
  end loop;
end $$;

-- ---------------------------------------------------------------------------
-- Row Level Security
-- ---------------------------------------------------------------------------

alter table workspaces enable row level security;
create policy ws_read on workspaces for select using (is_member(id));
create policy ws_update on workspaces for update using (is_admin(id));

alter table profiles enable row level security;
create policy profile_self on profiles for all
  using (id = auth.uid()) with check (id = auth.uid());
create policy profile_teammates on profiles for select using (
  exists (select 1 from memberships m
          where m.user_id = profiles.id and is_member(m.workspace_id)));

alter table memberships enable row level security;
create policy mem_read on memberships for select using (is_member(workspace_id));
create policy mem_admin on memberships for all
  using (is_admin(workspace_id)) with check (is_admin(workspace_id));

alter table subscriptions enable row level security;
create policy sub_read on subscriptions for select using (is_member(workspace_id));
-- escrita só pelo webhook do Stripe (service role)

-- Tabelas de configuração: membros leem, admins escrevem.
do $$
declare t text;
begin
  foreach t in array array[
    'knowledge_bases','knowledge_sources','faq_items','agents','agent_versions',
    'agent_version_knowledge','agent_media','tags','agent_tag_rules','channels',
    'widget_configs','entry_points','calendar_connections','automations'
  ] loop
    execute format('alter table %I enable row level security', t);
    execute format(
      'create policy %I on %I for select using (is_member(workspace_id))',
      t || '_read', t);
    execute format(
      'create policy %I on %I for all using (is_admin(workspace_id)) with check (is_admin(workspace_id))',
      t || '_write', t);
  end loop;
end $$;

-- Tabelas operacionais: qualquer membro lê e escreve (atendentes respondem,
-- marcam tags, pausam a IA).
do $$
declare t text;
begin
  foreach t in array array[
    'contacts','contact_identities','conversations','messages','contact_tags',
    'follow_up_jobs','bookings','tasks'
  ] loop
    execute format('alter table %I enable row level security', t);
    execute format(
      'create policy %I on %I for all using (is_member(workspace_id)) with check (is_member(workspace_id))',
      t || '_member', t);
  end loop;
end $$;

-- Só leitura para membros; escrita pelo servidor.
do $$
declare t text;
begin
  foreach t in array array['knowledge_chunks','automation_runs','usage_events'] loop
    execute format('alter table %I enable row level security', t);
    execute format(
      'create policy %I on %I for select using (is_member(workspace_id))',
      t || '_read', t);
  end loop;
end $$;

-- webhook_events: sem política = só a service role acessa.
alter table webhook_events enable row level security;
