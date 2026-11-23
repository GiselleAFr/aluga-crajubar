#!/bin/sh
set -e

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    echo "Slave: aguardando o master..."
    until pg_isready -h "$MASTER_HOST" -U "$POSTGRES_USER"; do sleep 2; done

    echo "Slave: copiando dados do master (pg_basebackup)..."
    PGPASSWORD="$REPLICATION_PASSWORD" pg_basebackup \
        -h "$MASTER_HOST" -U replicator -D "$PGDATA" -X stream -S slave_slot -P

    touch "$PGDATA/standby.signal"
    {
        echo "primary_conninfo = 'host=$MASTER_HOST port=5432 user=replicator password=$REPLICATION_PASSWORD'"
        echo "primary_slot_name = 'slave_slot'"
    } >> "$PGDATA/postgresql.auto.conf"
    chmod 700 "$PGDATA"
fi

exec docker-entrypoint.sh postgres