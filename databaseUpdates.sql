
--
--Update the database structure
--
	


--[Action required] 
--
-- Treat Data of DeleteTracker:
--

--
-- Delete table DeleteTracker
--
DROP TABLE DeleteTracker;
		

--
-- Create new table ChangesTracker
--
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ChangesTracker` (
  `ChangesTracker_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ChangesTracker_DATE` datetime NOT NULL,
  `ChangesTracker_ENTITY` varchar(20) NOT NULL,
  `ChangesTracker_ObjID` varchar(64) NOT NULL,
  `ChangesTracker_DEVICE` int(11) NOT NULL,
  `ChangesTracker_Type` char(6) NOT NULL COMMENT '`modif` or `delete` to indicate the type of changes in the data',
  PRIMARY KEY (`ChangesTracker_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Create new table TestAdd
--
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ChangesTracker` (
  `ChangesTracker_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ChangesTracker_DATE` datetime NOT NULL,
  `ChangesTracker_ENTITY` varchar(20) NOT NULL,
  `ChangesTracker_ObjID` varchar(64) NOT NULL,
  `ChangesTracker_DEVICE` int(11) NOT NULL,
  `ChangesTracker_Type` char(6) NOT NULL COMMENT '`modif` or `delete` to indicate the type of changes in the data',
  PRIMARY KEY (`ChangesTracker_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `TestAdd` (
  `TESTfs` int(11) NOT NULL,
  `jfklsahég` int(11) NOT NULL,
  `jgksaélf` int(11) NOT NULL,
  `jhgkaséj` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;


--
-- Modify data type of ProgramClass.PROGRAMCLASS_OBJECTIVE
--
ALTER TABLE ProgramClass
MODIFY COLUMN PROGRAMCLASS_OBJECTIVE text;
		