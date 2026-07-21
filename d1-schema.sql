CREATE TABLE IF NOT EXISTS leads (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nome TEXT NOT NULL,
  empresa TEXT NOT NULL,
  cargo TEXT,
  email TEXT NOT NULL,
  telefone TEXT NOT NULL,
  faturamento TEXT,
  regime TEXT,
  desafio TEXT,
  origem TEXT NOT NULL DEFAULT 'site',
  pagina_origem TEXT,
  utm_source TEXT,
  utm_medium TEXT,
  utm_campaign TEXT,
  utm_term TEXT,
  utm_content TEXT,
  consentimento_marketing INTEGER NOT NULL DEFAULT 0,
  consentimento_em TEXT,
  ip_hash TEXT,
  status TEXT NOT NULL DEFAULT 'novo',
  criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_leads_criado_em ON leads (criado_em);
CREATE INDEX IF NOT EXISTS idx_leads_email ON leads (email);
