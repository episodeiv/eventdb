\set eventdb_owner 'eventdb';

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = off;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;

SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;


--- table event
DROP TABLE IF EXISTS event CASCADE;
DROP SEQUENCE IF EXISTS event_id_seq;

CREATE SEQUENCE event_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

ALTER SEQUENCE event_id_seq OWNER TO :eventdb_owner;

CREATE TABLE event (
  id bigint NOT NULL PRIMARY KEY default nextval('event_id_seq'),
  host_name varchar(255) NOT NULL,
  host_address inet NOT NULL,
  type integer NOT NULL,
  facility integer,
  priority integer NOT NULL,
  program varchar(50) NOT NULL,
  message varchar(4096) default NULL,
  ack smallint default '0',
  created timestamp default NULL,
  modified timestamp default NULL
);

ALTER TABLE public.event OWNER TO :eventdb_owner;


CREATE INDEX idx_id_unique ON event USING btree (id, host_name, host_address, type, program, priority, facility, ack, created, modified);
CREATE INDEX order_created_unique ON event USING btree (created);
CREATE INDEX order_host_name_unique ON event USING btree (host_name);
CREATE INDEX order_prioNr_unique ON event USING btree (priority);
CREATE INDEX order_facNr_unique ON event USING btree (facility);
CREATE INDEX order_modified_unique ON event USING btree (modified);
CREATE INDEX order_ack_unique ON event USING btree (ack);
CREATE INDEX order_program ON event USING btree (program);
CREATE INDEX order_type ON event USING btree ("type");


--- table comment
DROP TABLE IF EXISTS comment CASCADE;
DROP SEQUENCE IF EXISTS comment_id_seq;

CREATE SEQUENCE comment_id_seq
    START WITH 1
    INCREMENT BY 2
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;

ALTER TABLE comment_id_seq OWNER TO :eventdb_owner;

CREATE TABLE comment (
  id integer NOT NULL PRIMARY KEY default nextval('comment_id_seq'),
  event_id bigint NOT NULL,
  type integer default '0',
  message varchar(1024) default NULL,
  created timestamp default NULL,
  modified timestamp default NULL,
  username varchar(64) NOT NULL,
  CONSTRAINT db_comments_ibfk_1 FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE
);

ALTER TABLE public.comment OWNER TO :eventdb_owner;

CREATE INDEX fk_event_id_unique ON comment USING btree (event_id);
CREATE INDEX idx_all_unique ON comment USING btree (id, event_id, username);


--- set rights
REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;

