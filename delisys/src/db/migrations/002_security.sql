-- Delisys v0.2: limite de tentativas persistente, recuperação de senha e retenção de dados.

-- Contador por janela de tempo. A chave guarda só o hash (sha256) do IP/e-mail, nunca o valor.
create table rate_limits (
  key_hash text not null,
  window_start timestamptz not null,
  hits integer not null default 1,
  primary key (key_hash, window_start)
);
create index on rate_limits (window_start);

-- Links de redefinição de senha: uso único, expiram em 30 minutos, só o hash é guardado.
create table password_resets (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references users(id) on delete cascade,
  token_hash text not null unique,
  expires_at timestamptz not null,
  used_at timestamptz,
  created_at timestamptz not null default now()
);
create index on password_resets (user_id);

-- apoio à limpeza periódica (retenção prevista na política de privacidade)
create index on sessions (expires_at);
create index on courier_positions (updated_at);
create index on orders (delivered_at);
