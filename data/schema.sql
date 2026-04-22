create table if not exists associations (
  id char(36) not null,
  name text not null,
  contact_email text,
  created_at timestamp not null default current_timestamp,
  primary key (id)
) engine=InnoDB;

create table if not exists templates (
  id char(36) not null,
  name text not null,
  description text,
  default_pages json not null,
  created_at timestamp not null default current_timestamp,
  primary key (id),
  unique key templates_name_unique (name)
) engine=InnoDB;

create table if not exists form_requests (
  id char(36) not null,
  association_name text not null,
  contact_email text,
  site_name text not null,
  domain text,
  template_id char(36),
  notes text,
  status text not null default 'novo',
  created_at timestamp not null default current_timestamp,
  converted_at timestamp null,
  primary key (id),
  constraint fk_form_requests_template
    foreign key (template_id) references templates(id)
    on delete set null
) engine=InnoDB;

create table if not exists sites (
  id char(36) not null,
  association_id char(36) not null,
  template_id char(36),
  name text not null,
  status text not null default 'rascunho',
  plan text not null default 'basico',
  domain text,
  notes text,
  created_at timestamp not null default current_timestamp,
  primary key (id),
  constraint fk_sites_association
    foreign key (association_id) references associations(id)
    on delete cascade,
  constraint fk_sites_template
    foreign key (template_id) references templates(id)
    on delete set null
) engine=InnoDB;

create table if not exists site_pages (
  id char(36) not null,
  site_id char(36) not null,
  title text not null,
  file text not null,
  status text not null default 'rascunho',
  sort_order int not null default 0,
  created_at timestamp not null default current_timestamp,
  primary key (id),
  key site_pages_site_id_idx (site_id),
  constraint fk_site_pages_site
    foreign key (site_id) references sites(id)
    on delete cascade
) engine=InnoDB;

create table if not exists users (
  id char(36) not null,
  name varchar(255) not null,
  email varchar(255) not null,
  password varchar(255) not null,
  email_verified_at timestamp null,
  verification_token varchar(100) null,
  reset_token varchar(100) null,
  reset_token_expires_at timestamp null,
  created_at timestamp not null default current_timestamp,
  primary key (id),
  unique key users_email_unique (email)
) engine=InnoDB;

insert ignore into templates (id, name, description, default_pages)
values
  (
    uuid(),
    'Modelo Institucional',
    'Para associacoes e entidades',
    '[{"title":"Home","file":"index.html"},{"title":"Sobre","file":"sobre.html"},{"title":"Contato","file":"contato.html"}]'
  ),
  (
    uuid(),
    'Modelo Evento',
    'Para feiras, congressos e workshops',
    '[{"title":"Home","file":"index.html"},{"title":"Expositores","file":"expositores.html"}]'
  ),
  (
    uuid(),
    'Modelo Celebracao',
    'Para cerimonias e eventos sociais',
    '[{"title":"Home","file":"index.html"},{"title":"Cerimonia","file":"cerimonia.html"},{"title":"Confirmacao","file":"confirmacao.html"}]'
  );

