<?php

function getMySQLVersion() {
	global $wpdb;
	$semver  = " 
	 SELECT VERSION() version,
	        1 canreindex,
	        1 unconstrained,
            CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED) major,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED) minor,
            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(VERSION(), '-', '.'), '.', 3), '.', -1) AS UNSIGNED) build,
            '' fork, '' distro";
	$results = $wpdb->get_results( $semver );
	$results = $results[0];

	$ver = explode( '-', $results->version, 3 );
	if ( count( $ver ) >= 2 ) {
		$results->fork = $ver[1];
	}
	if ( count( $ver ) >= 3 ) {
		$results->distro = $ver[2];
	}
	if ( $results->major >= 8 ) {
		return $results;
	}
	/* innodb_large_prefix variable is missing in MySQL 8+ */
	$largePrefix = "SELECT @@innodb_large_prefix";
	$largePrefix = $wpdb->get_var( $largePrefix );
	if ( $largePrefix >= 3072 ) {
		$results->unconstrained = true;
	}
	$results->unconstrained = true;
	if ( $results->major < 5 ) {
		$results->canreindex = 0;
	}
	if ( $results->major === 5 && $results->minor === 5 && $results->build < 62 ) {
		$results->canreindex = 0;
	}
	if ( $results->major === 5 && $results->minor === 6 && $results->build < 4 ) {
		$results->canreindex = 0;
	}

	return $results;
}

/**
 * @param $semver
 *
 * @return array
 */
function getReindexingInstructions( $semver ) {
	$reindexAnyway = array(
		"posts"    => array(
			"tablename"     => "posts",
			"check.enable"  => array(
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, ID)",
				"post_author"      => "ADD KEY post_author (post_author)"
			),
			"enable"        => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author (post_author, post_type, post_status, post_date, ID)"
			),
			"check.disable" => array(
				"type_status_date" => "ADD KEY type_status_date (post_type, post_status, post_date, post_author, ID)",
				"post_author"      => "ADD KEY post_author (post_author, post_type, post_status, post_date, ID)"
			),
			"disable"       => array(
				"DROP KEY type_status_date",
				"ADD KEY type_status_date (post_type, post_status, post_date, ID)",
				"DROP KEY post_author",
				"ADD KEY post_author (post_author)"
			),
		),
		"comments" => array(
			"tablename"     => "comments",
			"check.enable"  => array(
				"comment_post_parent_approved" => null,
			),
			"enable"        => array(
				"ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_ID)"
			),
			"check.disable" => array(
				"comment_post_parent_approved" => "ADD KEY comment_post_parent_approved (comment_post_ID, comment_parent, comment_approved, comment_ID)"
			),
			"disable"       => array(
				"DROP KEY comment_post_parent_approved"
			),
		)
	);


	$reindexWithoutConstraint = array(
		"postmeta" => array(
			"tablename"     => "postmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)",
				"meta_id"     => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (post_id, meta_key, meta_id)",
				"DROP KEY post_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, post_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_key, meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key, post_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"ADD KEY post_id (post_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),

		"usermeta" => array(
			"tablename"     => "usermeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"user_id"     => "ADD KEY user_id (user_id)",
				"umeta_id"    => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY umeta_id (umeta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
				"DROP KEY user_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, user_id)"
			),
			"check.disable" => array(
				"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
				"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, meta_key, umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key, user_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (umeta_id)",
				"DROP KEY umeta_id",
				"DROP KEY user_id",
				"ADD KEY user_id (user_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),
		"termmeta" => array(
			"tablename"     => "termmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"term_id"     => "ADD KEY term_id (term_id)",
				"meta_id"     => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (term_id, meta_key, meta_id)",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id, meta_key)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key, term_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_key, meta_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
				"term_id"     => "ADD KEY term_id (term_id, meta_key)",
				"meta_key"    => "ADD KEY meta_key (meta_key, term_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id)",
			),
		),
		"options"  => array(
			"tablename"     => "options",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
				"autoload"    => "ADD KEY autoload (autoload)",
				"option_id"   => null,
			),
			"enable"        => array(
				"ADD UNIQUE KEY option_id (option_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (autoload, option_id)",
				"DROP KEY autoload"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
				"option_id"   => "ADD UNIQUE KEY option_id (option_id)",
				"autoload"    => null,
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (option_id)",
				"DROP KEY option_id",
				"ADD KEY autoload (autoload)"
			),
		)
	);

	$reindexWith191Constraint = array(
		"postmeta" => array(
			"tablename"     => "postmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"post_id"     => "ADD KEY post_id (post_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (post_id, meta_id)",
				"DROP KEY post_id",
				"ADD KEY post_id (post_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), post_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (post_id, meta_id)",
				"post_id"     => "ADD KEY post_id (post_id, meta_key(191))",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), post_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY post_id",
				"ADD KEY post_id (post_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),

		"usermeta" => array(
			"tablename"     => "usermeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"user_id"     => "ADD KEY user_id (user_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY umeta_id (umeta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (user_id, umeta_id)",
				"DROP KEY user_id",
				"ADD KEY user_id (user_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), user_id)"
			),
			"check.disable" => array(
				"umeta_id"    => "ADD UNIQUE KEY umeta_id (umeta_id)",
				"user_id"     => "ADD KEY user_id (user_id, meta_key(191))",
				"PRIMARY KEY" => "ADD PRIMARY KEY (user_id, umeta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), user_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (umeta_id)",
				"DROP KEY umeta_id",
				"DROP KEY user_id",
				"ADD KEY user_id (user_id)",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
			),
		),
		"termmeta" => array(
			"tablename"     => "termmeta",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (meta_id)",
				"meta_key"    => "ADD KEY meta_key (meta_key(191))",
				"term_id"     => "ADD KEY term_id (term_id)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY meta_id (meta_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (term_id, meta_id)",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id, meta_key(191))",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191), term_id)"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (term_id, meta_id)",
				"meta_id"     => "ADD UNIQUE KEY meta_id (meta_id)",
				"term_id"     => "ADD KEY term_id (term_id, meta_key(191))",
				"meta_key"    => "ADD KEY meta_key (meta_key(191), term_id)",
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (meta_id)",
				"DROP KEY meta_id",
				"DROP KEY meta_key",
				"ADD KEY meta_key (meta_key(191))",
				"DROP KEY term_id",
				"ADD KEY term_id (term_id)",
			),
		),
		"options"  => array(
			"tablename"     => "options",
			"check.enable"  => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (option_id)",
				"autoload"    => "ADD KEY autoload (autoload)"
			),
			"enable"        => array(
				"ADD UNIQUE KEY option_id (option_id)",
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (autoload, option_id)",
				"DROP KEY autoload"
			),
			"check.disable" => array(
				"PRIMARY KEY" => "ADD PRIMARY KEY (autoload, option_id)",
				"option_id"   => "ADD UNIQUE KEY option_id (option_id)"
			),
			"disable"       => array(
				"DROP PRIMARY KEY",
				"ADD PRIMARY KEY (option_id)",
				"DROP KEY option_id",
				"ADD KEY autoload (autoload)"
			),
		)
	);
	switch ( $semver->unconstrained ) {
		case 1:
			return array_merge( $reindexWithoutConstraint, $reindexAnyway );
		case 0:
			return array_merge( $reindexWith191Constraint, $reindexAnyway );
		default:
			return $reindexAnyway;
	}
}

