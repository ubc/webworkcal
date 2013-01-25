DELIMITER $$

DROP PROCEDURE IF EXISTS `webwork`.`assignment_due` $$
CREATE PROCEDURE `webwork`.`assignment_due` ()
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE tname TEXT DEFAULT '';
  DECLARE course_name TEXT;
  DECLARE query_str TEXT DEFAULT '';
  DECLARE studentCount INT;
  DECLARE cur CURSOR FOR SELECT table_name FROM `INFORMATION_SCHEMA`.`TABLES` as t WHERE table_schema = 'webwork' AND t.`table_name` LIKE "%_set";
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  DROP TABLE IF EXISTS `tmp_assignments`;
  CREATE TEMPORARY TABLE `tmp_assignments` (
    `course` VARCHAR(255),
    `set_id` VARCHAR(255),
    `open_date` bigint(20),
    `due_date`  bigint(20),
    `answer_date` bigint(20),
    `student_count` int(8)
  );
  OPEN cur;
  FETCH cur INTO tname;
  

  table_loop:LOOP
    FETCH cur INTO tname;
    SET course_name = LEFT(tname, LENGTH(tname)-4);

    SET @query_str = concat('SELECT count(*) INTO @studentCount FROM `', course_name, '_user`');
    PREPARE stmt1 FROM @query_str;
    EXECUTE stmt1;
    DEALLOCATE PREPARE stmt1;
    
    SET @query_str = concat('INSERT INTO tmp_assignments SELECT "', course_name, '", CONVERT(`set_id` USING utf8), `open_date`, `due_date`, `answer_date`, ? FROM `', tname, '`');
    PREPARE stmt2 FROM @query_str;
    EXECUTE stmt2 USING @studentCount;
    DEALLOCATE PREPARE stmt2;

    IF done THEN
      LEAVE table_loop;
    END IF;
  END LOOP;
  
  CLOSE cur;

  SELECT `course`, `set_id`, FROM_UNIXTIME(`due_date`), `student_count` from `tmp_assignments` WHERE `due_date` > UNIX_TIMESTAMP() ORDER BY `due_date` DESC;
END $$
