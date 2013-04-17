--
-- CopyData Module supporting tables
--
-- @original author Alexander Shereshevsky - Amdocs (shereshevsky@gmail.com)
-- @created Mar 16,2013
-- @last_modification Mar 16,2013
--

ALTER TABLE COPYDATA_PROJECTS
 DROP PRIMARY KEY CASCADE;

DROP TABLE COPYDATA_PROJECTS CASCADE CONSTRAINTS;