function getQueries() {
	global $wpdb;
	$p = $wpdb->prefix;
	/** @var array $queryArray an array of arrays of queries for this to use */
	$queryArray = array(
		"indexes" => "		
        SELECT
           IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT (s.INDEX_NAME)) key_name,   
           s.TABLE_NAME,  
               IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', 1, 0) is_primary,
               CASE WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN 1 
                    WHEN tc.CONSTRAINT_TYPE LIKE 'UNIQUE' THEN 1
                    ELSE 0 END is_unique,
               IF(MAX(c.EXTRA) = 'auto_increment', 1, 0) 'contains_autoincrement',
               IF(MAX(c.EXTRA) = 'auto_increment' AND COUNT(*) = 1, 1, 0) 'is_autoincrement',
               CONCAT ( 'ADD ',
                CASE WHEN tc.CONSTRAINT_TYPE = 'UNIQUE' THEN CONCAT ('UNIQUE KEY ', s.INDEX_NAME)
                     WHEN tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY' THEN tc.CONSTRAINT_TYPE
                                         ELSE CONCAT ('KEY', ' ', s.INDEX_NAME) END,
                ' (',
                GROUP_CONCAT(
                  IF(s.SUB_PART IS NULL, s.COLUMN_NAME, CONCAT(s.COLUMN_NAME,'(',s.SUB_PART,')'))
                  ORDER BY s.SEQ_IN_INDEX 
                  SEPARATOR ', '),
                ')'
                ) `add`,
               CONCAT ( 'DROP ',
                IF(tc.CONSTRAINT_TYPE LIKE 'PRIMARY KEY', tc.CONSTRAINT_TYPE, CONCAT ('KEY', ' ', s.INDEX_NAME))
                ) `drop`,
               CONCAT ('ALTER TABLE ', s.TABLE_SCHEMA, '.', s.TABLE_NAME, ' ') `alter`,	
               MAX(t.ENGINE) engine,
               MAX(t.ROW_FORMAT) row_format,
            r.rowlength
          FROM information_schema.STATISTICS s
          LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                  ON s.TABLE_NAME = tc.TABLE_NAME
                 AND s.TABLE_SCHEMA = tc.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = tc.CONSTRAINT_CATALOG 
                 AND s.INDEX_NAME = tc.CONSTRAINT_NAME
          LEFT JOIN information_schema.COLUMNS c
                  ON s.TABLE_NAME = c.TABLE_NAME
                 AND s.TABLE_SCHEMA = c.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = c.TABLE_CATALOG 
                 AND s.COLUMN_NAME = c.COLUMN_NAME
      LEFT JOIN information_schema.TABLES t
                  ON s.TABLE_NAME = t.TABLE_NAME
                 AND s.TABLE_SCHEMA = t.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = t.TABLE_CATALOG
      LEFT JOIN (
        SELECT c.TABLE_NAME,
               c.TABLE_SCHEMA,
               c.TABLE_CATALOG,
               SUM(
                   CASE WHEN c.GENERATION_EXPRESSION IS NOT NULL THEN 0
                        WHEN c.DATA_TYPE IN ('varchar', 'char') THEN c.CHARACTER_OCTET_LENGTH
                        WHEN c.DATA_TYPE = 'int' THEN 4
                        WHEN c.DATA_TYPE = 'bigint' THEN 8
                        WHEN c.DATA_TYPE = 'float' THEN 4
                        WHEN c.DATA_TYPE = 'double' THEN 8
                        WHEN c.DATA_TYPE = 'date' THEN 3
                        WHEN c.DATA_TYPE = 'time' THEN 3 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                        WHEN c.DATA_TYPE = 'timestamp' THEN 4 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                        WHEN c.DATA_TYPE = 'datetime' THEN 5 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                        ELSE 0 END                
                   ) rowlength
         FROM information_schema.COLUMNS c
        GROUP BY c.TABLE_NAME, c.TABLE_SCHEMA, c.TABLE_CATALOG
        ) r   ON s.TABLE_NAME = r.TABLE_NAME
                 AND s.TABLE_SCHEMA = r.TABLE_SCHEMA
                 AND s.TABLE_CATALOG = r.TABLE_CATALOG
         WHERE s.TABLE_SCHEMA = DATABASE()
           AND s.TABLE_NAME = %s
         GROUP BY s.TABLE_NAME, s.INDEX_NAME
         ORDER BY s.TABLE_NAME, s.INDEX_NAME",

		"dbstats" => array(
			"SELECT VARIABLE_NAME variable, COALESCE(SESSION_VALUE, GLOBAL_VALUE) value FROM information_schema.SYSTEM_VARIABLES ORDER BY VARIABLE_NAME",
			/* fetch key/value statistics */
			<<<QQQ
        SELECT 'postmeta' AS 'table',
               '${p}' AS 'prefix',
                COUNT(*) AS 'count',
                COUNT(DISTINCT post_id) distinct_id,
                COUNT(DISTINCT meta_key) distinct_key,
                MAX(LENGTH(meta_key)) key_max_length,
                MAX(LENGTH(meta_value)) value_max_length,
                MIN(LENGTH(meta_key)) key_min_length,
                MIN(LENGTH(meta_value)) value_min_length,
                SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
                SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
                0 autoload_count
          FROM ${p}postmeta
        UNION ALL
        SELECT 'usermeta' AS 'table',
               '${p}' AS 'prefix',
                COUNT(*) AS 'count',
                COUNT(DISTINCT user_id) distinct_id,
                COUNT(DISTINCT meta_key) distinct_key,
                MAX(LENGTH(meta_key)) key_max_length,
                MAX(LENGTH(meta_value)) meta_value_max_length,
                MIN(LENGTH(meta_key)) key_min_length,
                MIN(LENGTH(meta_value)) value_min_length,
                SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
                SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
                0 autoload_count
          FROM ${p}usermeta
        UNION ALL
        SELECT 'termmeta' AS 'table',
               '${p}' AS 'prefix',
                COUNT(*) AS 'count',
                COUNT(DISTINCT term_id) distinct_id,
                COUNT(DISTINCT meta_key) distinct_key,
                MAX(LENGTH(meta_key)) key_max_length,
                MAX(LENGTH(meta_value)) value_max_length,
                MIN(LENGTH(meta_key)) key_min_length,
                MIN(LENGTH(meta_value)) value_min_length,
                SUM(CASE WHEN LENGTH(meta_key) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
                SUM(CASE WHEN LENGTH(meta_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
                0 autoload_count
          FROM ${p}termmeta
        UNION ALL 
        SELECT 'options' AS  'table',
               '${p}' AS 'prefix',
                COUNT(*) AS 'count',
                0 AS distinct_id,
                COUNT(DISTINCT option_name) distinct_meta_key,
                MAX(LENGTH(option_name)) key_max_length,
                MAX(LENGTH(option_value)) value_max_length,
                MIN(LENGTH(option_name)) min_length,
                MIN(LENGTH(option_value)) value_min_length,
                SUM(CASE WHEN LENGTH(option_name) > 191 THEN 1 ELSE 0 END) longer_191_key_count,
                SUM(CASE WHEN LENGTH(option_value) > 191 THEN 1 ELSE 0 END) longer_191_value_count,
                SUM(CASE WHEN autoload = 'yes' THEN 1 ELSE 0 END) autoload_count
          FROM ${p}options;
        QQQ,
			<<<QQQ
            SELECT c.TABLE_NAME,
                   t.ENGINE,
                   t.ROW_FORMAT,
                   COUNT(*) column_count,
                   SUM(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_OCTET_LENGTH, 0)) varchar_total_octets,
                   MAX(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_OCTET_LENGTH, 0)) varchar_max_octets,
                   SUM(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_MAXIMUM_LENGTH, 0)) varchar_total_length,
                   MAX(IF(c.DATA_TYPE = 'varchar', c.CHARACTER_MAXIMUM_LENGTH, 0)) varchar_max_length,
                   SUM(c.DATA_TYPE= 'varchar') varchar_columns,
                   SUM(IF(c.DATA_TYPE = 'char', c.CHARACTER_OCTET_LENGTH, 0)) char_total_octets,
                   MAX(IF(c.DATA_TYPE = 'char', c.CHARACTER_OCTET_LENGTH, 0)) char_max_octets,
                   SUM(IF(c.DATA_TYPE = 'char', c.CHARACTER_MAXIMUM_LENGTH, 0)) char_total_length,
                   MAX(IF(c.DATA_TYPE = 'char', c.CHARACTER_MAXIMUM_LENGTH, 0)) char_max_length,
                   SUM(c.DATA_TYPE= 'char') char_columns,
                   SUM(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_OCTET_LENGTH, 0)) longtext_total_octets,
                   MAX(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_OCTET_LENGTH, 0)) longtext_max_octets,
                   SUM(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_MAXIMUM_LENGTH, 0)) longtext_total_length,
                   MAX(IF(c.DATA_TYPE = 'longtext', c.CHARACTER_MAXIMUM_LENGTH, 0)) longtext_max_length,
                   SUM(c.DATA_TYPE= 'longtext') longtext_columns,
                   SUM(IF(c.DATA_TYPE = 'text', c.CHARACTER_OCTET_LENGTH, 0)) text_sum_octets,
                   MAX(IF(c.DATA_TYPE = 'text', c.CHARACTER_OCTET_LENGTH, 0)) text_max_octets,
                   SUM(IF(c.DATA_TYPE = 'text', c.CHARACTER_MAXIMUM_LENGTH, 0)) text_total_length,
                   MAX(IF(c.DATA_TYPE = 'text', c.CHARACTER_MAXIMUM_LENGTH, 0)) text_max_length,
                   SUM(c.DATA_TYPE= 'text') text_columns,		        
                   SUM(
                       CASE WHEN c.GENERATION_EXPRESSION IS NOT NULL THEN 0
                            WHEN c.DATA_TYPE IN ('varchar', 'char') THEN c.CHARACTER_OCTET_LENGTH
                            WHEN c.DATA_TYPE = 'int' THEN 4
                            WHEN c.DATA_TYPE = 'bigint' THEN 8
                            WHEN c.DATA_TYPE = 'float' THEN 4
                            WHEN c.DATA_TYPE = 'double' THEN 8
                            WHEN c.DATA_TYPE = 'date' THEN 3
                            WHEN c.DATA_TYPE = 'time' THEN 3 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                            WHEN c.DATA_TYPE = 'timestamp' THEN 4 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                            WHEN c.DATA_TYPE = 'datetime' THEN 5 + FLOOR((1+c.DATETIME_PRECISION) / 2)
                            ELSE 0 END                
                       ) rowlength
                 FROM information_schema.COLUMNS c
                 JOIN information_schema.TABLES t
                       ON c.TABLE_NAME = t.TABLE_NAME
                      AND c.TABLE_SCHEMA = t.TABLE_SCHEMA
                      AND c.TABLE_CATALOG = t.TABLE_CATALOG
                 WHERE c.TABLE_SCHEMA = DATABASE()
                GROUP BY c.TABLE_NAME, c.TABLE_SCHEMA, c.TABLE_CATALOG
        QQQ

		)
	);

	return $queryArray;
}

