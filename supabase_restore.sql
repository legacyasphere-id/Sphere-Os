-- ============================================================
-- SphereOS — Full Database Restore Script
-- Run this in Supabase SQL Editor (supabase.com → your project → SQL Editor)
-- ============================================================

-- Laravel migrations tracking table
CREATE TABLE IF NOT EXISTS migrations (
    id serial PRIMARY KEY,
    migration varchar(255) NOT NULL,
    batch integer NOT NULL
);

-- --------------------------------------------------------
-- CORE: users, sessions, password_reset_tokens
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id bigserial PRIMARY KEY,
    name varchar(255) NOT NULL,
    email varchar(255) NOT NULL UNIQUE,
    email_verified_at timestamp(0) NULL,
    password varchar(255) NOT NULL,
    remember_token varchar(100) NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email varchar(255) PRIMARY KEY,
    token varchar(255) NOT NULL,
    created_at timestamp(0) NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id varchar(255) PRIMARY KEY,
    user_id bigint NULL,
    ip_address varchar(45) NULL,
    user_agent text NULL,
    payload text NOT NULL,
    last_activity integer NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions(user_id);
CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions(last_activity);

-- --------------------------------------------------------
-- CACHE
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS cache (
    key varchar(255) PRIMARY KEY,
    value text NOT NULL,
    expiration bigint NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache(expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key varchar(255) PRIMARY KEY,
    owner varchar(255) NOT NULL,
    expiration bigint NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks(expiration);

-- --------------------------------------------------------
-- JOBS / QUEUES
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS jobs (
    id bigserial PRIMARY KEY,
    queue varchar(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer NULL,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);
CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs(queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id varchar(255) PRIMARY KEY,
    name varchar(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text NULL,
    cancelled_at integer NULL,
    created_at integer NOT NULL,
    finished_at integer NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id bigserial PRIMARY KEY,
    uuid varchar(255) NOT NULL UNIQUE,
    connection varchar(255) NOT NULL,
    queue varchar(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS failed_jobs_connection_queue_failed_at_index ON failed_jobs(connection, queue, failed_at);

-- --------------------------------------------------------
-- SANCTUM AUTH TOKENS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id bigserial PRIMARY KEY,
    tokenable_type varchar(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token varchar(64) NOT NULL UNIQUE,
    abilities text NULL,
    last_used_at timestamp(0) NULL,
    expires_at timestamp(0) NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS personal_access_tokens_tokenable_type_tokenable_id_index ON personal_access_tokens(tokenable_type, tokenable_id);
CREATE INDEX IF NOT EXISTS personal_access_tokens_expires_at_index ON personal_access_tokens(expires_at);

-- --------------------------------------------------------
-- CRM: clients
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name varchar(255) NOT NULL,
    company varchar(255) NULL,
    email varchar(255) NULL,
    phone varchar(255) NULL,
    status varchar(20) NOT NULL DEFAULT 'lead' CHECK (status IN ('lead', 'prospect', 'client', 'lost')),
    next_action_date date NULL,
    next_action_note varchar(255) NULL,
    notes text NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS clients_user_id_status_index ON clients(user_id, status);

-- --------------------------------------------------------
-- PROJECTS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NULL REFERENCES clients(id) ON DELETE SET NULL,
    name varchar(255) NOT NULL,
    description text NULL,
    ai_summary text NULL,
    ai_summary_at timestamp(0) NULL,
    status varchar(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'completed', 'archived')),
    due_date date NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS projects_user_id_status_index ON projects(user_id, status);

-- --------------------------------------------------------
-- CONTACTS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    name varchar(255) NOT NULL,
    email varchar(255) NULL,
    phone varchar(255) NULL,
    role varchar(255) NULL,
    notes text NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS contacts_user_id_client_id_index ON contacts(user_id, client_id);

-- --------------------------------------------------------
-- TASKS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    project_id bigint NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    title varchar(255) NOT NULL,
    description text NULL,
    status varchar(20) NOT NULL DEFAULT 'todo' CHECK (status IN ('todo', 'in_progress', 'done')),
    priority varchar(10) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
    due_date date NULL,
    ai_generated boolean NOT NULL DEFAULT false,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS tasks_user_id_status_index ON tasks(user_id, status);
CREATE INDEX IF NOT EXISTS tasks_project_id_status_index ON tasks(project_id, status);

-- --------------------------------------------------------
-- AI: prompts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_prompts (
    id bigserial PRIMARY KEY,
    name varchar(100) NOT NULL UNIQUE,
    version integer NOT NULL DEFAULT 1,
    task_type varchar(50) NOT NULL,
    model_hint varchar(100) NULL,
    system_message text NOT NULL,
    user_template text NOT NULL,
    output_format varchar(20) NOT NULL DEFAULT 'json' CHECK (output_format IN ('json', 'markdown', 'text')),
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS ai_prompts_task_type_is_active_index ON ai_prompts(task_type, is_active);

-- --------------------------------------------------------
-- AI: logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_logs (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    task_type varchar(50) NOT NULL,
    resource_type varchar(50) NULL,
    resource_id bigint NULL,
    prompt_id bigint NULL REFERENCES ai_prompts(id) ON DELETE SET NULL,
    model varchar(100) NOT NULL,
    prompt_tokens integer NOT NULL DEFAULT 0,
    completion_tokens integer NOT NULL DEFAULT 0,
    total_tokens integer NOT NULL DEFAULT 0,
    cost_usd decimal(10,6) NOT NULL DEFAULT 0,
    latency_ms integer NOT NULL DEFAULT 0,
    status varchar(10) NOT NULL DEFAULT 'success' CHECK (status IN ('success', 'error', 'timeout')),
    error_message text NULL,
    output_preview varchar(500) NULL,
    created_at timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS ai_logs_user_id_created_at_index ON ai_logs(user_id, created_at);
CREATE INDEX IF NOT EXISTS ai_logs_user_id_task_type_index ON ai_logs(user_id, task_type);
CREATE INDEX IF NOT EXISTS ai_logs_status_created_at_index ON ai_logs(status, created_at);

-- --------------------------------------------------------
-- PROPOSALS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS proposals (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NULL REFERENCES clients(id) ON DELETE SET NULL,
    project_id bigint NULL REFERENCES projects(id) ON DELETE SET NULL,
    title varchar(255) NOT NULL,
    content text NULL,
    status varchar(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'accepted', 'rejected', 'converted')),
    ai_generated boolean NOT NULL DEFAULT false,
    sent_at timestamp(0) NULL,
    responded_at timestamp(0) NULL,
    notes text NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS proposals_user_id_status_index ON proposals(user_id, status);
CREATE INDEX IF NOT EXISTS proposals_client_id_index ON proposals(client_id);
CREATE INDEX IF NOT EXISTS proposals_project_id_index ON proposals(project_id);

-- --------------------------------------------------------
-- EXPENSES
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NULL REFERENCES clients(id) ON DELETE SET NULL,
    project_id bigint NULL REFERENCES projects(id) ON DELETE SET NULL,
    title varchar(255) NOT NULL,
    amount decimal(12,2) NOT NULL,
    category varchar(100) NOT NULL,
    expense_date date NOT NULL,
    notes text NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS expenses_user_id_expense_date_index ON expenses(user_id, expense_date);
CREATE INDEX IF NOT EXISTS expenses_user_id_category_index ON expenses(user_id, category);
CREATE INDEX IF NOT EXISTS expenses_project_id_index ON expenses(project_id);
CREATE INDEX IF NOT EXISTS expenses_client_id_index ON expenses(client_id);

-- --------------------------------------------------------
-- INVOICES
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoices (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NULL REFERENCES clients(id) ON DELETE SET NULL,
    project_id bigint NULL REFERENCES projects(id) ON DELETE SET NULL,
    proposal_id bigint NULL REFERENCES proposals(id) ON DELETE SET NULL,
    invoice_number varchar(20) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled')),
    issue_date date NOT NULL,
    due_date date NULL,
    subtotal decimal(12,2) NOT NULL DEFAULT 0,
    tax_rate decimal(5,2) NOT NULL DEFAULT 0,
    tax_amount decimal(12,2) NOT NULL DEFAULT 0,
    total decimal(12,2) NOT NULL DEFAULT 0,
    currency varchar(3) NOT NULL DEFAULT 'USD',
    notes text NULL,
    paid_at timestamp(0) NULL,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL,
    UNIQUE (user_id, invoice_number)
);
CREATE INDEX IF NOT EXISTS invoices_user_id_status_index ON invoices(user_id, status);
CREATE INDEX IF NOT EXISTS invoices_client_id_index ON invoices(client_id);
CREATE INDEX IF NOT EXISTS invoices_project_id_index ON invoices(project_id);
CREATE INDEX IF NOT EXISTS invoices_due_date_status_index ON invoices(due_date, status);

-- --------------------------------------------------------
-- KNOWLEDGE DOCUMENTS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS knowledge_documents (
    id bigserial PRIMARY KEY,
    user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    client_id bigint NULL REFERENCES clients(id) ON DELETE SET NULL,
    project_id bigint NULL REFERENCES projects(id) ON DELETE SET NULL,
    title varchar(255) NOT NULL,
    content text NULL,
    type varchar(20) NOT NULL DEFAULT 'note' CHECK (type IN ('note', 'template', 'sop', 'reference')),
    tags jsonb NULL,
    is_pinned boolean NOT NULL DEFAULT false,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL,
    deleted_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS knowledge_documents_user_id_type_index ON knowledge_documents(user_id, type);
CREATE INDEX IF NOT EXISTS knowledge_documents_user_id_is_pinned_index ON knowledge_documents(user_id, is_pinned);
CREATE INDEX IF NOT EXISTS ft_title_content ON knowledge_documents USING gin(to_tsvector('english', coalesce(title,'') || ' ' || coalesce(content,'')));

-- --------------------------------------------------------
-- INVOICE ITEMS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_items (
    id bigserial PRIMARY KEY,
    invoice_id bigint NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    description varchar(500) NOT NULL,
    quantity decimal(10,2) NOT NULL DEFAULT 1,
    unit_price decimal(12,2) NOT NULL DEFAULT 0,
    amount decimal(12,2) NOT NULL DEFAULT 0,
    sort_order smallint NOT NULL DEFAULT 0,
    created_at timestamp(0) NULL,
    updated_at timestamp(0) NULL
);
CREATE INDEX IF NOT EXISTS invoice_items_invoice_id_sort_order_index ON invoice_items(invoice_id, sort_order);

-- --------------------------------------------------------
-- MARK ALL MIGRATIONS AS COMPLETED
-- --------------------------------------------------------
INSERT INTO migrations (migration, batch) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_06_01_115915_create_personal_access_tokens_table', 1),
('2026_06_01_115927_create_clients_table', 1),
('2026_06_01_115928_create_contacts_table', 1),
('2026_06_01_115928_create_projects_table', 1),
('2026_06_01_115928_create_tasks_table', 1),
('2026_06_04_052216_create_ai_prompts_table', 1),
('2026_06_04_052217_add_ai_columns_to_projects_and_tasks', 1),
('2026_06_04_052217_create_ai_logs_table', 1),
('2026_06_05_034054_create_proposals_table', 1),
('2026_06_05_034055_create_expenses_table', 1),
('2026_06_05_034055_create_invoices_table', 1),
('2026_06_05_034055_create_knowledge_documents_table', 1),
('2026_06_05_034056_create_invoice_items_table', 1)
ON CONFLICT DO NOTHING;
