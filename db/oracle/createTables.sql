---
--- Main table storing events
---

CREATE TABLE  "EVENT" 
   (	
    "ID" NUMBER NOT NULL ENABLE, 
	"HOST_NAME" VARCHAR2(255) NOT NULL ENABLE,
    --- HOST_ADDRESS will be stored in binary form
	"HOST_ADDRESS" VARCHAR2(64) NOT NULL ENABLE, 
	"TYPE" NUMBER(11,0) NOT NULL ENABLE, 
	"FACILITY" NUMBER(11,0), 
	"PRIORITY" NUMBER(11,0) NOT NULL ENABLE, 
	"PROGRAM" VARCHAR2(50) NOT NULL ENABLE, 
	"MESSAGE" VARCHAR2(4000) DEFAULT NULL, 
	"ACK" NUMBER(*,0) DEFAULT '0', 
	"CREATED" DATE DEFAULT NULL, 
	"MODIFIED" DATE DEFAULT NULL, 
	 CONSTRAINT "PK_EVENT" PRIMARY KEY ("ID") ENABLE
   )
/

---
--- Indizes for event table, based on usual searches
---
CREATE INDEX  "EVENT_FAC_TIME" ON  "EVENT" ("FACILITY", "PRIORITY", "MODIFIED", "ACK")
/

CREATE INDEX  "EVENT_IDX1" ON  "EVENT" ("HOST_NAME", "TYPE", "FACILITY", "PRIORITY")
/

CREATE INDEX  "EVENT_TIME" ON  "EVENT" ("CREATED", "MODIFIED", "HOST_NAME", "TYPE")
/

CREATE INDEX  "EVENT_TYPE_IDX" ON  "EVENT" ("FACILITY", "PRIORITY", "PROGRAM", "ACK")
/

CREATE INDEX "EVENT_FACILITY" ON "EVENT" ("FACILITY")
/

CREATE INDEX "EVENT_PRIORITY" ON "EVENT" ("PRIORITY")
/

CREATE INDEX "EVENT_CREATED" ON "EVENT" ("CREATED")
/

CREATE INDEX "EVENT_MODIFIED" ON "EVENT" ("MODIFIED")
/

CREATE INDEX "EVENT_ACK" ON "EVENT" ("ACK")
/

CREATE INDEX "EVENT_HOST_NAME" ON "EVENT" ("HOST_NAME")
/

---
--- Primary key auto_increment
---
CREATE SEQUENCE event_seq
 START WITH     1
 INCREMENT BY   1
/

CREATE TRIGGER trigger_event_pk BEFORE INSERT ON event
       REFERENCING NEW AS NEW OLD AS OLD FOR EACH ROW
Begin
SELECT event_seq.NEXTVAL INTO :NEW.id FROM DUAL;
End;
/


CREATE TABLE  "EVENT_COMMENT" 
   (	"ID" NUMBER, 
	"EVENT_ID" NUMBER, 
	"TYPE" NUMBER, 
	"MESSAGE" VARCHAR2(1024), 
	"CREATED" DATE, 
	"MODIFIED" DATE, 
	"COMMENT_USER" VARCHAR2(64), 
	 CONSTRAINT "EVENT_COMMENT_PK" PRIMARY KEY ("ID") ENABLE
   )
/

CREATE SEQUENCE event_comment_seq
 START WITH     1
 INCREMENT BY   1
/

CREATE TRIGGER trigger_c_event_pk BEFORE INSERT ON event_comment
       REFERENCING NEW AS NEW OLD AS OLD FOR EACH ROW
Begin
SELECT event_comment_seq.NEXTVAL INTO :NEW.id FROM DUAL;
End;
/

-- make sure sequences are initalized
SELECT event_comment_seq.NEXTVAL FROM DUAL;			
SELECT event_seq.NEXTVAL FROM DUAL;
