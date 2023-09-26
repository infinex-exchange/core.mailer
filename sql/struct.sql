CREATE EXTENSION IF NOT EXISTS timescaledb;

CREATE ROLE "core.mailer" LOGIN PASSWORD 'password';

create table mails(
    time timestamptz not null default current_timestamp,
    uid bigint,
    email varchar(254) not null,
    template varchar(64) not null,
    context text not null
);
SELECT create_hypertable('mails', 'time');
SELECT add_retention_policy('mails', INTERVAL '2 years');

GRANT INSERT ON mails TO "core.mailer";