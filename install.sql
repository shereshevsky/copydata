--
-- SnapRefresh Module supporting tables
--
-- @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
-- @created Jan 16 2008
-- @last_modification Jan 16,2008
--

ALTER TABLE OWEB.COPYDATA_PROJECTS
 DROP PRIMARY KEY CASCADE;

DROP TABLE OWEB.COPYDATA_PROJECTS CASCADE CONSTRAINTS;

CREATE TABLE OWEB.COPYDATA_PROJECTS
(
  PROJECT         VARCHAR2(30 BYTE),
  SRC_OWNER       VARCHAR2(30 BYTE),
  SRC_DB          VARCHAR2(30 BYTE),
  TRG_OWNER       VARCHAR2(30 BYTE),
  TRG_DB          VARCHAR2(30 BYTE),
  TABLE_NAME      VARCHAR2(30 BYTE),
  WHERE_ST        VARCHAR2(200 BYTE),
  TRUNCATE_TABLE  NUMBER(1),
  MISSING         NUMBER(1),
  CRE_BACKUP      NUMBER(1),
  PROJECT_OWNER   VARCHAR2(30 BYTE),
  SNAPSHOT        NUMBER(1),
  HASH            VARCHAR2(30 BYTE),
  SEQUENCE        NUMBER(1)
);


CREATE UNIQUE INDEX OWEB.COPYDATA_PROJECTS_PK ON OWEB.COPYDATA_PROJECTS
(PROJECT, PROJECT_OWNER, SRC_OWNER, SRC_DB, TRG_OWNER, 
TRG_DB, TABLE_NAME);


ALTER TABLE OWEB.COPYDATA_PROJECTS ADD (
  CONSTRAINT COPYDATA_PROJECTS_PK
  PRIMARY KEY
  (PROJECT, PROJECT_OWNER, SRC_OWNER, SRC_DB, TRG_OWNER, TRG_DB, TABLE_NAME)
  USING INDEX OWEB.COPYDATA_PROJECTS_PK
  ENABLE VALIDATE);

exit;