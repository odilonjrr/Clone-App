import "server-only";
import { HttpError, hashPassword } from "./auth";
import { tx, type Queryable } from "./db";
import { randomToken } from "./tokens";

type NewStore = {
  storeName: string;
  slug: string;
  ownerName: string;
  email: string;
  password: string;
  whatsapp?: string | null;
  withDemoMenu: boolean;
};

/** Cria a loja, o dono e (opcional) um cardápio de exemplo. Tudo numa transação. */
export async function createStoreWithOwner(input: NewStore) {
  const hash = await hashPassword(input.password);
  return tx(async (t) => {
    const taken = await t.query(`select 1 from stores where slug = $1`, [input.slug]);
    if (taken.length) throw new HttpError(409, "Esse endereço já está em uso. Escolha outro.");
    const used = await t.query(`select 1 from users where email = $1`, [input.email]);
    if (used.length) throw new HttpError(409, "Já existe uma conta com esse e-mail. Entre com ela.");
    const [store] = await t.query<{ id: string }>(
      `insert into stores (slug, name, whatsapp_phone, address_line, district, city, lat, lng) values ($1,$2,$3,$4,$5,$6,$7,$8) returning id`,
      [input.slug, input.storeName, input.whatsapp ?? null, input.withDemoMenu ? "Rua Domingos de Morais, 1200" : null,
       input.withDemoMenu ? "Vila Mariana" : null, input.withDemoMenu ? "São Paulo" : null,
       // ponto aproximado da Vila Mariana, só para a loja de exemplo aparecer no mapa
       input.withDemoMenu ? -23.5893 : null, input.withDemoMenu ? -46.6369 : null],
    );
    const [user] = await t.query<{ id: string }>(
      `insert into users (store_id, email, name, password_hash, role) values ($1,$2,$3,$4,'owner') returning id`,
      [store.id, input.email, input.ownerName, hash],
    );
    if (input.withDemoMenu) await seedDemoMenu(t, store.id);
    return { storeId: store.id, userId: user.id };
  });
}

async function seedDemoMenu(t: Queryable, storeId: string) {
  const cat = async (name: string, position: number) =>
    (await t.query<{ id: string }>(`insert into categories (store_id, name, position) values ($1,$2,$3) returning id`, [storeId, name, position]))[0].id;
  const prod = async (categoryId: string, p: { name: string; description?: string; price: number; compareAt?: number; image?: string; badge?: string; position: number }) =>
    (await t.query<{ id: string }>(
      `insert into products (store_id, category_id, name, description, price_cents, compare_at_cents, image_url, badge, position)
       values ($1,$2,$3,$4,$5,$6,$7,$8,$9) returning id`,
      [storeId, categoryId, p.name, p.description ?? null, p.price, p.compareAt ?? null, p.image ?? null, p.badge ?? null, p.position],
    ))[0].id;
  const group = async (productId: string, g: { name: string; min: number; max: number; pricing?: "sum" | "max"; position: number }, opts: [string, number, string?][]) => {
    const [row] = await t.query<{ id: string }>(
      `insert into option_groups (product_id, name, min_select, max_select, pricing, position) values ($1,$2,$3,$4,$5,$6) returning id`,
      [productId, g.name, g.min, g.max, g.pricing ?? "sum", g.position],
    );
    let i = 0;
    for (const [name, price, description] of opts)
      await t.query(`insert into options (group_id, name, price_cents, description, position) values ($1,$2,$3,$4,$5)`, [row.id, name, price, description ?? null, i++]);
  };

  const lanches = await cat("Lanches", 0);
  const pizzas = await cat("Pizzas", 1);
  const porcoes = await cat("Porções", 2);
  const bebidas = await cat("Bebidas", 3);

  const smash = await prod(lanches, { name: "Smash Clássico", description: "Pão brioche, dois smashs de 80 g, cheddar, cebola caramelizada e molho da casa.", price: 2990, image: "/demo/1f354.svg", badge: "favorito", position: 0 });
  await group(smash, { name: "Ponto da carne", min: 1, max: 1, position: 0 }, [["Ao ponto", 0], ["Bem passado", 0]]);
  await group(smash, { name: "Turbine seu lanche", min: 0, max: 3, position: 1 }, [["Bacon crocante", 500], ["Cheddar extra", 400], ["Ovo", 300]]);
  const duplo = await prod(lanches, { name: "Combo Smash em Dobro", description: "Dois Smash Clássicos, batata média e refrigerante lata.", price: 4990, compareAt: 6990, image: "/demo/1f354.svg", badge: "promo", position: 1 });
  await group(duplo, { name: "Refrigerante", min: 1, max: 1, position: 0 }, [["Coca-Cola", 0], ["Guaraná", 0], ["Coca-Cola Zero", 0]]);
  await prod(lanches, { name: "Frango Crocante", description: "Sobrecoxa empanada, alface, picles e maionese de alho.", price: 2790, image: "/demo/1f354.svg", position: 2 });

  const grande = await prod(pizzas, { name: "Pizza Grande (8 pedaços)", description: "Escolha 1 ou 2 sabores. Meio a meio cobra pelo sabor mais caro.", price: 0, image: "/demo/1f355.svg", position: 0 });
  await group(grande, { name: "Sabores", min: 1, max: 2, pricing: "max", position: 0 }, [
    ["Calabresa", 5490, "Calabresa fatiada, cebola e azeitona."],
    ["Margherita", 5290, "Muçarela, tomate e manjericão."],
    ["Frango com catupiry", 5890, "Frango desfiado e catupiry."],
    ["Quatro queijos", 6290, "Muçarela, provolone, parmesão e gorgonzola."],
  ]);
  await group(grande, { name: "Borda", min: 0, max: 1, position: 1 }, [["Catupiry", 900], ["Cheddar", 900]]);

  await prod(porcoes, { name: "Batata Rústica com Cheddar", description: "400 g, com cheddar cremoso e bacon.", price: 2290, image: "/demo/1f35f.svg", badge: "favorito", position: 0 });
  await prod(porcoes, { name: "Onion Rings", description: "12 anéis de cebola empanados, com molho barbecue.", price: 1990, image: "/demo/1f35f.svg", position: 1 });

  await prod(bebidas, { name: "Refrigerante lata", description: "350 ml.", price: 650, image: "/demo/1f964.svg", position: 0 });
  await prod(bebidas, { name: "Suco natural", description: "500 ml. Laranja ou limão.", price: 990, image: "/demo/1f964.svg", position: 1 });

  let i = 0;
  for (const [name, fee] of [["Vila Mariana", 500], ["Moema", 700], ["Saúde", 800], ["Ipiranga", 1000]] as const)
    await t.query(`insert into delivery_zones (store_id, name, fee_cents, position) values ($1,$2,$3,$4)`, [storeId, name, fee, i++]);

  await t.query(`insert into couriers (store_id, name, phone, access_token) values ($1,$2,$3,$4)`, [storeId, "Rafael (exemplo)", null, randomToken(18)]);
}
